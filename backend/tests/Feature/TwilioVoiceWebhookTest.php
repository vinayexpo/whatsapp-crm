<?php

use App\Jobs\ProcessInboundVoiceCall;
use App\Models\ApiConnection;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use App\Models\VoiceAgent;
use App\Models\VoiceCall;
use App\Models\WebhookEvent;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

function twilioSignatureFor(string $url, array $params, string $authToken): string
{
    $data = $url;
    foreach ($params as $key => $value) {
        $data .= $key.$value;
    }

    return base64_encode(hash_hmac('sha1', $data, $authToken, true));
}

it('rejects an inbound voice webhook with an invalid signature', function () {
    config(['services.twilio.auth_token' => 'test-token']);

    $this->post('/api/webhooks/twilio/voice', [
        'CallSid' => 'CA123',
        'From' => '+15551234567',
        'To' => '+15557654321',
    ], ['X-Twilio-Signature' => 'invalid'])->assertForbidden();

    expect(WebhookEvent::query()->count())->toBe(0);
});

it('accepts an inbound voice webhook with a valid signature and returns TwiML', function () {
    Queue::fake();
    config(['services.twilio.auth_token' => 'test-token']);

    $params = ['CallSid' => 'CA123', 'From' => '+15551234567', 'To' => '+15557654321'];
    $url = url('/api/webhooks/twilio/voice');
    $signature = twilioSignatureFor($url, $params, 'test-token');

    $response = $this->post('/api/webhooks/twilio/voice', $params, ['X-Twilio-Signature' => $signature]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/xml');
    expect(WebhookEvent::query()->count())->toBe(1);
    Queue::assertPushed(ProcessInboundVoiceCall::class);
});

it('accepts an inbound voice webhook without a signature when no auth token is configured', function () {
    Queue::fake();
    config(['services.twilio.auth_token' => null]);

    $response = $this->post('/api/webhooks/twilio/voice', [
        'CallSid' => 'CA123',
        'From' => '+15551234567',
        'To' => '+15557654321',
    ]);

    $response->assertOk();
    expect(WebhookEvent::query()->count())->toBe(1);
    Queue::assertPushed(ProcessInboundVoiceCall::class);
});

it('creates a contact, conversation, and voice call when processing an inbound call', function () {
    $company = Company::factory()->create();
    ApiConnection::factory()->create([
        'company_id' => $company->id,
        'channel' => 'voice',
        'twilio_phone_number' => '+15557654321',
    ]);
    VoiceAgent::factory()->create(['company_id' => $company->id, 'status' => 'active']);

    $event = WebhookEvent::query()->create([
        'provider' => 'twilio_voice',
        'payload' => ['CallSid' => 'CA999', 'From' => '+15559998888', 'To' => '+15557654321'],
    ]);

    (new ProcessInboundVoiceCall($event->id))->handle();

    $contact = Contact::query()->where('handle', '+15559998888')->first();
    expect($contact)->not->toBeNull();
    expect($contact->company_id)->toBe($company->id);

    $conversation = Conversation::query()->where('contact_id', $contact->id)->where('channel', 'voice')->first();
    expect($conversation)->not->toBeNull();

    $voiceCall = VoiceCall::query()->where('provider_call_sid', 'CA999')->first();
    expect($voiceCall)->not->toBeNull();
    expect($voiceCall->direction)->toBe('inbound');
    expect($voiceCall->medium)->toBe('telephony');
    expect($voiceCall->status)->toBe('ringing');
    expect($voiceCall->conversation_id)->toBe($conversation->id);

    expect($event->fresh()->processed_at)->not->toBeNull();
});

it('notifies voice-agent managers with voice_call_ringing when an inbound call arrives', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $company = Company::factory()->create();
    $manager = User::factory()->create(['company_id' => $company->id]);
    $manager->assignRole('manager');

    ApiConnection::factory()->create([
        'company_id' => $company->id,
        'channel' => 'voice',
        'twilio_phone_number' => '+15557654321',
    ]);
    VoiceAgent::factory()->create(['company_id' => $company->id, 'status' => 'active']);

    $event = WebhookEvent::query()->create([
        'provider' => 'twilio_voice',
        'payload' => ['CallSid' => 'CA888', 'From' => '+15559998888', 'To' => '+15557654321'],
    ]);

    (new ProcessInboundVoiceCall($event->id))->handle();

    $this->assertDatabaseHas('notifications', [
        'user_id' => $manager->id,
        'type' => 'voice_call_ringing',
    ]);
});

it('does nothing when no voice connection matches the called number', function () {
    $event = WebhookEvent::query()->create([
        'provider' => 'twilio_voice',
        'payload' => ['CallSid' => 'CA111', 'From' => '+15551112222', 'To' => '+19999999999'],
    ]);

    (new ProcessInboundVoiceCall($event->id))->handle();

    expect(VoiceCall::query()->count())->toBe(0);
});

it('updates voice call status from a status callback and flags followup on failure', function () {
    $voiceCall = VoiceCall::factory()->create([
        'provider_call_sid' => 'CA555',
        'status' => 'ringing',
    ]);

    $this->post('/api/webhooks/twilio/voice/status', [
        'CallSid' => 'CA555',
        'CallStatus' => 'no-answer',
    ])->assertNoContent();

    $voiceCall->refresh();
    expect($voiceCall->status)->toBe('no_answer');
    expect($voiceCall->needs_human_followup)->toBeTrue();
    expect($voiceCall->ended_at)->not->toBeNull();
});

it('sets the recording url from a recording callback', function () {
    $voiceCall = VoiceCall::factory()->create(['provider_call_sid' => 'CA777']);

    $this->post('/api/webhooks/twilio/voice/recording', [
        'CallSid' => 'CA777',
        'RecordingUrl' => 'https://api.twilio.com/recording/123',
    ])->assertNoContent();

    expect($voiceCall->fresh()->recording_url)->toBe('https://api.twilio.com/recording/123');
});
