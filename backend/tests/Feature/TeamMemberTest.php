<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function actingAsTeamRole(string $role, ?Company $company = null): User
{
    $company ??= Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

it('rejects unauthenticated team listing', function () {
    $this->getJson('/api/v1/team-members')->assertUnauthorized();
});

it('forbids a non-admin from listing team members', function () {
    $user = actingAsTeamRole('manager');

    $this->actingAs($user)->getJson('/api/v1/team-members')->assertForbidden();
});

it('allows an admin to list team members', function () {
    $admin = actingAsTeamRole('admin');
    User::factory()->count(2)->create(['company_id' => $admin->company_id]);

    $this->actingAs($admin)->getJson('/api/v1/team-members')->assertOk();
});

it('scopes team member listing to the admin own company', function () {
    $admin = actingAsTeamRole('admin');
    $otherCompany = Company::factory()->create();
    User::factory()->create(['company_id' => $otherCompany->id]);

    $response = $this->actingAs($admin)->getJson('/api/v1/team-members');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('allows an admin to invite a new team member with a password', function () {
    $admin = actingAsTeamRole('admin');

    $response = $this->actingAs($admin)->postJson('/api/v1/team-members', [
        'name' => 'New Agent',
        'email' => 'new.agent@omnichat.test',
        'password' => 'password123',
        'role' => 'agent',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'New Agent');
    $response->assertJsonPath('data.email', 'new.agent@omnichat.test');
    $response->assertJsonPath('data.role', 'agent');
    $response->assertJsonPath('data.status', 'active');

    $user = User::query()->where('email', 'new.agent@omnichat.test')->first();
    expect($user)->not->toBeNull();
    expect($user->role())->toBe('agent');
    expect($user->company_id)->toBe($admin->company_id);
    expect(\Illuminate\Support\Facades\Hash::check('password123', $user->password))->toBeTrue();
});

it('forbids an admin from creating another admin via team-members', function () {
    $admin = actingAsTeamRole('admin');

    $this->actingAs($admin)->postJson('/api/v1/team-members', [
        'name' => 'Sneaky Admin',
        'email' => 'sneaky@omnichat.test',
        'password' => 'password123',
        'role' => 'admin',
    ])->assertUnprocessable();
});

it('forbids a manager from inviting a team member', function () {
    $manager = actingAsTeamRole('manager');

    $this->actingAs($manager)->postJson('/api/v1/team-members', [
        'name' => 'New Agent',
        'email' => 'blocked@omnichat.test',
        'password' => 'password123',
        'role' => 'agent',
    ])->assertForbidden();
});

it('allows an admin to change a team member role', function () {
    $admin = actingAsTeamRole('admin');
    $target = actingAsTeamRole('agent', $admin->company->fresh());

    $response = $this->actingAs($admin)->patchJson("/api/v1/team-members/{$target->uuid}/role", [
        'role' => 'manager',
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.role', 'manager');
    expect($target->fresh()->role())->toBe('manager');
});

it('prevents an admin from changing the role of a team member in another company', function () {
    $admin = actingAsTeamRole('admin');
    $target = actingAsTeamRole('agent');

    $this->actingAs($admin)->patchJson("/api/v1/team-members/{$target->uuid}/role", [
        'role' => 'manager',
    ])->assertForbidden();
});

it('allows an admin to remove a team member', function () {
    $admin = actingAsTeamRole('admin');
    $target = actingAsTeamRole('agent', $admin->company);

    $this->actingAs($admin)->deleteJson("/api/v1/team-members/{$target->uuid}")->assertNoContent();

    expect(User::query()->find($target->id))->toBeNull();
});

it('prevents an admin from removing a team member in another company', function () {
    $admin = actingAsTeamRole('admin');
    $target = actingAsTeamRole('agent');

    $this->actingAs($admin)->deleteJson("/api/v1/team-members/{$target->uuid}")->assertForbidden();

    expect(User::query()->find($target->id))->not->toBeNull();
});

it('prevents an admin from removing themselves', function () {
    $admin = actingAsTeamRole('admin');

    $this->actingAs($admin)->deleteJson("/api/v1/team-members/{$admin->uuid}")->assertStatus(422);

    expect(User::query()->find($admin->id))->not->toBeNull();
});

it('allows an agent to list assignable members for conversation assignment', function () {
    $company = Company::factory()->create();
    $agent = actingAsTeamRole('agent', $company);
    actingAsTeamRole('manager', $company);

    $response = $this->actingAs($agent)->getJson('/api/v1/assignable-members');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('scopes assignable members to the caller own company', function () {
    $company = Company::factory()->create();
    $agent = actingAsTeamRole('agent', $company);
    actingAsTeamRole('manager');

    $response = $this->actingAs($agent)->getJson('/api/v1/assignable-members');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});
