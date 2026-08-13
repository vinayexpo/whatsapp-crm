<?php

use App\Models\Chatbot;
use App\Models\ChatbotTrainingEntry;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function actingAsChatbotRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('rejects unauthenticated chatbot listing', function () {
    $this->getJson('/api/v1/chatbots')->assertUnauthorized();
});

it('lists chatbots for any authenticated role with chatbots.view', function () {
    Chatbot::factory()->count(2)->create();
    $user = actingAsChatbotRole('manager');

    $response = $this->actingAs($user)->getJson('/api/v1/chatbots');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('forbids an agent from listing chatbots', function () {
    Chatbot::factory()->count(2)->create();
    $user = actingAsChatbotRole('agent');

    $this->actingAs($user)->getJson('/api/v1/chatbots')->assertForbidden();
});

it('allows an admin to create a chatbot with an auto-generated widget key', function () {
    $admin = actingAsChatbotRole('admin');

    $response = $this->actingAs($admin)->postJson('/api/v1/chatbots', [
        'name' => 'Support Bot',
        'welcomeMessage' => 'Hi there!',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Support Bot')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.welcomeMessage', 'Hi there!');

    $widgetKey = $response->json('data.widgetKey');
    expect($widgetKey)->not->toBeNull();
    expect(strlen($widgetKey))->toBe(32);

    $this->assertDatabaseHas('chatbots', ['name' => 'Support Bot']);
});

it('forbids an agent from creating a chatbot', function () {
    $agent = actingAsChatbotRole('agent');

    $this->actingAs($agent)->postJson('/api/v1/chatbots', [
        'name' => 'Support Bot',
    ])->assertForbidden();
});

it('allows a manager to update a chatbot', function () {
    $manager = actingAsChatbotRole('manager');
    $chatbot = Chatbot::factory()->create(['name' => 'Old Name']);

    $response = $this->actingAs($manager)->patchJson("/api/v1/chatbots/{$chatbot->uuid}", [
        'name' => 'New Name',
        'status' => 'inactive',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.status', 'inactive');
});

it('forbids an agent from deleting a chatbot', function () {
    $agent = actingAsChatbotRole('agent');
    $chatbot = Chatbot::factory()->create();

    $this->actingAs($agent)->deleteJson("/api/v1/chatbots/{$chatbot->uuid}")->assertForbidden();
});

it('allows an admin to delete a chatbot', function () {
    $admin = actingAsChatbotRole('admin');
    $chatbot = Chatbot::factory()->create();

    $this->actingAs($admin)->deleteJson("/api/v1/chatbots/{$chatbot->uuid}")->assertOk();

    expect(Chatbot::query()->count())->toBe(0);
});

it('scopes chatbots to the caller company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = User::factory()->create(['company_id' => $companyA->id]);
    $userA->assignRole('manager');

    Chatbot::factory()->create(['company_id' => $companyA->id]);
    Chatbot::factory()->create(['company_id' => $companyB->id]);

    $response = $this->actingAs($userA)->getJson('/api/v1/chatbots');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('404s when accessing a chatbot belonging to another company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = User::factory()->create(['company_id' => $companyA->id]);
    $userA->assignRole('manager');

    $foreignChatbot = Chatbot::factory()->create(['company_id' => $companyB->id]);

    $this->actingAs($userA)->getJson("/api/v1/chatbots/{$foreignChatbot->uuid}")->assertNotFound();
});

it('allows a manager to add a manual training entry to a chatbot', function () {
    $manager = actingAsChatbotRole('manager');
    $chatbot = Chatbot::factory()->create();

    $response = $this->actingAs($manager)->postJson("/api/v1/chatbots/{$chatbot->uuid}/training-entries", [
        'question' => 'What are your hours?',
        'answer' => 'We are open 9-5 Mon-Fri.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.question', 'What are your hours?')
        ->assertJsonPath('data.source', 'manual');

    expect($chatbot->trainingEntries()->count())->toBe(1);
});

it('lists training entries for a chatbot', function () {
    $chatbot = Chatbot::factory()->create();
    ChatbotTrainingEntry::factory()->count(3)->create(['chatbot_id' => $chatbot->id]);
    $user = actingAsChatbotRole('manager');

    $response = $this->actingAs($user)->getJson("/api/v1/chatbots/{$chatbot->uuid}/training-entries");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
});

it('allows updating a training entry', function () {
    $manager = actingAsChatbotRole('manager');
    $chatbot = Chatbot::factory()->create();
    $entry = ChatbotTrainingEntry::factory()->create(['chatbot_id' => $chatbot->id]);

    $response = $this->actingAs($manager)->patchJson(
        "/api/v1/chatbots/{$chatbot->uuid}/training-entries/{$entry->uuid}",
        ['answer' => 'Updated answer.']
    );

    $response->assertOk()->assertJsonPath('data.answer', 'Updated answer.');
});

it('allows deleting a training entry', function () {
    $manager = actingAsChatbotRole('manager');
    $chatbot = Chatbot::factory()->create();
    $entry = ChatbotTrainingEntry::factory()->create(['chatbot_id' => $chatbot->id]);

    $this->actingAs($manager)->deleteJson(
        "/api/v1/chatbots/{$chatbot->uuid}/training-entries/{$entry->uuid}"
    )->assertOk();

    expect(ChatbotTrainingEntry::query()->count())->toBe(0);
});

it('404s when a training entry does not belong to the given chatbot', function () {
    $manager = actingAsChatbotRole('manager');
    $chatbotA = Chatbot::factory()->create();
    $chatbotB = Chatbot::factory()->create();
    $entry = ChatbotTrainingEntry::factory()->create(['chatbot_id' => $chatbotB->id]);

    $this->actingAs($manager)->patchJson(
        "/api/v1/chatbots/{$chatbotA->uuid}/training-entries/{$entry->uuid}",
        ['answer' => 'Nope.']
    )->assertNotFound();
});

it('defaults general_fallback_enabled to false on creation', function () {
    $admin = actingAsChatbotRole('admin');

    $response = $this->actingAs($admin)->postJson('/api/v1/chatbots', [
        'name' => 'Support Bot',
    ]);

    $response->assertCreated()->assertJsonPath('data.generalFallbackEnabled', false);
});

it('allows toggling general_fallback_enabled and it persists', function () {
    $manager = actingAsChatbotRole('manager');
    $chatbot = Chatbot::factory()->create(['general_fallback_enabled' => false]);

    $response = $this->actingAs($manager)->patchJson("/api/v1/chatbots/{$chatbot->uuid}", [
        'generalFallbackEnabled' => true,
    ]);

    $response->assertOk()->assertJsonPath('data.generalFallbackEnabled', true);

    $chatbot->refresh();
    expect($chatbot->general_fallback_enabled)->toBeTrue();

    $show = $this->actingAs($manager)->getJson("/api/v1/chatbots/{$chatbot->uuid}");
    $show->assertOk()->assertJsonPath('data.generalFallbackEnabled', true);
});

it('branches the system prompt wording based on general_fallback_enabled', function () {
    $this->seed(\Database\Seeders\PipelineStagesSeeder::class);

    $strictChatbot = Chatbot::factory()->create(['general_fallback_enabled' => false]);
    $permissiveChatbot = Chatbot::factory()->create(['general_fallback_enabled' => true]);
    $conversation = \App\Models\Conversation::factory()->create(['chatbot_id' => $strictChatbot->id]);

    $service = new \App\Services\Chatbot\OpenAiChatbotReplyService;
    $buildMessages = new ReflectionMethod($service, 'buildMessages');
    $buildMessages->setAccessible(true);

    $strictMessages = $buildMessages->invoke($service, $strictChatbot, $conversation, 'Hello');
    $permissiveMessages = $buildMessages->invoke($service, $permissiveChatbot, $conversation, 'Hello');

    expect($strictMessages[0]['content'])->toContain("don't have that information");
    expect($permissiveMessages[0]['content'])->toContain('answer helpfully and concisely anyway');
});

it('generates candidate training entries from pasted text without persisting them', function () {
    $manager = actingAsChatbotRole('manager');
    $chatbot = Chatbot::factory()->create();

    $response = $this->actingAs($manager)->postJson("/api/v1/chatbots/{$chatbot->uuid}/training-entries/generate", [
        'text' => 'Our support hours are 9-5 Monday to Friday. We are closed on public holidays.',
    ]);

    $response->assertOk();
    expect($response->json('data'))->not->toBeEmpty();
    expect($chatbot->trainingEntries()->count())->toBe(0);
});

it('generates candidate training entries from an uploaded text file', function () {
    $manager = actingAsChatbotRole('manager');
    $chatbot = Chatbot::factory()->create();

    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent(
        'faq.txt',
        'Our support hours are 9-5 Monday to Friday.'
    );

    $response = $this->actingAs($manager)->post("/api/v1/chatbots/{$chatbot->uuid}/training-entries/generate", [
        'file' => $file,
    ]);

    $response->assertOk();
    expect($response->json('data'))->not->toBeEmpty();
    expect($chatbot->trainingEntries()->count())->toBe(0);
});

it('rejects generate requests with neither file nor text', function () {
    $manager = actingAsChatbotRole('manager');
    $chatbot = Chatbot::factory()->create();

    $this->actingAs($manager)
        ->postJson("/api/v1/chatbots/{$chatbot->uuid}/training-entries/generate", [])
        ->assertUnprocessable();
});

it('rejects generate requests with both file and text', function () {
    $manager = actingAsChatbotRole('manager');
    $chatbot = Chatbot::factory()->create();

    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('faq.txt', 'Some content.');

    $this->actingAs($manager)
        ->postJson("/api/v1/chatbots/{$chatbot->uuid}/training-entries/generate", [
            'file' => $file,
            'text' => 'Some pasted text.',
        ])
        ->assertUnprocessable();
});

it('forbids an agent from generating training entries', function () {
    $agent = actingAsChatbotRole('agent');
    $chatbot = Chatbot::factory()->create();

    $this->actingAs($agent)
        ->postJson("/api/v1/chatbots/{$chatbot->uuid}/training-entries/generate", [
            'text' => 'Some text.',
        ])
        ->assertForbidden();
});
