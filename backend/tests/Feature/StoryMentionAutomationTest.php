<?php

use App\Jobs\EvaluateAutomationFlows;
use App\Jobs\ProcessInboundInstagramMessage;
use App\Models\ApiConnection;
use App\Models\AutomationFlow;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\InstagramStoryMention;
use App\Models\Message;
use App\Models\WebhookEvent;
use Database\Seeders\PipelineStagesSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

function inboundInstagramStoryMentionPayload(string $mentionId = 'ig_mention_1', string $senderId = '1234567890'): array
{
    return [
        'entry' => [
            [
                'id' => '999',
                'time' => now()->timestamp,
                'messaging' => [
                    [
                        'sender' => ['id' => $senderId],
                        'recipient' => ['id' => '999'],
                        'timestamp' => now()->timestamp * 1000,
                        'message' => [
                            'mid' => $mentionId,
                            'attachments' => [
                                ['type' => 'story_mention', 'payload' => ['url' => 'https://instagram.com/story/media123']],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

it('creates a contact and instagram story mention when processing an inbound webhook event', function () {
    $event = WebhookEvent::query()->create([
        'provider' => 'instagram',
        'payload' => inboundInstagramStoryMentionPayload('ig_mention_new', '5551112222'),
    ]);

    (new ProcessInboundInstagramMessage($event->id))->handle();

    $contact = Contact::query()->where('handle', '@ig_5551112222')->first();
    expect($contact)->not->toBeNull();

    $mention = InstagramStoryMention::query()->where('mention_id', 'ig_mention_new')->first();
    expect($mention)->not->toBeNull();
    expect($mention->contact_id)->toBe($contact->id);
    expect($mention->replied_at)->toBeNull();

    expect($event->fresh()->processed_at)->not->toBeNull();
});

it('does not create a duplicate instagram story mention for a repeated webhook delivery', function () {
    $payload = inboundInstagramStoryMentionPayload('ig_mention_dupe', '1231231234');

    $first = WebhookEvent::query()->create(['provider' => 'instagram', 'payload' => $payload]);
    (new ProcessInboundInstagramMessage($first->id))->handle();

    $second = WebhookEvent::query()->create(['provider' => 'instagram', 'payload' => $payload]);
    (new ProcessInboundInstagramMessage($second->id))->handle();

    expect(InstagramStoryMention::query()->where('mention_id', 'ig_mention_dupe')->count())->toBe(1);
});

it('does not route a story mention through the regular inbound message path', function () {
    $event = WebhookEvent::query()->create([
        'provider' => 'instagram',
        'payload' => inboundInstagramStoryMentionPayload('ig_mention_no_dm', '4445556666'),
    ]);

    (new ProcessInboundInstagramMessage($event->id))->handle();

    $contact = Contact::query()->where('handle', '@ig_4445556666')->first();
    expect(Conversation::query()->where('contact_id', $contact->id)->exists())->toBeFalse();
});

it('matches a story-mention flow and sends an instagram dm', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'ig_dm_mention_1']]], 200),
    ]);

    $company = Company::factory()->create();
    $contact = Contact::factory()->create(['company_id' => $company->id, 'handle' => '@ig_444555666', 'channel' => 'instagram']);
    $mention = InstagramStoryMention::factory()->create([
        'company_id' => $company->id,
        'contact_id' => $contact->id,
    ]);

    ApiConnection::factory()->connected()->create([
        'company_id' => $company->id,
        'channel' => 'instagram',
        'instagram_account_id' => 'ig-account-1',
    ]);

    $flow = AutomationFlow::factory()->create([
        'status' => 'active',
        'trigger' => ['type' => 'story-mention', 'channel' => 'instagram'],
        'actions' => [['id' => 'act-1', 'type' => 'send-instagram-dm', 'value' => 'Thanks for the shoutout!']],
    ]);

    (new EvaluateAutomationFlows(null, null, false, null, $mention->id))->handle();

    $conversation = Conversation::query()->where('contact_id', $contact->id)->where('channel', 'instagram')->first();
    expect($conversation)->not->toBeNull();

    $message = Message::query()->where('conversation_id', $conversation->id)->where('direction', 'outbound')->first();
    expect($message)->not->toBeNull();
    expect($message->text)->toBe('Thanks for the shoutout!');
    expect($message->external_message_id)->toBe('ig_dm_mention_1');

    expect($mention->fresh()->replied_at)->not->toBeNull();
    expect($flow->fresh()->triggered_count)->toBe(1);
});

it('does not match a story-mention flow scoped to a different channel', function () {
    $company = Company::factory()->create();
    $contact = Contact::factory()->create(['company_id' => $company->id, 'channel' => 'instagram']);
    $mention = InstagramStoryMention::factory()->create([
        'company_id' => $company->id,
        'contact_id' => $contact->id,
    ]);

    AutomationFlow::factory()->create([
        'status' => 'active',
        'trigger' => ['type' => 'story-mention', 'channel' => 'whatsapp'],
        'actions' => [['id' => 'act-1', 'type' => 'send-instagram-dm', 'value' => 'Thanks for the shoutout!']],
    ]);

    (new EvaluateAutomationFlows(null, null, false, null, $mention->id))->handle();

    expect(Conversation::query()->where('contact_id', $contact->id)->exists())->toBeFalse();
    expect($mention->fresh()->replied_at)->toBeNull();
});
