<?php

use App\Jobs\GenerateChatbotInstagramReply;
use App\Models\ApiConnection;
use App\Models\Chatbot;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Database\Seeders\PipelineStagesSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

function createInstagramConversation(): array
{
    $company = Company::factory()->create();
    $contact = Contact::factory()->create([
        'company_id' => $company->id,
        'handle' => '@ig_555444333',
        'channel' => 'instagram',
    ]);
    $conversation = Conversation::factory()->create([
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'channel' => 'instagram',
    ]);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'text' => 'What are your hours?',
    ]);

    return [$company, $conversation, $message];
}

it('generates and sends an ai chatbot reply for an inbound instagram dm', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'ig_reply_1']]], 200),
    ]);

    [$company, $conversation, $message] = createInstagramConversation();

    Chatbot::factory()->create([
        'company_id' => $company->id,
        'status' => 'active',
        'channels' => ['website', 'instagram'],
    ]);

    ApiConnection::factory()->connected()->create([
        'company_id' => $company->id,
        'channel' => 'instagram',
        'instagram_account_id' => 'ig-account-1',
    ]);

    (new GenerateChatbotInstagramReply($conversation->id, $message->id))->handle(
        app(\App\Services\Chatbot\ChatbotReplyServiceInterface::class),
        app(\App\Services\Messaging\MessagingDriverResolver::class),
    );

    $reply = Message::query()->where('conversation_id', $conversation->id)->where('direction', 'outbound')->first();

    expect($reply)->not->toBeNull();
    expect($reply->text)->toBe('Fake reply to: What are your hours?');
    expect($reply->external_message_id)->toBe('ig_reply_1');
    expect($conversation->fresh()->chatbot_id)->not->toBeNull();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'graph.facebook.com')
            && str_contains($request->url(), 'ig-account-1')
            && $request['to'] === '555444333';
    });
});

it('does not generate a reply when no instagram-enabled chatbot is configured', function () {
    [$company, $conversation, $message] = createInstagramConversation();

    Chatbot::factory()->create([
        'company_id' => $company->id,
        'status' => 'active',
        'channels' => ['website'],
    ]);

    (new GenerateChatbotInstagramReply($conversation->id, $message->id))->handle(
        app(\App\Services\Chatbot\ChatbotReplyServiceInterface::class),
        app(\App\Services\Messaging\MessagingDriverResolver::class),
    );

    expect(Message::query()->where('conversation_id', $conversation->id)->where('direction', 'outbound')->exists())->toBeFalse();
});

it('skips the chatbot reply when an automation flow already replied', function () {
    [$company, $conversation, $message] = createInstagramConversation();

    Chatbot::factory()->create([
        'company_id' => $company->id,
        'status' => 'active',
        'channels' => ['instagram'],
    ]);

    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'outbound',
        'text' => 'Automation already replied',
    ]);

    (new GenerateChatbotInstagramReply($conversation->id, $message->id))->handle(
        app(\App\Services\Chatbot\ChatbotReplyServiceInterface::class),
        app(\App\Services\Messaging\MessagingDriverResolver::class),
    );

    expect(Message::query()->where('conversation_id', $conversation->id)->where('direction', 'outbound')->count())->toBe(1);
});

it('sends the raw instagram sender id, not the @ig_ prefixed handle, as the graph api recipient', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'ig_out_1']]], 200),
    ]);

    $company = Company::factory()->create();
    $contact = Contact::factory()->create([
        'company_id' => $company->id,
        'handle' => '@ig_9988776655',
        'channel' => 'instagram',
    ]);
    $conversation = Conversation::factory()->create([
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'channel' => 'instagram',
    ]);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'outbound',
        'text' => 'Hello from us',
    ]);

    $connection = ApiConnection::factory()->connected()->create([
        'company_id' => $company->id,
        'channel' => 'instagram',
        'instagram_account_id' => 'ig-account-9',
    ]);

    app(\App\Services\Messaging\GraphApiMessagingService::class)->send($message, $connection);

    Http::assertSent(function ($request) {
        return $request['to'] === '9988776655';
    });
});
