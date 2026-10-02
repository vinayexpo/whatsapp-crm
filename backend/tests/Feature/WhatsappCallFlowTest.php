<?php

use App\Models\ApiConnection;
use App\Models\Company;
use App\Models\User;
use App\Models\WhatsappCallFlow;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function actingAsWhatsappCallFlowRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('rejects unauthenticated call flow listing', function () {
    $this->getJson('/api/v1/whatsapp-call-flows')->assertUnauthorized();
});

it('lists call flows for a role with whatsapp-calling.view', function () {
    WhatsappCallFlow::factory()->count(2)->create();
    $user = actingAsWhatsappCallFlowRole('manager');

    $response = $this->actingAs($user)->getJson('/api/v1/whatsapp-call-flows');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('filters call flows by status', function () {
    WhatsappCallFlow::factory()->create(['status' => 'active']);
    WhatsappCallFlow::factory()->create(['status' => 'paused']);
    $user = actingAsWhatsappCallFlowRole('manager');

    $response = $this->actingAs($user)->getJson('/api/v1/whatsapp-call-flows?status=active');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.status'))->toBe('active');
});

it('filters call flows by search matching name', function () {
    WhatsappCallFlow::factory()->create(['name' => 'Lead Qualification Flow']);
    WhatsappCallFlow::factory()->create(['name' => 'Support Triage Flow']);
    $user = actingAsWhatsappCallFlowRole('manager');

    $response = $this->actingAs($user)->getJson('/api/v1/whatsapp-call-flows?search=Lead');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Lead Qualification Flow');
});

it('forbids an agent from listing call flows', function () {
    WhatsappCallFlow::factory()->count(2)->create();
    $user = actingAsWhatsappCallFlowRole('agent');

    $this->actingAs($user)->getJson('/api/v1/whatsapp-call-flows')->assertForbidden();
});

it('allows an admin to create a call flow', function () {
    $admin = actingAsWhatsappCallFlowRole('admin');
    $connection = ApiConnection::factory()->create(['company_id' => $admin->company_id, 'channel' => 'whatsapp']);

    $response = $this->actingAs($admin)->postJson('/api/v1/whatsapp-call-flows', [
        'apiConnectionId' => $connection->uuid,
        'name' => 'Sales Qualifier',
        'greetingMessage' => 'Hi, thanks for calling!',
        'nodes' => [
            ['id' => 'q1', 'type' => 'question', 'prompt' => 'What is your budget?', 'variable_key' => 'budget'],
            ['id' => 'end', 'type' => 'end_call', 'prompt' => 'Thanks, bye!'],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Sales Qualifier')
        ->assertJsonPath('data.status', 'paused')
        ->assertJsonPath('data.maxRetries', 2);

    $this->assertDatabaseHas('whatsapp_call_flows', ['name' => 'Sales Qualifier']);
});

it('creates a call flow with ai voice mode and a non-default language', function () {
    $admin = actingAsWhatsappCallFlowRole('admin');
    $connection = ApiConnection::factory()->create(['company_id' => $admin->company_id, 'channel' => 'whatsapp']);

    $response = $this->actingAs($admin)->postJson('/api/v1/whatsapp-call-flows', [
        'apiConnectionId' => $connection->uuid,
        'name' => 'Hindi Voice Flow',
        'greetingMessage' => 'Namaste!',
        'nodes' => [
            ['id' => 'end', 'type' => 'end_call', 'prompt' => 'Dhanyavaad!'],
        ],
        'voiceMode' => 'ai_voice',
        'language' => 'hi',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.voiceMode', 'ai_voice')
        ->assertJsonPath('data.language', 'hi');

    $this->assertDatabaseHas('whatsapp_call_flows', [
        'name' => 'Hindi Voice Flow',
        'voice_mode' => 'ai_voice',
        'language' => 'hi',
    ]);
});

it('defaults voice mode and language when not provided', function () {
    $admin = actingAsWhatsappCallFlowRole('admin');
    $connection = ApiConnection::factory()->create(['company_id' => $admin->company_id, 'channel' => 'whatsapp']);

    $response = $this->actingAs($admin)->postJson('/api/v1/whatsapp-call-flows', [
        'apiConnectionId' => $connection->uuid,
        'name' => 'Default Flow',
        'greetingMessage' => 'Hi!',
        'nodes' => [
            ['id' => 'end', 'type' => 'end_call', 'prompt' => 'Bye!'],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.voiceMode', 'text_only')
        ->assertJsonPath('data.language', 'en');
});

it('updates voice mode and language on an existing call flow', function () {
    $admin = actingAsWhatsappCallFlowRole('admin');
    $flow = WhatsappCallFlow::factory()->create(['company_id' => $admin->company_id]);

    $response = $this->actingAs($admin)->patchJson("/api/v1/whatsapp-call-flows/{$flow->uuid}", [
        'voiceMode' => 'ai_voice',
        'language' => 'te',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.voiceMode', 'ai_voice')
        ->assertJsonPath('data.language', 'te');

    $this->assertDatabaseHas('whatsapp_call_flows', [
        'id' => $flow->id,
        'voice_mode' => 'ai_voice',
        'language' => 'te',
    ]);
});

it('rejects an invalid language on a call flow', function () {
    $admin = actingAsWhatsappCallFlowRole('admin');
    $connection = ApiConnection::factory()->create(['company_id' => $admin->company_id, 'channel' => 'whatsapp']);

    $response = $this->actingAs($admin)->postJson('/api/v1/whatsapp-call-flows', [
        'apiConnectionId' => $connection->uuid,
        'name' => 'Bad Language Flow',
        'greetingMessage' => 'Hi!',
        'nodes' => [
            ['id' => 'end', 'type' => 'end_call', 'prompt' => 'Bye!'],
        ],
        'language' => 'fr',
    ]);

    $response->assertStatus(422);
});

it('rejects creating a call flow with an empty node list', function () {
    $admin = actingAsWhatsappCallFlowRole('admin');
    $connection = ApiConnection::factory()->create(['company_id' => $admin->company_id, 'channel' => 'whatsapp']);

    $this->actingAs($admin)->postJson('/api/v1/whatsapp-call-flows', [
        'apiConnectionId' => $connection->uuid,
        'name' => 'Empty Flow',
        'greetingMessage' => 'Hi',
        'nodes' => [],
    ])->assertUnprocessable();
});

it('forbids an agent from creating a call flow', function () {
    $agent = actingAsWhatsappCallFlowRole('agent');
    $connection = ApiConnection::factory()->create(['company_id' => $agent->company_id, 'channel' => 'whatsapp']);

    $this->actingAs($agent)->postJson('/api/v1/whatsapp-call-flows', [
        'apiConnectionId' => $connection->uuid,
        'name' => 'Sales Qualifier',
        'greetingMessage' => 'Hi',
        'nodes' => [['id' => 'end', 'type' => 'end_call', 'prompt' => 'Bye']],
    ])->assertForbidden();
});

it('allows a manager to update a call flow', function () {
    $manager = actingAsWhatsappCallFlowRole('manager');
    $flow = WhatsappCallFlow::factory()->create(['company_id' => $manager->company_id, 'name' => 'Old', 'status' => 'paused']);

    $response = $this->actingAs($manager)->patchJson("/api/v1/whatsapp-call-flows/{$flow->uuid}", [
        'name' => 'New Name',
        'status' => 'active',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.status', 'active');
});

it('allows updating the node list on a call flow', function () {
    $manager = actingAsWhatsappCallFlowRole('manager');
    $flow = WhatsappCallFlow::factory()->create(['company_id' => $manager->company_id]);

    $response = $this->actingAs($manager)->patchJson("/api/v1/whatsapp-call-flows/{$flow->uuid}", [
        'nodes' => [
            ['id' => 'menu', 'type' => 'menu', 'prompt' => 'Press 1 for sales', 'options' => ['sales', 'support']],
        ],
    ]);

    $response->assertOk();
    expect($response->json('data.nodes'))->toHaveCount(1);
});

it('forbids an agent from deleting a call flow', function () {
    $agent = actingAsWhatsappCallFlowRole('agent');
    $flow = WhatsappCallFlow::factory()->create(['company_id' => $agent->company_id]);

    $this->actingAs($agent)->deleteJson("/api/v1/whatsapp-call-flows/{$flow->uuid}")->assertForbidden();
});

it('allows an admin to delete a call flow', function () {
    $admin = actingAsWhatsappCallFlowRole('admin');
    $flow = WhatsappCallFlow::factory()->create(['company_id' => $admin->company_id]);

    $this->actingAs($admin)->deleteJson("/api/v1/whatsapp-call-flows/{$flow->uuid}")->assertOk();

    expect(WhatsappCallFlow::query()->count())->toBe(0);
});

it('scopes call flows to the caller company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = User::factory()->create(['company_id' => $companyA->id]);
    $userA->assignRole('manager');

    WhatsappCallFlow::factory()->create(['company_id' => $companyA->id]);
    WhatsappCallFlow::factory()->create(['company_id' => $companyB->id]);

    $response = $this->actingAs($userA)->getJson('/api/v1/whatsapp-call-flows');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('404s when accessing a call flow belonging to another company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = User::factory()->create(['company_id' => $companyA->id]);
    $userA->assignRole('manager');

    $foreignFlow = WhatsappCallFlow::factory()->create(['company_id' => $companyB->id]);

    $this->actingAs($userA)->getJson("/api/v1/whatsapp-call-flows/{$foreignFlow->uuid}")->assertNotFound();
});
