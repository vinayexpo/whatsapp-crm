<?php

use App\Jobs\ProcessInboundWhatsAppMessage;
use App\Models\ApiConnection;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WebhookEvent;
use App\Models\WhatsappCall;
use Database\Seeders\PipelineStagesSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

function inboundWhatsAppPayload(string $waId = '15551234567', string $text = 'Hello there', string $messageId = 'wamid.ABC123'): array
{
    return [
        'entry' => [
            [
                'changes' => [
                    [
                        'value' => [
                            'contacts' => [
                                ['wa_id' => $waId, 'profile' => ['name' => 'Jane Doe']],
                            ],
                            'messages' => [
                                [
                                    'from' => $waId,
                                    'id' => $messageId,
                                    'timestamp' => (string) now()->timestamp,
                                    'text' => ['body' => $text],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

it('rejects webhook verification with the wrong token', function () {
    config(['services.meta.verify_token' => 'correct-token']);

    $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong-token&hub_challenge=12345')
        ->assertForbidden();
});

it('accepts webhook verification with the correct token and echoes the challenge', function () {
    config(['services.meta.verify_token' => 'correct-token']);

    $response = $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=correct-token&hub_challenge=12345');

    $response->assertOk();
    expect($response->getContent())->toBe('12345');
});

it('rejects an inbound webhook with an invalid signature', function () {
    config(['services.meta.app_secret' => 'test-secret']);

    $this->postJson('/api/webhooks/whatsapp', inboundWhatsAppPayload(), [
        'X-Hub-Signature-256' => 'sha256=invalid',
    ])->assertUnauthorized();

    expect(WebhookEvent::query()->count())->toBe(0);
});

it('accepts an inbound webhook with a valid signature and dispatches processing', function () {
    Queue::fake();
    config(['services.meta.app_secret' => 'test-secret']);

    $payload = inboundWhatsAppPayload();
    $body = json_encode($payload);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'test-secret');

    $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X-Hub-Signature-256' => $signature,
    ], $body)->assertNoContent();

    expect(WebhookEvent::query()->count())->toBe(1);
    Queue::assertPushed(ProcessInboundWhatsAppMessage::class);
});

it('accepts an inbound webhook without a signature when no app secret is configured', function () {
    Queue::fake();
    config(['services.meta.app_secret' => null]);

    $this->postJson('/api/webhooks/whatsapp', inboundWhatsAppPayload())->assertNoContent();

    expect(WebhookEvent::query()->count())->toBe(1);
    Queue::assertPushed(ProcessInboundWhatsAppMessage::class);
});

it('creates a contact, conversation, and message when processing an inbound message from an unknown number', function () {
    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'payload' => inboundWhatsAppPayload('15559876543', 'Is this available?', 'wamid.NEW1'),
    ]);

    (new ProcessInboundWhatsAppMessage($event->id))->handle();

    $contact = Contact::query()->where('handle', '15559876543')->first();
    expect($contact)->not->toBeNull();
    expect($contact->name)->toBe('Jane Doe');

    $conversation = Conversation::query()->where('contact_id', $contact->id)->first();
    expect($conversation)->not->toBeNull();
    expect($conversation->channel)->toBe('whatsapp');
    expect($conversation->unread_count)->toBe(1);

    $message = Message::query()->where('conversation_id', $conversation->id)->first();
    expect($message->direction)->toBe('inbound');
    expect($message->text)->toBe('Is this available?');
    expect($message->external_message_id)->toBe('wamid.NEW1');

    expect($event->fresh()->processed_at)->not->toBeNull();
});

it('downloads and stores inbound media as a message attachment', function () {
    Storage::fake('public');
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp', 'access_token' => 'test-token']);

    Http::fake([
        'https://graph.facebook.com/v20.0/media123' => Http::response([
            'url' => 'https://lookaside.fbsbx.com/whatsapp_business/attachments/media123',
        ]),
        'https://lookaside.fbsbx.com/*' => Http::response('fake-image-bytes'),
    ]);

    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'payload' => [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'contacts' => [
                                    ['wa_id' => '15557778888', 'profile' => ['name' => 'Photo Sender']],
                                ],
                                'messages' => [
                                    [
                                        'from' => '15557778888',
                                        'id' => 'wamid.MEDIA1',
                                        'timestamp' => (string) now()->timestamp,
                                        'type' => 'image',
                                        'image' => [
                                            'id' => 'media123',
                                            'mime_type' => 'image/jpeg',
                                            'caption' => 'Check this out',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    (new ProcessInboundWhatsAppMessage($event->id))->handle();

    $message = Message::query()->where('external_message_id', 'wamid.MEDIA1')->first();

    expect($message)->not->toBeNull();
    expect($message->attachment_type)->toBe('image');
    expect($message->attachment_url)->not->toBeNull();
    expect($message->text)->toBe('Check this out');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://graph.facebook.com/v20.0/media123');
});

it('reuses the existing contact and conversation for a known number', function () {
    $contact = Contact::factory()->create(['handle' => '15551112222', 'channel' => 'whatsapp']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'unread_count' => 2]);

    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'payload' => inboundWhatsAppPayload('15551112222', 'Second message', 'wamid.NEW2'),
    ]);

    (new ProcessInboundWhatsAppMessage($event->id))->handle();

    expect(Contact::query()->where('handle', '15551112222')->count())->toBe(1);
    expect(Conversation::query()->where('contact_id', $contact->id)->count())->toBe(1);
    expect($conversation->fresh()->unread_count)->toBe(3);
});

it('routes a call payload arriving at the message webhook URL to the call handler instead of dropping it', function () {
    Queue::fake();
    config(['services.meta.app_secret' => null]);

    $whatsappCall = WhatsappCall::factory()->create([
        'meta_call_id' => 'wacid.routed1',
        'status' => 'ringing',
    ]);

    $this->postJson('/api/webhooks/whatsapp', [
        'entry' => [
            [
                'changes' => [
                    [
                        'value' => [
                            'calls' => [
                                ['id' => 'wacid.routed1', 'status' => 'missed'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ])->assertNoContent();

    expect($whatsappCall->fresh()->status)->toBe('missed');
    Queue::assertNotPushed(ProcessInboundWhatsAppMessage::class);
});

it('updates message status from an inbound status webhook', function () {
    $message = Message::factory()->create(['external_message_id' => 'wamid.STATUS1', 'status' => 'sent']);

    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'payload' => [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'statuses' => [
                                    ['id' => 'wamid.STATUS1', 'status' => 'delivered'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    (new ProcessInboundWhatsAppMessage($event->id))->handle();

    expect($message->fresh()->status)->toBe('delivered');
});

it('marks a call permission request failed from an async status webhook with no matching message', function () {
    $whatsappCall = WhatsappCall::factory()->create([
        'permission_request_message_id' => 'wamid.PERMREQ1',
        'permission_request_status' => 'sent',
    ]);

    $event = WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'payload' => [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'statuses' => [
                                    [
                                        'id' => 'wamid.PERMREQ1',
                                        'status' => 'failed',
                                        'errors' => [
                                            [
                                                'message' => 'Re-engagement message',
                                                'error_data' => ['details' => 'Message failed to send because more than 24 hours have passed since the customer last replied to this number.'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    (new ProcessInboundWhatsAppMessage($event->id))->handle();

    $whatsappCall->refresh();
    expect($whatsappCall->permission_request_status)->toBe('failed');
    expect($whatsappCall->permission_request_failure_reason)->toContain('24 hours');
});
