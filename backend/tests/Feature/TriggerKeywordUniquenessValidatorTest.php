<?php

use App\Models\ChatMenuFlow;
use App\Models\CommerceSetting;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function actingAsTriggerKeywordRole(string $role, ?Company $company = null): User
{
    $company ??= Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

function chatMenuFlowPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Flow',
        'entryNodeId' => 'start',
        'nodes' => [
            [
                'id' => 'start',
                'type' => 'content',
                'message' => 'Hello!',
            ],
        ],
    ], $overrides);
}

it('rejects saving a commerce trigger keyword that collides with an existing chat menu flow keyword', function () {
    $user = actingAsTriggerKeywordRole('manager');
    ChatMenuFlow::factory()->create(['company_id' => $user->company_id, 'trigger_keyword' => 'menu']);

    $response = $this->actingAs($user)->patchJson('/api/v1/commerce/settings', [
        'settings' => ['triggerKeywords' => ['Menu']],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('triggerKeywords');
});

it('allows saving a commerce trigger keyword that does not collide', function () {
    $user = actingAsTriggerKeywordRole('manager');
    ChatMenuFlow::factory()->create(['company_id' => $user->company_id, 'trigger_keyword' => 'support']);

    $response = $this->actingAs($user)->patchJson('/api/v1/commerce/settings', [
        'settings' => ['triggerKeywords' => ['order now']],
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('commerce_settings', ['company_id' => $user->company_id]);
});

it('rejects creating a chat menu flow trigger keyword that collides with an existing commerce keyword', function () {
    $user = actingAsTriggerKeywordRole('manager');
    CommerceSetting::factory()->create([
        'company_id' => $user->company_id,
        'settings' => ['trigger_keywords' => ['hi']],
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/chat-menu-flows', chatMenuFlowPayload([
        'triggerKeyword' => 'HI',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('triggerKeyword');
});

it('allows creating a chat menu flow trigger keyword that does not collide', function () {
    $user = actingAsTriggerKeywordRole('manager');
    CommerceSetting::factory()->create([
        'company_id' => $user->company_id,
        'settings' => ['trigger_keywords' => ['order']],
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/chat-menu-flows', chatMenuFlowPayload([
        'triggerKeyword' => 'support',
    ]));

    $response->assertCreated();
    $this->assertDatabaseHas('chat_menu_flows', ['trigger_keyword' => 'support']);
});

it('excludes the flow being updated from its own collision check', function () {
    $user = actingAsTriggerKeywordRole('manager');
    $flow = ChatMenuFlow::factory()->create(['company_id' => $user->company_id, 'trigger_keyword' => 'support']);

    $response = $this->actingAs($user)->patchJson("/api/v1/chat-menu-flows/{$flow->uuid}", [
        'triggerKeyword' => 'support',
    ]);

    $response->assertOk();
});
