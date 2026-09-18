<?php

use App\Events\ConversationCreated;
use App\Events\ConversationUpdated;
use App\Events\MessageReceived;
use App\Jobs\EvaluateAutomationFlows;
use App\Jobs\GenerateChatbotWhatsAppReply;
use App\Jobs\ProcessInboundWhatsAppMessage;
use App\Jobs\ProcessWhatsAppHistorySync;
use App\Models\ApiConnection;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WebhookEvent;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

function smbAppStateSyncPayload(string $phoneNumberId, array $contacts, array $messages): array
{
    return [
        'entry' => [
            [
                'changes' => [
                    [
                        'value' => [
                            'metadata' => ['phone_number_id' => $phoneNumberId],
                            'smb_app_state_sync' => [
                                'contacts' => $contacts,
                                'messages' => $messages,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

it('dispatches the history sync job instead of the inbound message job for smb_app_state_sync payloads', function () {
    Queue::fake();
    config(['services.meta.app_secret' => null]);

    $payload = smbAppStateSyncPayload('123456', [
        ['wa_id' => '15550001111', 'full_name' => 'Historic Contact'],
    ], []);

    $this->postJson('/api/webhooks/whatsapp', $payload)->assertNoContent();

    Queue::assertPushed(ProcessWhatsAppHistorySync::class);
    Queue::assertNotPushed(ProcessInboundWhatsAppMessage::class);
});

it('imports backfilled contacts and messages deduped by external message id', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp', 'access_token' => 'test-token', 'phone_number_id' => '123456']);

    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'payload' => smbAppStateSyncPayload('123456', [
            ['wa_id' => '15550001111', 'full_name' => 'Historic Contact'],
        ], [
            [
                'id' => 'wamid.HIST1',
                'wa_id' => '15550001111',
                'from' => '15550001111',
                'type' => 'text',
                'timestamp' => (string) now()->subDays(10)->timestamp,
                'text' => ['body' => 'Message from before linking'],
            ],
            [
                'id' => 'wamid.HIST2',
                'wa_id' => '15550001111',
                'from' => $connection->phone_number_id,
                'to' => '15550001111',
                'type' => 'text',
                'timestamp' => (string) now()->subDays(9)->timestamp,
                'text' => ['body' => 'Reply sent from the phone'],
            ],
        ]),
    ]);

    (new ProcessWhatsAppHistorySync($event->id))->handle();

    $contact = Contact::query()->where('handle', '15550001111')->first();
    expect($contact)->not->toBeNull();
    expect($contact->name)->toBe('Historic Contact');

    $conversation = Conversation::query()->where('contact_id', $contact->id)->first();
    expect($conversation)->not->toBeNull();
    expect($conversation->channel)->toBe('whatsapp');
    expect($conversation->api_connection_id)->toBe($connection->id);

    expect(Message::query()->where('conversation_id', $conversation->id)->count())->toBe(2);

    $inbound = Message::query()->where('external_message_id', 'wamid.HIST1')->first();
    expect($inbound->direction)->toBe('inbound');
    expect($inbound->origin)->toBeNull();

    $outbound = Message::query()->where('external_message_id', 'wamid.HIST2')->first();
    expect($outbound->direction)->toBe('outbound');
    expect($outbound->origin)->toBe('smb_app');

    expect($event->fresh()->processed_at)->not->toBeNull();
});

it('does not create duplicate messages when the same history sync message id is processed twice', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp', 'access_token' => 'test-token', 'phone_number_id' => '123456']);

    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'payload' => smbAppStateSyncPayload('123456', [
            ['wa_id' => '15550002222', 'full_name' => 'Repeat Contact'],
        ], [
            [
                'id' => 'wamid.DUPLICATE',
                'wa_id' => '15550002222',
                'from' => '15550002222',
                'type' => 'text',
                'timestamp' => (string) now()->subDays(5)->timestamp,
                'text' => ['body' => 'Only once please'],
            ],
        ]),
    ]);

    (new ProcessWhatsAppHistorySync($event->id))->handle();
    (new ProcessWhatsAppHistorySync($event->id))->handle();

    expect(Message::query()->where('external_message_id', 'wamid.DUPLICATE')->count())->toBe(1);
    expect(Contact::query()->where('handle', '15550002222')->count())->toBe(1);
});

it('does not fire message-received broadcasts or trigger automations for backfilled history', function () {
    Event::fake([MessageReceived::class, ConversationCreated::class]);
    Bus::fake([EvaluateAutomationFlows::class, GenerateChatbotWhatsAppReply::class]);

    ApiConnection::factory()->create(['channel' => 'whatsapp', 'access_token' => 'test-token', 'phone_number_id' => '123456']);

    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'payload' => smbAppStateSyncPayload('123456', [
            ['wa_id' => '15550003333', 'full_name' => 'Silent Contact'],
        ], [
            [
                'id' => 'wamid.SILENT1',
                'wa_id' => '15550003333',
                'from' => '15550003333',
                'type' => 'text',
                'timestamp' => (string) now()->subDays(3)->timestamp,
                'text' => ['body' => 'Should not trigger anything'],
            ],
        ]),
    ]);

    (new ProcessWhatsAppHistorySync($event->id))->handle();

    Event::assertNotDispatched(MessageReceived::class);
    Event::assertNotDispatched(ConversationCreated::class);
    Bus::assertNotDispatched(EvaluateAutomationFlows::class);
    Bus::assertNotDispatched(GenerateChatbotWhatsAppReply::class);
});

it('dispatches at most one ConversationUpdated event per conversation after a history sync batch', function () {
    Event::fake([ConversationUpdated::class]);

    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp', 'access_token' => 'test-token', 'phone_number_id' => '123456']);
    $contact = Contact::factory()->create(['handle' => '15550004444', 'channel' => 'whatsapp']);
    Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'api_connection_id' => $connection->id]);

    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'payload' => smbAppStateSyncPayload('123456', [
            ['wa_id' => '15550004444', 'full_name' => 'Batch Contact'],
        ], [
            [
                'id' => 'wamid.BATCH1',
                'wa_id' => '15550004444',
                'from' => '15550004444',
                'type' => 'text',
                'timestamp' => (string) now()->subDays(2)->timestamp,
                'text' => ['body' => 'First historical message'],
            ],
            [
                'id' => 'wamid.BATCH2',
                'wa_id' => '15550004444',
                'from' => '15550004444',
                'type' => 'text',
                'timestamp' => (string) now()->subDays(1)->timestamp,
                'text' => ['body' => 'Second historical message'],
            ],
        ]),
    ]);

    (new ProcessWhatsAppHistorySync($event->id))->handle();

    Event::assertDispatchedTimes(ConversationUpdated::class, 1);
});

it('skips history sync import when the phone_number_id does not match any connection', function () {
    ApiConnection::factory()->create(['channel' => 'whatsapp', 'access_token' => 'test-token', 'phone_number_id' => '111111']);
    ApiConnection::factory()->create(['channel' => 'whatsapp', 'access_token' => 'test-token', 'phone_number_id' => '222222']);

    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'payload' => smbAppStateSyncPayload('999999', [
            ['wa_id' => '15550005555', 'full_name' => 'Unmatched Contact'],
        ], []),
    ]);

    (new ProcessWhatsAppHistorySync($event->id))->handle();

    expect(Contact::query()->where('handle', '15550005555')->exists())->toBeFalse();
    expect($event->fresh()->processed_at)->not->toBeNull();
});
