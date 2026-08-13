<?php

use App\Jobs\ProcessInboundInstagramMessage;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WebhookEvent;
use Database\Seeders\PipelineStagesSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

function inboundInstagramPayload(string $senderId = '1234567890', string $text = 'Hi there', string $mid = 'ig_mid_ABC123'): array
{
    return [
        'entry' => [
            [
                'id' => '999',
                'time' => now()->timestamp,
                'messaging' => [
                    [
                        'sender' => ['id' => $senderId],
                        'recipient' => ['id' => 'page-id'],
                        'timestamp' => now()->timestamp * 1000,
                        'message' => ['mid' => $mid, 'text' => $text],
                    ],
                ],
            ],
        ],
    ];
}

it('rejects instagram webhook verification with the wrong token', function () {
    config(['services.meta.verify_token' => 'correct-token']);

    $this->get('/api/webhooks/instagram?hub_mode=subscribe&hub_verify_token=wrong-token&hub_challenge=54321')
        ->assertForbidden();
});

it('accepts instagram webhook verification with the correct token and echoes the challenge', function () {
    config(['services.meta.verify_token' => 'correct-token']);

    $response = $this->get('/api/webhooks/instagram?hub_mode=subscribe&hub_verify_token=correct-token&hub_challenge=54321');

    $response->assertOk();
    expect($response->getContent())->toBe('54321');
});

it('rejects an inbound instagram webhook with an invalid signature', function () {
    config(['services.meta.app_secret' => 'test-secret']);

    $this->postJson('/api/webhooks/instagram', inboundInstagramPayload(), [
        'X-Hub-Signature-256' => 'sha256=invalid',
    ])->assertUnauthorized();

    expect(WebhookEvent::query()->count())->toBe(0);
});

it('accepts an inbound instagram webhook and dispatches processing', function () {
    Queue::fake();

    $this->postJson('/api/webhooks/instagram', inboundInstagramPayload())->assertNoContent();

    expect(WebhookEvent::query()->where('provider', 'instagram')->count())->toBe(1);
    Queue::assertPushed(ProcessInboundInstagramMessage::class);
});

it('creates a contact, conversation, and message when processing an inbound instagram message from an unknown sender', function () {
    $event = WebhookEvent::query()->create([
        'provider' => 'instagram',
        'payload' => inboundInstagramPayload('9998887777', 'Is this still available?', 'ig_mid_NEW1'),
    ]);

    (new ProcessInboundInstagramMessage($event->id))->handle();

    $contact = Contact::query()->where('handle', '@ig_9998887777')->first();
    expect($contact)->not->toBeNull();
    expect($contact->channel)->toBe('instagram');

    $conversation = Conversation::query()->where('contact_id', $contact->id)->first();
    expect($conversation)->not->toBeNull();
    expect($conversation->channel)->toBe('instagram');
    expect($conversation->unread_count)->toBe(1);

    $message = Message::query()->where('conversation_id', $conversation->id)->first();
    expect($message->direction)->toBe('inbound');
    expect($message->text)->toBe('Is this still available?');
    expect($message->external_message_id)->toBe('ig_mid_NEW1');

    expect($event->fresh()->processed_at)->not->toBeNull();
});

it('reuses the existing contact and conversation for a known instagram sender', function () {
    $contact = Contact::factory()->create(['handle' => '@ig_1112223333', 'channel' => 'instagram']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'instagram', 'unread_count' => 1]);

    $event = WebhookEvent::query()->create([
        'provider' => 'instagram',
        'payload' => inboundInstagramPayload('1112223333', 'Second message', 'ig_mid_NEW2'),
    ]);

    (new ProcessInboundInstagramMessage($event->id))->handle();

    expect(Contact::query()->where('handle', '@ig_1112223333')->count())->toBe(1);
    expect(Conversation::query()->where('contact_id', $contact->id)->count())->toBe(1);
    expect($conversation->fresh()->unread_count)->toBe(2);
});

it('updates message status from an instagram read receipt webhook', function () {
    $message = Message::factory()->create(['external_message_id' => 'ig_mid_STATUS1', 'status' => 'sent']);

    $event = WebhookEvent::query()->create([
        'provider' => 'instagram',
        'payload' => [
            'entry' => [
                [
                    'messaging' => [
                        [
                            'sender' => ['id' => '123'],
                            'read' => ['mid' => 'ig_mid_STATUS1'],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    (new ProcessInboundInstagramMessage($event->id))->handle();

    expect($message->fresh()->status)->toBe('read');
});
