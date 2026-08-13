<?php

use App\Jobs\ProcessInboundInstagramMessage;
use App\Models\Contact;
use App\Models\InstagramComment;
use App\Models\WebhookEvent;
use Database\Seeders\PipelineStagesSeeder;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

function inboundInstagramCommentPayload(string $commentId = 'ig_comment_1', string $senderId = '1234567890', string $text = 'Love this, price?'): array
{
    return [
        'entry' => [
            [
                'id' => '999',
                'time' => now()->timestamp,
                'changes' => [
                    [
                        'field' => 'comments',
                        'value' => [
                            'id' => $commentId,
                            'text' => $text,
                            'from' => ['id' => $senderId, 'username' => 'shopper'],
                            'media' => ['id' => 'ig_media_1'],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

it('creates a contact and instagram comment when processing an inbound comment webhook event', function () {
    $event = WebhookEvent::query()->create([
        'provider' => 'instagram',
        'payload' => inboundInstagramCommentPayload('ig_comment_new', '5551112222', 'What is the price?'),
    ]);

    (new ProcessInboundInstagramMessage($event->id))->handle();

    $contact = Contact::query()->where('handle', '@ig_5551112222')->first();
    expect($contact)->not->toBeNull();
    expect($contact->channel)->toBe('instagram');

    $comment = InstagramComment::query()->where('comment_id', 'ig_comment_new')->first();
    expect($comment)->not->toBeNull();
    expect($comment->contact_id)->toBe($contact->id);
    expect($comment->text)->toBe('What is the price?');
    expect($comment->media_id)->toBe('ig_media_1');
    expect($comment->replied_at)->toBeNull();

    expect($event->fresh()->processed_at)->not->toBeNull();
});

it('reuses the existing contact for a comment from a known instagram sender', function () {
    $contact = Contact::factory()->create(['handle' => '@ig_7778889999', 'channel' => 'instagram']);

    $event = WebhookEvent::query()->create([
        'provider' => 'instagram',
        'payload' => inboundInstagramCommentPayload('ig_comment_known', '7778889999', 'Nice!'),
    ]);

    (new ProcessInboundInstagramMessage($event->id))->handle();

    expect(Contact::query()->where('handle', '@ig_7778889999')->count())->toBe(1);
    expect(InstagramComment::query()->where('comment_id', 'ig_comment_known')->where('contact_id', $contact->id)->exists())->toBeTrue();
});

it('does not create a duplicate instagram comment for a repeated webhook delivery', function () {
    $payload = inboundInstagramCommentPayload('ig_comment_dupe', '1231231234', 'Great post');

    $first = WebhookEvent::query()->create(['provider' => 'instagram', 'payload' => $payload]);
    (new ProcessInboundInstagramMessage($first->id))->handle();

    $second = WebhookEvent::query()->create(['provider' => 'instagram', 'payload' => $payload]);
    (new ProcessInboundInstagramMessage($second->id))->handle();

    expect(InstagramComment::query()->where('comment_id', 'ig_comment_dupe')->count())->toBe(1);
});
