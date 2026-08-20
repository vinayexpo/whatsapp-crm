<?php

use App\Models\ChatMenuFlow;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function actingAsChatMenuFlowRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('rejects unauthenticated chat menu flow listing', function () {
    $this->getJson('/api/v1/chat-menu-flows')->assertUnauthorized();
});

it('lists chat menu flows for a role with chatbots.view', function () {
    ChatMenuFlow::factory()->count(2)->create();
    $user = actingAsChatMenuFlowRole('manager');

    $response = $this->actingAs($user)->getJson('/api/v1/chat-menu-flows');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('filters chat menu flows by status', function () {
    ChatMenuFlow::factory()->create(['status' => 'active']);
    ChatMenuFlow::factory()->create(['status' => 'paused']);
    $user = actingAsChatMenuFlowRole('manager');

    $response = $this->actingAs($user)->getJson('/api/v1/chat-menu-flows?status=active');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.status'))->toBe('active');
});

it('filters chat menu flows by channel', function () {
    ChatMenuFlow::factory()->create(['channel' => 'whatsapp']);
    ChatMenuFlow::factory()->create(['channel' => 'web']);
    $user = actingAsChatMenuFlowRole('manager');

    $response = $this->actingAs($user)->getJson('/api/v1/chat-menu-flows?channel=web');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.channel'))->toBe('web');
});

it('filters chat menu flows by search matching name', function () {
    ChatMenuFlow::factory()->create(['name' => 'Catering Services Menu']);
    ChatMenuFlow::factory()->create(['name' => 'Support Options']);
    $user = actingAsChatMenuFlowRole('manager');

    $response = $this->actingAs($user)->getJson('/api/v1/chat-menu-flows?search=Catering');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Catering Services Menu');
});

it('forbids an agent from listing chat menu flows', function () {
    ChatMenuFlow::factory()->count(2)->create();
    $user = actingAsChatMenuFlowRole('agent');

    $this->actingAs($user)->getJson('/api/v1/chat-menu-flows')->assertForbidden();
});

it('allows an admin to create a chat menu flow', function () {
    $admin = actingAsChatMenuFlowRole('admin');

    $response = $this->actingAs($admin)->postJson('/api/v1/chat-menu-flows', [
        'name' => 'Services Menu',
        'channel' => 'both',
        'triggerKeyword' => 'services',
        'entryNodeId' => 'root',
        'nodes' => [
            [
                'id' => 'root',
                'type' => 'menu',
                'message' => 'What are you looking for?',
                'renderAs' => 'button',
                'buttons' => [
                    ['id' => 'btn-1', 'label' => 'Catering', 'nextNodeId' => 'leaf'],
                ],
            ],
            ['id' => 'leaf', 'type' => 'content', 'message' => 'We cater events.', 'buttons' => []],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Services Menu')
        ->assertJsonPath('data.status', 'paused')
        ->assertJsonPath('data.triggerKeyword', 'services');

    $this->assertDatabaseHas('chat_menu_flows', ['name' => 'Services Menu']);
});

it('rejects creating a chat menu flow with an empty node list', function () {
    $admin = actingAsChatMenuFlowRole('admin');

    $this->actingAs($admin)->postJson('/api/v1/chat-menu-flows', [
        'name' => 'Empty Flow',
        'entryNodeId' => 'root',
        'nodes' => [],
    ])->assertUnprocessable();
});

it('forbids an agent from creating a chat menu flow', function () {
    $agent = actingAsChatMenuFlowRole('agent');

    $this->actingAs($agent)->postJson('/api/v1/chat-menu-flows', [
        'name' => 'Services Menu',
        'entryNodeId' => 'root',
        'nodes' => [['id' => 'root', 'type' => 'content', 'message' => 'Hi', 'buttons' => []]],
    ])->assertForbidden();
});

it('allows a manager to update a chat menu flow', function () {
    $manager = actingAsChatMenuFlowRole('manager');
    $flow = ChatMenuFlow::factory()->create(['company_id' => $manager->company_id, 'name' => 'Old', 'status' => 'paused']);

    $response = $this->actingAs($manager)->patchJson("/api/v1/chat-menu-flows/{$flow->uuid}", [
        'name' => 'New Name',
        'status' => 'active',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.status', 'active');
});

it('forbids an agent from deleting a chat menu flow', function () {
    $agent = actingAsChatMenuFlowRole('agent');
    $flow = ChatMenuFlow::factory()->create(['company_id' => $agent->company_id]);

    $this->actingAs($agent)->deleteJson("/api/v1/chat-menu-flows/{$flow->uuid}")->assertForbidden();
});

it('allows an admin to delete a chat menu flow', function () {
    $admin = actingAsChatMenuFlowRole('admin');
    $flow = ChatMenuFlow::factory()->create(['company_id' => $admin->company_id]);

    $this->actingAs($admin)->deleteJson("/api/v1/chat-menu-flows/{$flow->uuid}")->assertOk();

    expect(ChatMenuFlow::query()->count())->toBe(0);
});

it('scopes chat menu flows to the caller company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = User::factory()->create(['company_id' => $companyA->id]);
    $userA->assignRole('manager');

    ChatMenuFlow::factory()->create(['company_id' => $companyA->id]);
    ChatMenuFlow::factory()->create(['company_id' => $companyB->id]);

    $response = $this->actingAs($userA)->getJson('/api/v1/chat-menu-flows');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('404s when accessing a chat menu flow belonging to another company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = User::factory()->create(['company_id' => $companyA->id]);
    $userA->assignRole('manager');

    $foreignFlow = ChatMenuFlow::factory()->create(['company_id' => $companyB->id]);

    $this->actingAs($userA)->getJson("/api/v1/chat-menu-flows/{$foreignFlow->uuid}")->assertNotFound();
});

it('rejects unauthenticated chat menu flow generation', function () {
    $this->postJson('/api/v1/chat-menu-flows/generate', ['prompt' => 'A catering menu'])->assertUnauthorized();
});

it('forbids an agent from generating a chat menu flow', function () {
    $agent = actingAsChatMenuFlowRole('agent');

    $this->actingAs($agent)->postJson('/api/v1/chat-menu-flows/generate', [
        'prompt' => 'A catering menu',
    ])->assertForbidden();
});

it('requires a prompt to generate a chat menu flow', function () {
    $admin = actingAsChatMenuFlowRole('admin');
    $admin->update(['company_id' => Company::factory()->create()->id]);

    $this->actingAs($admin)->postJson('/api/v1/chat-menu-flows/generate', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('prompt');
});

it('allows an admin to generate a draft chat menu flow', function () {
    $admin = actingAsChatMenuFlowRole('admin');
    $admin->update(['company_id' => Company::factory()->create()->id]);

    $response = $this->actingAs($admin)->postJson('/api/v1/chat-menu-flows/generate', [
        'prompt' => 'A catering services menu',
    ]);

    $response->assertOk();
    expect($response->json('data.entryNodeId'))->toBeString();
    expect($response->json('data.nodes'))->toHaveCount(2);
    expect($response->json('data.nodes.0.message'))->toBe('Fake reply for: A catering services menu');
});

it('rejects an empty-string prompt when generating a chat menu flow', function () {
    $admin = actingAsChatMenuFlowRole('admin');
    $admin->update(['company_id' => Company::factory()->create()->id]);

    $this->actingAs($admin)->postJson('/api/v1/chat-menu-flows/generate', [
        'prompt' => '   ',
    ])->assertUnprocessable();
});
