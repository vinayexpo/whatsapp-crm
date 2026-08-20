<?php

use App\Models\Company;
use App\Models\User;
use App\Models\VoiceAgent;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function actingAsVoiceAgentRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('rejects unauthenticated voice agent listing', function () {
    $this->getJson('/api/v1/voice-agents')->assertUnauthorized();
});

it('lists voice agents for a role with voice-agents.view', function () {
    VoiceAgent::factory()->count(2)->create();
    $user = actingAsVoiceAgentRole('manager');

    $response = $this->actingAs($user)->getJson('/api/v1/voice-agents');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('filters voice agents by status', function () {
    VoiceAgent::factory()->create(['status' => 'active']);
    VoiceAgent::factory()->create(['status' => 'paused']);
    $user = actingAsVoiceAgentRole('manager');

    $response = $this->actingAs($user)->getJson('/api/v1/voice-agents?status=active');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.status'))->toBe('active');
});

it('filters voice agents by search matching name', function () {
    VoiceAgent::factory()->create(['name' => 'Sales Qualifier']);
    VoiceAgent::factory()->create(['name' => 'Support Screener']);
    $user = actingAsVoiceAgentRole('manager');

    $response = $this->actingAs($user)->getJson('/api/v1/voice-agents?search=Sales');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Sales Qualifier');
});

it('forbids an agent from listing voice agents', function () {
    VoiceAgent::factory()->count(2)->create();
    $user = actingAsVoiceAgentRole('agent');

    $this->actingAs($user)->getJson('/api/v1/voice-agents')->assertForbidden();
});

it('allows an admin to create a voice agent with defaults', function () {
    $admin = actingAsVoiceAgentRole('admin');

    $response = $this->actingAs($admin)->postJson('/api/v1/voice-agents', [
        'name' => 'Qualifier Bot',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Qualifier Bot')
        ->assertJsonPath('data.status', 'paused')
        ->assertJsonPath('data.voiceMode', 'tts')
        ->assertJsonPath('data.maxCallAttempts', 1);

    $this->assertDatabaseHas('voice_agents', ['name' => 'Qualifier Bot']);
});

it('forbids an agent from creating a voice agent', function () {
    $agent = actingAsVoiceAgentRole('agent');

    $this->actingAs($agent)->postJson('/api/v1/voice-agents', [
        'name' => 'Qualifier Bot',
    ])->assertForbidden();
});

it('allows a manager to update a voice agent', function () {
    $manager = actingAsVoiceAgentRole('manager');
    $voiceAgent = VoiceAgent::factory()->create(['name' => 'Old Name', 'status' => 'paused']);

    $response = $this->actingAs($manager)->patchJson("/api/v1/voice-agents/{$voiceAgent->uuid}", [
        'name' => 'New Name',
        'status' => 'active',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.status', 'active');
});

it('allows updating qualification criteria on a voice agent', function () {
    $manager = actingAsVoiceAgentRole('manager');
    $voiceAgent = VoiceAgent::factory()->create();

    $response = $this->actingAs($manager)->patchJson("/api/v1/voice-agents/{$voiceAgent->uuid}", [
        'qualificationCriteria' => [
            ['key' => 'budget', 'label' => 'Budget', 'question' => 'What is your budget?', 'type' => 'text'],
        ],
        'qualificationPassCriteria' => 'Budget over $1000',
    ]);

    $response->assertOk();
    expect($response->json('data.qualificationCriteria'))->toHaveCount(1);
    expect($response->json('data.qualificationPassCriteria'))->toBe('Budget over $1000');
});

it('forbids an agent from deleting a voice agent', function () {
    $agent = actingAsVoiceAgentRole('agent');
    $voiceAgent = VoiceAgent::factory()->create();

    $this->actingAs($agent)->deleteJson("/api/v1/voice-agents/{$voiceAgent->uuid}")->assertForbidden();
});

it('allows an admin to delete a voice agent', function () {
    $admin = actingAsVoiceAgentRole('admin');
    $voiceAgent = VoiceAgent::factory()->create();

    $this->actingAs($admin)->deleteJson("/api/v1/voice-agents/{$voiceAgent->uuid}")->assertOk();

    expect(VoiceAgent::query()->count())->toBe(0);
});

it('scopes voice agents to the caller company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = User::factory()->create(['company_id' => $companyA->id]);
    $userA->assignRole('manager');

    VoiceAgent::factory()->create(['company_id' => $companyA->id]);
    VoiceAgent::factory()->create(['company_id' => $companyB->id]);

    $response = $this->actingAs($userA)->getJson('/api/v1/voice-agents');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('404s when accessing a voice agent belonging to another company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = User::factory()->create(['company_id' => $companyA->id]);
    $userA->assignRole('manager');

    $foreignVoiceAgent = VoiceAgent::factory()->create(['company_id' => $companyB->id]);

    $this->actingAs($userA)->getJson("/api/v1/voice-agents/{$foreignVoiceAgent->uuid}")->assertNotFound();
});
