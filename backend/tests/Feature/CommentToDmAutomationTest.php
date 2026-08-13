<?php

use App\Jobs\EvaluateAutomationFlows;
use App\Models\ApiConnection;
use App\Models\AutomationFlow;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\InstagramComment;
use App\Models\Message;
use Database\Seeders\PipelineStagesSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

function createInstagramComment(string $text = 'What is the price?'): array
{
    $company = Company::factory()->create();
    $contact = Contact::factory()->create(['company_id' => $company->id, 'handle' => '@ig_444555666', 'channel' => 'instagram']);
    $comment = InstagramComment::factory()->create([
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'text' => $text,
    ]);

    return [$company, $contact, $comment];
}

it('matches a comment-received flow with a keyword filter and sends an instagram dm', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'ig_dm_1']]], 200),
    ]);

    [$company, $contact, $comment] = createInstagramComment('What is the price?');

    ApiConnection::factory()->connected()->create([
        'company_id' => $company->id,
        'channel' => 'instagram',
        'instagram_account_id' => 'ig-account-1',
    ]);

    $flow = AutomationFlow::factory()->create([
        'status' => 'active',
        'trigger' => ['type' => 'comment-received', 'channel' => 'instagram', 'keyword' => 'price'],
        'actions' => [['id' => 'act-1', 'type' => 'send-instagram-dm', 'value' => 'Thanks for asking! Here is our price list.']],
    ]);

    (new EvaluateAutomationFlows(null, null, false, $comment->id))->handle();

    $conversation = Conversation::query()->where('contact_id', $contact->id)->where('channel', 'instagram')->first();
    expect($conversation)->not->toBeNull();

    $message = Message::query()->where('conversation_id', $conversation->id)->where('direction', 'outbound')->first();
    expect($message)->not->toBeNull();
    expect($message->text)->toBe('Thanks for asking! Here is our price list.');
    expect($message->external_message_id)->toBe('ig_dm_1');

    expect($comment->fresh()->replied_at)->not->toBeNull();
    expect($flow->fresh()->triggered_count)->toBe(1);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'graph.facebook.com')
            && $request['to'] === '444555666';
    });
});

it('does not trigger a comment-received flow when the keyword is absent', function () {
    [$company, $contact, $comment] = createInstagramComment('Love this photo!');

    AutomationFlow::factory()->create([
        'status' => 'active',
        'trigger' => ['type' => 'comment-received', 'channel' => 'instagram', 'keyword' => 'price'],
        'actions' => [['id' => 'act-1', 'type' => 'send-instagram-dm', 'value' => 'Here is our price list.']],
    ]);

    (new EvaluateAutomationFlows(null, null, false, $comment->id))->handle();

    expect(Conversation::query()->where('contact_id', $contact->id)->exists())->toBeFalse();
    expect($comment->fresh()->replied_at)->toBeNull();
});

it('matches a comment-received flow with no keyword against any comment text', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'ig_dm_2']]], 200),
    ]);

    [$company, $contact, $comment] = createInstagramComment('Random comment');

    AutomationFlow::factory()->create([
        'status' => 'active',
        'trigger' => ['type' => 'comment-received', 'channel' => 'instagram'],
        'actions' => [['id' => 'act-1', 'type' => 'send-instagram-dm', 'value' => 'Thanks for commenting!']],
    ]);

    (new EvaluateAutomationFlows(null, null, false, $comment->id))->handle();

    expect($comment->fresh()->replied_at)->not->toBeNull();
});
