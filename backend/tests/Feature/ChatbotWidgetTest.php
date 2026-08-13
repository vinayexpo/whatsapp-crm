<?php

use App\Models\AiAssistantSetting;
use App\Models\Chatbot;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\Chatbot\ChatbotReplyServiceInterface;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

function createChatbot(array $attributes = []): Chatbot
{
    return Chatbot::factory()->create(array_merge(
        ['company_id' => Company::factory()->create()->id],
        $attributes
    ));
}

it('rejects a widget request with a missing widget key', function () {
    $this->getJson('/api/widget/bootstrap', ['X-Visitor-Id' => 'visitor-1'])
        ->assertNotFound();
});

it('rejects a widget request with an invalid widget key', function () {
    $this->getJson('/api/widget/bootstrap', [
        'X-Widget-Key' => 'not-a-real-key',
        'X-Visitor-Id' => 'visitor-1',
    ])->assertNotFound();
});

it('rejects a widget request from an origin not on the allow-list', function () {
    $chatbot = createChatbot(['allowed_origins' => ['https://allowed.example.com']]);

    $this->getJson('/api/widget/bootstrap', [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'visitor-1',
        'Origin' => 'https://evil.example.com',
    ])->assertNotFound();
});

it('allows a widget request from an origin on the allow-list', function () {
    $chatbot = createChatbot(['allowed_origins' => ['https://allowed.example.com']]);

    $this->getJson('/api/widget/bootstrap', [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'visitor-1',
        'Origin' => 'https://allowed.example.com',
    ])->assertOk();
});

it('allows a widget request when the allow-list is empty', function () {
    $chatbot = createChatbot(['allowed_origins' => null]);

    $this->getJson('/api/widget/bootstrap', [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'visitor-1',
        'Origin' => 'https://anywhere.example.com',
    ])->assertOk();
});

it('rejects an inactive chatbot widget key', function () {
    $chatbot = createChatbot(['status' => 'inactive']);

    $this->getJson('/api/widget/bootstrap', [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'visitor-1',
    ])->assertNotFound();
});

it('sets permissive CORS headers on widget responses', function () {
    $chatbot = createChatbot();

    $response = $this->getJson('/api/widget/bootstrap', [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'visitor-1',
        'Origin' => 'https://third-party.example.com',
    ]);

    $response->assertOk();
    $response->assertHeader('Access-Control-Allow-Origin', 'https://third-party.example.com');
});

it('bootstraps a new visitor with a welcome message and creates a contact + conversation', function () {
    $chatbot = createChatbot(['welcome_message' => 'Welcome!']);

    $response = $this->getJson('/api/widget/bootstrap', [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'visitor-abc',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.welcomeMessage', 'Welcome!');

    expect($response->json('data.conversationId'))->not->toBeNull();

    $contact = Contact::query()->where('handle', 'visitor-abc')->where('channel', 'website')->first();
    expect($contact)->not->toBeNull();
    expect($contact->company_id)->toBe($chatbot->company_id);

    $conversation = Conversation::query()->where('contact_id', $contact->id)->where('chatbot_id', $chatbot->id)->first();
    expect($conversation)->not->toBeNull();
    expect($conversation->channel)->toBe('website');
});

it('bootstraps a new visitor even when the default new-lead stage is missing', function () {
    $company = Company::factory()->create();
    PipelineStage::query()->where('id', 'new-lead')->delete();
    PipelineStage::query()->create([
        'id' => 'first-touch',
        'company_id' => $company->id,
        'name' => 'First Touch',
        'color' => '#123456',
        'position' => 0,
    ]);

    $chatbot = createChatbot([
        'company_id' => $company->id,
        'welcome_message' => 'Welcome!',
    ]);

    $response = $this->getJson('/api/widget/bootstrap', [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'visitor-stage-fallback',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.welcomeMessage', 'Welcome!');

    $contact = Contact::query()
        ->where('handle', 'visitor-stage-fallback')
        ->where('channel', 'website')
        ->first();

    expect($contact)->not->toBeNull();
    expect($contact->pipeline_stage_id)->toBe('first-touch');
});

it('requires the X-Visitor-Id header on bootstrap', function () {
    $chatbot = createChatbot();

    $this->getJson('/api/widget/bootstrap', [
        'X-Widget-Key' => $chatbot->widget_key,
    ])->assertStatus(422);
});

it('sends a message and receives a fake reply in tests', function () {
    $chatbot = createChatbot();

    $response = $this->postJson('/api/widget/messages', ['text' => 'Hello there'], [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'visitor-xyz',
    ]);

    $response->assertCreated();
    expect($response->json('data.reply'))->toBe('Fake reply to: Hello there');
    expect($response->json('data.message.text'))->toBe('Fake reply to: Hello there');
    expect($response->json('data.message.direction'))->toBe('outbound');
});

it('persists both the inbound visitor message and the outbound bot reply', function () {
    $chatbot = createChatbot();

    $this->postJson('/api/widget/messages', ['text' => 'Hello there'], [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'visitor-xyz',
    ])->assertCreated();

    $contact = Contact::query()->where('handle', 'visitor-xyz')->first();
    $conversation = Conversation::query()->where('contact_id', $contact->id)->first();

    expect($conversation->messages()->count())->toBe(2);
    expect($conversation->messages()->where('direction', 'inbound')->first()->text)->toBe('Hello there');
    expect($conversation->messages()->where('direction', 'outbound')->first()->text)
        ->toBe('Fake reply to: Hello there');
});

it('reuses the same conversation for the same visitor id across requests', function () {
    $chatbot = createChatbot();

    $this->postJson('/api/widget/messages', ['text' => 'First message'], [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'visitor-dedupe',
    ])->assertCreated();

    $this->postJson('/api/widget/messages', ['text' => 'Second message'], [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'visitor-dedupe',
    ])->assertCreated();

    expect(Contact::query()->where('handle', 'visitor-dedupe')->count())->toBe(1);
    expect(Conversation::query()->count())->toBe(1);

    $conversation = Conversation::query()->first();
    expect($conversation->messages()->count())->toBe(4);
});

it('validates the message text is required', function () {
    $chatbot = createChatbot();

    $this->postJson('/api/widget/messages', [], [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'visitor-xyz',
    ])->assertStatus(422);
});

it('polls for new messages since a given message id', function () {
    $chatbot = createChatbot();

    $first = $this->postJson('/api/widget/messages', ['text' => 'Hi'], [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'visitor-poll',
    ])->assertCreated();

    $firstReplyId = $first->json('data.message.id');

    $this->postJson('/api/widget/messages', ['text' => 'Second question'], [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'visitor-poll',
    ])->assertCreated();

    $response = $this->getJson('/api/widget/messages?since='.$firstReplyId, [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'visitor-poll',
    ]);

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('404s polling for messages when the visitor has no conversation yet', function () {
    $chatbot = createChatbot();

    $this->getJson('/api/widget/messages', [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'never-messaged',
    ])->assertNotFound();
});

it('rate limits widget requests per widget key', function () {
    $chatbot = createChatbot();

    $lastResponse = null;
    for ($i = 0; $i < 31; $i++) {
        $lastResponse = $this->getJson('/api/widget/bootstrap', [
            'X-Widget-Key' => $chatbot->widget_key,
            'X-Visitor-Id' => 'visitor-rl-'.$i,
        ]);
    }

    $lastResponse->assertStatus(429);
});

it('notifies chatbot managers when the widget reply is a handoff to a human', function () {
    $chatbot = createChatbot();

    $user = User::factory()->create(['company_id' => $chatbot->company_id]);
    $user->assignRole('manager');

    $this->app->bind(ChatbotReplyServiceInterface::class, function () {
        return new class implements ChatbotReplyServiceInterface
        {
            public function reply($chatbot, $conversation, string $visitorMessage): string
            {
                return "Sorry, I'm unable to answer right now.";
            }

            public function isHandoff(string $reply): bool
            {
                return true;
            }
        };
    });

    $this->postJson('/api/widget/messages', ['text' => 'Something obscure'], [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'visitor-handoff',
    ])->assertCreated();

    $this->assertDatabaseHas('notifications', [
        'user_id' => $user->id,
        'type' => 'chatbot_handoff',
    ]);
});

it('shows a website conversation created by the widget in the authenticated inbox', function () {
    $chatbot = createChatbot();

    $this->postJson('/api/widget/messages', ['text' => 'Hello'], [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'visitor-inbox',
    ])->assertCreated();

    $user = User::factory()->create(['company_id' => $chatbot->company_id]);
    $user->assignRole('manager');

    $response = $this->actingAs($user)->getJson('/api/v1/conversations');

    $response->assertOk();
    $channels = collect($response->json('data'))->pluck('channel');
    expect($channels)->toContain('website');
});

it('accepts structured ai replies for widget messages', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => [
                        ['type' => 'output_text', 'text' => 'We are open 9 to 5.'],
                    ],
                ],
            ]],
        ], 200),
    ]);

    $chatbot = createChatbot();

    AiAssistantSetting::query()->create([
        'company_id' => $chatbot->company_id,
        'base_url' => 'https://api.openai.com/v1',
        'api_key' => 'test-key',
        'model' => 'gpt-4o-mini',
    ]);

    $response = $this->postJson('/api/widget/messages', ['text' => 'What are your hours?'], [
        'X-Widget-Key' => $chatbot->widget_key,
        'X-Visitor-Id' => 'visitor-structured-reply',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.reply', 'We are open 9 to 5.')
        ->assertJsonPath('data.message.text', 'We are open 9 to 5.');
});
