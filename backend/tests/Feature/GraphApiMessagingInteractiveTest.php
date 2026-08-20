<?php

use App\Models\ApiConnection;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Messaging\GraphApiMessagingService;
use Database\Seeders\PipelineStagesSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

it('sends a button-type interactive message when the message has 3 or fewer buttons', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.123']]], 200),
    ]);

    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp', 'access_token' => 'test-token', 'phone_number_id' => '1234567890']);
    $contact = Contact::factory()->create(['channel' => 'whatsapp', 'handle' => '15551234567']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp']);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'outbound',
        'text' => 'What are you looking for?',
        'buttons' => [
            ['id' => 'btn-catering', 'label' => 'Catering', 'nextNodeId' => 'node-2'],
            ['id' => 'btn-menu', 'label' => 'Menu', 'nextNodeId' => 'node-3'],
        ],
    ]);

    $externalId = (new GraphApiMessagingService())->send($message, $connection);

    expect($externalId)->toBe('wamid.123');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://graph.facebook.com/v20.0/1234567890/messages'
            && $body['type'] === 'interactive'
            && $body['interactive']['type'] === 'button'
            && $body['interactive']['body']['text'] === 'What are you looking for?'
            && $body['interactive']['action']['buttons'] === [
                ['type' => 'reply', 'reply' => ['id' => 'btn-catering', 'title' => 'Catering']],
                ['type' => 'reply', 'reply' => ['id' => 'btn-menu', 'title' => 'Menu']],
            ];
    });
});

it('sends a list-type interactive message when the message has more than 3 buttons', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.456']]], 200),
    ]);

    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp', 'access_token' => 'test-token', 'phone_number_id' => '1234567890']);
    $contact = Contact::factory()->create(['channel' => 'whatsapp', 'handle' => '15551234567']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp']);
    $buttons = collect(range(1, 5))->map(fn ($i) => ['id' => "btn-{$i}", 'label' => "Option {$i}", 'nextNodeId' => "node-{$i}"])->all();
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'outbound',
        'text' => 'Pick one',
        'buttons' => $buttons,
    ]);

    (new GraphApiMessagingService())->send($message, $connection);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $body['type'] === 'interactive'
            && $body['interactive']['type'] === 'list'
            && count($body['interactive']['action']['sections'][0]['rows']) === 5
            && $body['interactive']['action']['sections'][0]['rows'][0] === ['id' => 'btn-1', 'title' => 'Option 1'];
    });
});
