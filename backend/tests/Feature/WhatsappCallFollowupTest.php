<?php

use App\Models\Company;
use App\Models\User;
use App\Models\WhatsappCall;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

function actingAsWhatsappCallRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('rejects unauthenticated call listing', function () {
    $this->getJson('/api/v1/whatsapp-calls')->assertUnauthorized();
});

it('forbids an agent from listing whatsapp calls', function () {
    $agent = actingAsWhatsappCallRole('agent');

    $this->actingAs($agent)->getJson('/api/v1/whatsapp-calls')->assertForbidden();
});

it('lists whatsapp calls needing human followup', function () {
    $manager = actingAsWhatsappCallRole('manager');
    WhatsappCall::factory()->create(['company_id' => $manager->company_id, 'needs_human_followup' => true, 'human_followup_completed_at' => null]);
    WhatsappCall::factory()->create(['company_id' => $manager->company_id, 'needs_human_followup' => false]);
    WhatsappCall::factory()->create(['company_id' => $manager->company_id, 'needs_human_followup' => true, 'human_followup_completed_at' => now()]);

    $response = $this->actingAs($manager)->getJson('/api/v1/whatsapp-calls?needsHumanFollowup=true');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('filters whatsapp calls by status', function () {
    $manager = actingAsWhatsappCallRole('manager');
    WhatsappCall::factory()->create(['company_id' => $manager->company_id, 'status' => 'completed']);
    WhatsappCall::factory()->create(['company_id' => $manager->company_id, 'status' => 'missed']);

    $response = $this->actingAs($manager)->getJson('/api/v1/whatsapp-calls?status=missed');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('allows a manager to assign a followup to a team member', function () {
    $manager = actingAsWhatsappCallRole('manager');
    $assignee = User::factory()->create(['company_id' => $manager->company_id]);
    $whatsappCall = WhatsappCall::factory()->create(['company_id' => $manager->company_id, 'needs_human_followup' => true]);

    $response = $this->actingAs($manager)->patchJson("/api/v1/whatsapp-calls/{$whatsappCall->uuid}/followup", [
        'userId' => $assignee->uuid,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.humanFollowupAssignedTo.id', $assignee->uuid);

    expect($whatsappCall->fresh()->human_followup_assigned_to)->toBe($assignee->id);
});

it('allows clearing a followup assignment', function () {
    $manager = actingAsWhatsappCallRole('manager');
    $assignee = User::factory()->create(['company_id' => $manager->company_id]);
    $whatsappCall = WhatsappCall::factory()->create([
        'company_id' => $manager->company_id,
        'needs_human_followup' => true,
        'human_followup_assigned_to' => $assignee->id,
    ]);

    $response = $this->actingAs($manager)->patchJson("/api/v1/whatsapp-calls/{$whatsappCall->uuid}/followup", [
        'userId' => null,
    ]);

    $response->assertOk();
    expect($whatsappCall->fresh()->human_followup_assigned_to)->toBeNull();
});

it('forbids an agent from assigning a followup', function () {
    $agent = actingAsWhatsappCallRole('agent');
    $whatsappCall = WhatsappCall::factory()->create(['company_id' => $agent->company_id, 'needs_human_followup' => true]);

    $this->actingAs($agent)->patchJson("/api/v1/whatsapp-calls/{$whatsappCall->uuid}/followup", [
        'userId' => null,
    ])->assertForbidden();
});

it('allows a manager to mark a followup complete', function () {
    $manager = actingAsWhatsappCallRole('manager');
    $whatsappCall = WhatsappCall::factory()->create(['company_id' => $manager->company_id, 'needs_human_followup' => true]);

    $response = $this->actingAs($manager)->patchJson("/api/v1/whatsapp-calls/{$whatsappCall->uuid}/followup/complete");

    $response->assertOk();
    expect($whatsappCall->fresh()->human_followup_completed_at)->not->toBeNull();
});

it('allows a user with conversations.assign permission to manage followups even without whatsapp-calling.manage', function () {
    $agentWithAssign = actingAsWhatsappCallRole('agent');
    $agentWithAssign->givePermissionTo('conversations.assign');
    $whatsappCall = WhatsappCall::factory()->create(['company_id' => $agentWithAssign->company_id, 'needs_human_followup' => true]);

    $response = $this->actingAs($agentWithAssign)->patchJson("/api/v1/whatsapp-calls/{$whatsappCall->uuid}/followup/complete");

    $response->assertOk();
});

it('404s when assigning followup on a whatsapp call belonging to another company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $manager = User::factory()->create(['company_id' => $companyA->id]);
    $manager->assignRole('manager');

    $foreignCall = WhatsappCall::factory()->create();
    $foreignCall->company_id = $companyB->id;
    $foreignCall->save();

    $this->actingAs($manager)->getJson("/api/v1/whatsapp-calls/{$foreignCall->uuid}")->assertNotFound();
});
