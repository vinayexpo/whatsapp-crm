<?php

use App\Events\ConversationUpdated;
use App\Jobs\InitiateOutboundVoiceCall;
use App\Models\ApiConnection;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\VoiceAgent;
use App\Models\VoiceCall;
use Database\Seeders\PipelineStagesSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

it('places an outbound telephony call via the Twilio REST API when a connection is configured', function () {
    Event::fake([ConversationUpdated::class]);
    Http::fake([
        'api.twilio.com/*' => Http::response(['sid' => 'CA_OUTBOUND_1'], 201),
    ]);

    $company = Company::factory()->create();
    $connection = ApiConnection::factory()->create([
        'company_id' => $company->id,
        'channel' => 'voice',
        'twilio_account_sid' => 'AC123',
        'twilio_phone_number' => '+15557654321',
        'access_token' => 'auth-token-123',
    ]);
    $agent = VoiceAgent::factory()->create(['company_id' => $company->id, 'status' => 'active']);
    $contact = Contact::factory()->create(['company_id' => $company->id, 'handle' => '+15559998888', 'channel' => 'voice']);
    $campaign = Campaign::factory()->create(['company_id' => $company->id, 'voice_agent_id' => $agent->id]);
    $recipient = CampaignRecipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'status' => 'pending',
    ]);

    (new InitiateOutboundVoiceCall($recipient->id, $agent->id))->handle(app(\App\Services\Voice\VoiceCallDriverResolver::class));

    Http::assertSent(function ($request) use ($connection) {
        return str_contains($request->url(), "Accounts/{$connection->twilio_account_sid}/Calls.json")
            && $request['To'] === '+15559998888'
            && $request['From'] === '+15557654321';
    });

    $voiceCall = VoiceCall::query()->where('provider_call_sid', 'CA_OUTBOUND_1')->first();
    expect($voiceCall)->not->toBeNull();
    expect($voiceCall->company_id)->toBe($company->id);
    expect($voiceCall->direction)->toBe('outbound');
    expect($voiceCall->medium)->toBe('telephony');
    expect($voiceCall->status)->toBe('ringing');
    expect($voiceCall->campaign_recipient_id)->toBe($recipient->id);

    $conversation = Conversation::query()->where('contact_id', $contact->id)->where('channel', 'voice')->first();
    expect($conversation)->not->toBeNull();
    expect($conversation->company_id)->toBe($company->id);
    expect($voiceCall->conversation_id)->toBe($conversation->id);

    expect($recipient->fresh()->status)->toBe('sent');

    Event::assertDispatched(ConversationUpdated::class);
});

it('marks the call failed and needing followup when no Twilio connection is configured', function () {
    Event::fake([ConversationUpdated::class]);

    $company = Company::factory()->create();
    $agent = VoiceAgent::factory()->create(['company_id' => $company->id, 'status' => 'active']);
    $contact = Contact::factory()->create(['company_id' => $company->id, 'channel' => 'voice']);
    $campaign = Campaign::factory()->create(['company_id' => $company->id, 'voice_agent_id' => $agent->id]);
    $recipient = CampaignRecipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'status' => 'pending',
    ]);

    (new InitiateOutboundVoiceCall($recipient->id, $agent->id))->handle(app(\App\Services\Voice\VoiceCallDriverResolver::class));

    $voiceCall = VoiceCall::query()->where('campaign_recipient_id', $recipient->id)->first();
    expect($voiceCall)->not->toBeNull();
    expect($voiceCall->status)->toBe('failed');
    expect($voiceCall->needs_human_followup)->toBeTrue();

    expect($recipient->fresh()->status)->toBe('failed');
});

it('does nothing when the campaign recipient is not pending', function () {
    $company = Company::factory()->create();
    $agent = VoiceAgent::factory()->create(['company_id' => $company->id]);
    $contact = Contact::factory()->create(['company_id' => $company->id, 'channel' => 'voice']);
    $campaign = Campaign::factory()->create(['company_id' => $company->id, 'voice_agent_id' => $agent->id]);
    $recipient = CampaignRecipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'status' => 'sent',
    ]);

    (new InitiateOutboundVoiceCall($recipient->id, $agent->id))->handle(app(\App\Services\Voice\VoiceCallDriverResolver::class));

    expect(VoiceCall::query()->count())->toBe(0);
});
