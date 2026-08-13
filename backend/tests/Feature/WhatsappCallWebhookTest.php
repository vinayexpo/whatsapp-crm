<?php

use App\Jobs\ProcessInboundWhatsappCall;
use App\Jobs\ProcessWhatsappCallCompletion;
use App\Models\ApiConnection;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Models\WhatsappCall;
use App\Models\WhatsappCallFlow;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

function whatsappCallPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'entry' => [
            [
                'changes' => [
                    [
                        'value' => [
                            'metadata' => ['phone_number_id' => '1234567890'],
                            'calls' => [
                                ['id' => 'wacid.999', 'from' => '+15559998888', 'status' => 'ringing'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ], $overrides);
}

it('rejects an inbound call webhook with an invalid signature', function () {
    config(['services.meta.app_secret' => 'test-secret']);

    $this->postJson('/api/webhooks/whatsapp-call', whatsappCallPayload(), [
        'X-Hub-Signature-256' => 'sha256=invalid',
    ])->assertUnauthorized();

    expect(WebhookEvent::query()->count())->toBe(0);
});

it('accepts an inbound call webhook without a signature when no app secret is configured', function () {
    Queue::fake();
    config(['services.meta.app_secret' => null]);

    $response = $this->postJson('/api/webhooks/whatsapp-call', whatsappCallPayload());

    $response->assertNoContent();
    expect(WebhookEvent::query()->count())->toBe(1);
    Queue::assertPushed(ProcessInboundWhatsappCall::class);
});

it('creates a contact, conversation, and whatsapp call when processing an inbound call', function () {
    $company = Company::factory()->create();
    $connection = ApiConnection::factory()->create([
        'company_id' => $company->id,
        'channel' => 'whatsapp',
        'phone_number_id' => '1234567890',
        'calling_enabled' => true,
    ]);
    $flow = WhatsappCallFlow::factory()->create([
        'company_id' => $company->id,
        'api_connection_id' => $connection->id,
        'status' => 'active',
    ]);

    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp_call',
        'payload' => whatsappCallPayload(),
    ]);

    (new ProcessInboundWhatsappCall($event->id))->handle();

    $contact = Contact::query()->where('handle', '+15559998888')->first();
    expect($contact)->not->toBeNull();
    expect($contact->company_id)->toBe($company->id);

    $conversation = Conversation::query()->where('contact_id', $contact->id)->where('channel', 'whatsapp_call')->first();
    expect($conversation)->not->toBeNull();

    $whatsappCall = WhatsappCall::query()->where('meta_call_id', 'wacid.999')->first();
    expect($whatsappCall)->not->toBeNull();
    expect($whatsappCall->direction)->toBe('inbound');
    expect($whatsappCall->status)->toBe('ringing');
    expect($whatsappCall->conversation_id)->toBe($conversation->id);
    expect($whatsappCall->whatsapp_call_flow_id)->toBe($flow->id);

    expect($event->fresh()->processed_at)->not->toBeNull();
});

it('notifies whatsapp-calling managers with whatsapp_call_ringing when an inbound call arrives', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $company = Company::factory()->create();
    $manager = User::factory()->create(['company_id' => $company->id]);
    $manager->assignRole('manager');

    $connection = ApiConnection::factory()->create([
        'company_id' => $company->id,
        'channel' => 'whatsapp',
        'phone_number_id' => '1234567890',
        'calling_enabled' => true,
    ]);
    WhatsappCallFlow::factory()->create([
        'company_id' => $company->id,
        'api_connection_id' => $connection->id,
        'status' => 'active',
    ]);

    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp_call',
        'payload' => whatsappCallPayload(),
    ]);

    (new ProcessInboundWhatsappCall($event->id))->handle();

    $this->assertDatabaseHas('notifications', [
        'user_id' => $manager->id,
        'type' => 'whatsapp_call_ringing',
    ]);
});

it('does nothing when no calling-enabled connection matches the phone number id', function () {
    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp_call',
        'payload' => whatsappCallPayload(),
    ]);

    (new ProcessInboundWhatsappCall($event->id))->handle();

    expect(WhatsappCall::query()->count())->toBe(0);
});

it('does nothing when the matching connection has calling disabled', function () {
    $company = Company::factory()->create();
    ApiConnection::factory()->create([
        'company_id' => $company->id,
        'channel' => 'whatsapp',
        'phone_number_id' => '1234567890',
        'calling_enabled' => false,
    ]);

    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp_call',
        'payload' => whatsappCallPayload(),
    ]);

    (new ProcessInboundWhatsappCall($event->id))->handle();

    expect(WhatsappCall::query()->count())->toBe(0);
});

it('updates whatsapp call status from a status callback and flags followup on missed', function () {
    Queue::fake();
    config(['services.meta.app_secret' => null]);

    $whatsappCall = WhatsappCall::factory()->create([
        'meta_call_id' => 'wacid.555',
        'status' => 'ringing',
    ]);

    $this->postJson('/api/webhooks/whatsapp-call', whatsappCallPayload([
        'entry' => [['changes' => [['value' => ['calls' => [['id' => 'wacid.555', 'status' => 'missed']]]]]]],
    ]))->assertNoContent();

    $whatsappCall->refresh();
    expect($whatsappCall->status)->toBe('missed');
    expect($whatsappCall->needs_human_followup)->toBeTrue();
    expect($whatsappCall->ended_at)->not->toBeNull();

    Queue::assertPushed(ProcessWhatsappCallCompletion::class);
});

it('marks a call in_progress and sets started_at when accepted', function () {
    Queue::fake();
    config(['services.meta.app_secret' => null]);

    $whatsappCall = WhatsappCall::factory()->create([
        'meta_call_id' => 'wacid.222',
        'status' => 'ringing',
        'started_at' => null,
    ]);

    $this->postJson('/api/webhooks/whatsapp-call', whatsappCallPayload([
        'entry' => [['changes' => [['value' => ['calls' => [['id' => 'wacid.222', 'status' => 'accepted']]]]]]],
    ]))->assertNoContent();

    $whatsappCall->refresh();
    expect($whatsappCall->status)->toBe('in_progress');
    expect($whatsappCall->started_at)->not->toBeNull();
});

it('advances the flow and records a collected variable on an action request', function () {
    $flow = WhatsappCallFlow::factory()->create();
    $whatsappCall = WhatsappCall::factory()->create([
        'whatsapp_call_flow_id' => $flow->id,
        'meta_call_id' => 'wacid.333',
        'status' => 'in_progress',
        'collected_variables' => [],
    ]);

    $response = $this->postJson('/api/webhooks/whatsapp-call/action', [
        'call_id' => 'wacid.333',
        'speech' => '$5,000 to $10,000',
    ]);

    $response->assertOk()->assertJsonPath('action', 'prompt');
    expect($whatsappCall->fresh()->collected_variables)->toBe(['budget' => '$5,000 to $10,000']);
});

it('terminates the call and dispatches completion on reaching an end_call node', function () {
    Queue::fake();

    $flow = WhatsappCallFlow::factory()->create();
    $whatsappCall = WhatsappCall::factory()->create([
        'whatsapp_call_flow_id' => $flow->id,
        'meta_call_id' => 'wacid.444',
        'status' => 'in_progress',
        'collected_variables' => ['budget' => '$5,000', 'timeline' => 'next month'],
    ]);

    $response = $this->postJson('/api/webhooks/whatsapp-call/action', [
        'call_id' => 'wacid.444',
        'speech' => '',
    ]);

    $response->assertOk()->assertJsonPath('action', 'terminate');
    Queue::assertPushed(ProcessWhatsappCallCompletion::class);
});

it('returns 404 on an action request for an unknown call id', function () {
    $this->postJson('/api/webhooks/whatsapp-call/action', [
        'call_id' => 'does-not-exist',
    ])->assertNotFound();
});
