<?php

use App\Models\User;
use App\Models\VoiceCall;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

function actingAsFollowupRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('rejects unauthenticated followup listing', function () {
    $this->getJson('/api/v1/voice-calls')->assertUnauthorized();
});

it('forbids an agent from listing voice calls', function () {
    $agent = actingAsFollowupRole('agent');

    $this->actingAs($agent)->getJson('/api/v1/voice-calls')->assertForbidden();
});

it('lists voice calls needing human followup', function () {
    $manager = actingAsFollowupRole('manager');
    VoiceCall::factory()->create(['needs_human_followup' => true, 'human_followup_completed_at' => null]);
    VoiceCall::factory()->create(['needs_human_followup' => false]);
    VoiceCall::factory()->create(['needs_human_followup' => true, 'human_followup_completed_at' => now()]);

    $response = $this->actingAs($manager)->getJson('/api/v1/voice-calls?needsHumanFollowup=true');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('allows a manager to assign a followup to a team member', function () {
    $manager = actingAsFollowupRole('manager');
    $assignee = User::factory()->create(['company_id' => $manager->company_id]);
    $voiceCall = VoiceCall::factory()->create(['company_id' => $manager->company_id, 'needs_human_followup' => true]);

    $response = $this->actingAs($manager)->patchJson("/api/v1/voice-calls/{$voiceCall->uuid}/followup", [
        'userId' => $assignee->uuid,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.humanFollowupAssignedTo.id', $assignee->uuid);

    expect($voiceCall->fresh()->human_followup_assigned_to)->toBe($assignee->id);
});

it('allows clearing a followup assignment', function () {
    $manager = actingAsFollowupRole('manager');
    $assignee = User::factory()->create(['company_id' => $manager->company_id]);
    $voiceCall = VoiceCall::factory()->create([
        'company_id' => $manager->company_id,
        'needs_human_followup' => true,
        'human_followup_assigned_to' => $assignee->id,
    ]);

    $response = $this->actingAs($manager)->patchJson("/api/v1/voice-calls/{$voiceCall->uuid}/followup", [
        'userId' => null,
    ]);

    $response->assertOk();
    expect($voiceCall->fresh()->human_followup_assigned_to)->toBeNull();
});

it('forbids an agent from assigning a followup', function () {
    $agent = actingAsFollowupRole('agent');
    $voiceCall = VoiceCall::factory()->create(['needs_human_followup' => true]);

    $this->actingAs($agent)->patchJson("/api/v1/voice-calls/{$voiceCall->uuid}/followup", [
        'userId' => null,
    ])->assertForbidden();
});

it('allows a manager to mark a followup complete', function () {
    $manager = actingAsFollowupRole('manager');
    $voiceCall = VoiceCall::factory()->create(['company_id' => $manager->company_id, 'needs_human_followup' => true]);

    $response = $this->actingAs($manager)->patchJson("/api/v1/voice-calls/{$voiceCall->uuid}/followup/complete");

    $response->assertOk();
    expect($voiceCall->fresh()->human_followup_completed_at)->not->toBeNull();
});

it('allows a user with conversations.assign permission to manage followups even without voice-agents.manage', function () {
    $agentWithAssign = actingAsFollowupRole('agent');
    $agentWithAssign->givePermissionTo('conversations.assign');
    $voiceCall = VoiceCall::factory()->create(['company_id' => $agentWithAssign->company_id, 'needs_human_followup' => true]);

    $response = $this->actingAs($agentWithAssign)->patchJson("/api/v1/voice-calls/{$voiceCall->uuid}/followup/complete");

    $response->assertOk();
});

it('404s when assigning followup on a voice call belonging to another company', function () {
    $companyA = \App\Models\Company::factory()->create();
    $companyB = \App\Models\Company::factory()->create();
    $manager = User::factory()->create(['company_id' => $companyA->id]);
    $manager->assignRole('manager');

    $foreignCall = VoiceCall::factory()->create();
    $foreignCall->company_id = $companyB->id;
    $foreignCall->save();

    $this->actingAs($manager)->getJson("/api/v1/voice-calls/{$foreignCall->uuid}")->assertNotFound();
});
