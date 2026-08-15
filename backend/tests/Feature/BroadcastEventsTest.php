<?php

use App\Events\ConversationCreated;
use App\Events\ConversationUpdated;
use App\Events\MessageReceived;
use App\Events\MessageStatusUpdated;
use App\Jobs\ProcessInboundWhatsAppMessage;
use App\Jobs\SimulateMessageDeliveryTick;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\WebhookEvent;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

it('broadcasts MessageReceived and ConversationCreated when an inbound whatsapp message starts a new conversation', function () {
    Event::fake([MessageReceived::class, ConversationCreated::class]);

    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'payload' => [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'contacts' => [['wa_id' => '15550001111', 'profile' => ['name' => 'Broadcast Tester']]],
                        'messages' => [[
                            'from' => '15550001111',
                            'id' => 'wamid.BCAST1',
                            'timestamp' => (string) now()->timestamp,
                            'text' => ['body' => 'Hello'],
                        ]],
                    ],
                ]],
            ]],
        ],
    ]);

    (new ProcessInboundWhatsAppMessage($event->id))->handle();

    Event::assertDispatched(MessageReceived::class);
    Event::assertDispatched(ConversationCreated::class);
});

it('broadcasts ConversationUpdated (not ConversationCreated) when an inbound whatsapp message reuses an existing conversation', function () {
    $company = \App\Models\Company::factory()->create();
    $contact = Contact::factory()->create(['company_id' => $company->id, 'channel' => 'whatsapp', 'handle' => '15550002222']);
    Conversation::factory()->create(['company_id' => $company->id, 'contact_id' => $contact->id, 'channel' => 'whatsapp']);
    \App\Models\ApiConnection::factory()->create(['company_id' => $company->id, 'channel' => 'whatsapp']);

    Event::fake([ConversationCreated::class, ConversationUpdated::class]);

    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'payload' => [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'contacts' => [['wa_id' => '15550002222', 'profile' => ['name' => 'Broadcast Tester']]],
                        'messages' => [[
                            'from' => '15550002222',
                            'id' => 'wamid.BCAST2',
                            'timestamp' => (string) now()->timestamp,
                            'text' => ['body' => 'Hello again'],
                        ]],
                    ],
                ]],
            ]],
        ],
    ]);

    (new ProcessInboundWhatsAppMessage($event->id))->handle();

    Event::assertDispatched(ConversationUpdated::class);
    Event::assertNotDispatched(ConversationCreated::class);
});

it('broadcasts MessageStatusUpdated when a status webhook updates a message', function () {
    Event::fake([MessageStatusUpdated::class]);

    $message = Message::factory()->create(['external_message_id' => 'wamid.STATUSB', 'status' => 'sent']);

    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'payload' => [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [['id' => 'wamid.STATUSB', 'status' => 'delivered']],
                    ],
                ]],
            ]],
        ],
    ]);

    (new ProcessInboundWhatsAppMessage($event->id))->handle();

    Event::assertDispatched(MessageStatusUpdated::class, function ($e) use ($message) {
        return $e->message->id === $message->id;
    });
});

it('broadcasts MessageStatusUpdated during a simulated delivery tick', function () {
    Event::fake([MessageStatusUpdated::class]);

    $message = Message::factory()->create(['status' => 'sent']);

    (new SimulateMessageDeliveryTick($message->id, 'delivered'))->handle();

    expect($message->fresh()->status)->toBe('delivered');
    Event::assertDispatched(MessageStatusUpdated::class);
});

it('broadcasts ConversationUpdated when an agent sends an outbound message', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Event::fake([ConversationUpdated::class]);

    $agent = User::factory()->create();
    $agent->assignRole('agent');
    $contact = Contact::factory()->create();
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);

    $this->actingAs($agent)
        ->postJson("/api/v1/conversations/{$conversation->uuid}/messages", ['text' => 'On my way'])
        ->assertCreated();

    Event::assertDispatched(ConversationUpdated::class);
});
