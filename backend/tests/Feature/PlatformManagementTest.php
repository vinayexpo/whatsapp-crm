<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\Contact;
use App\Models\PipelineStage;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->platformAdmin = User::factory()->create(['company_id' => null, 'password' => 'platform-password']);
    $this->platformAdmin->assignRole('superadmin');
});

function platformUserPayload(Company $company, array $overrides = []): array
{
    return [...['name' => 'Test Agent', 'email' => 'agent@example.test', 'password' => 'safe-password', 'role' => 'agent', 'status' => 'active', 'companyId' => $company->uuid], ...$overrides];
}

it('preserves shared pipeline stages and contacts belonging to another company on deletion', function () {
    $company = Company::factory()->create(['status' => 'suspended']);
    $otherCompany = Company::factory()->create();
    $stage = PipelineStage::withoutGlobalScopes()->firstOrCreate(['id' => 'shared-platform-test'], ['company_id' => $company->id, 'name' => 'Shared stage', 'position' => 1, 'color' => '#00A884']);
    $contact = Contact::factory()->create(['company_id' => $otherCompany->id, 'pipeline_stage_id' => $stage->id]);
    $this->actingAs($this->platformAdmin)->deleteJson('/api/v1/platform/companies/'.$company->uuid, ['confirmation' => $company->name, 'currentPassword' => 'platform-password'])->assertNoContent();
    expect($contact->fresh())->not->toBeNull();
    expect($stage->fresh()->company_id)->toBeNull();
});

it('filters active companies needing an administrator with a boolean query value', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->create(['company_id' => $company->id]);
    $admin->assignRole('admin');
    $missing = Company::factory()->create();
    $ids = $this->actingAs($this->platformAdmin)->getJson('/api/v1/platform/companies?needsAdmin=1')->assertOk()->json('data.*.id');
    expect($ids)->toContain($missing->uuid)->not->toContain($company->uuid);
});

it('requires authentication and rejects every tenant role from platform endpoints', function () {
    $this->getJson('/api/v1/platform/summary')->assertUnauthorized();
    foreach (['admin', 'manager', 'agent', 'branch_manager', 'staff'] as $role) {
        $user = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        $user->assignRole($role);
        $this->actingAs($user)->getJson('/api/v1/platform/summary')->assertForbidden();
        $this->postJson('/api/v1/platform/companies', ['name' => 'Forbidden'])->assertForbidden();
        $this->getJson('/api/v1/platform/users')->assertForbidden();
        $this->getJson('/api/v1/platform/activity')->assertForbidden();
    }
});

it('creates and updates companies with initialized channels and audited actions', function () {
    $this->actingAs($this->platformAdmin);
    $id = $this->postJson('/api/v1/platform/companies', ['name' => 'New Company'])->assertCreated()->json('data.id');
    $this->patchJson("/api/v1/platform/companies/{$id}", ['name' => 'Renamed Company'])->assertOk()->assertJsonPath('data.name', 'Renamed Company');
    $this->getJson("/api/v1/platform/companies/{$id}")->assertOk()->assertJsonCount(3, 'data.connections')->assertJsonMissingPath('data.connections.0.access_token');
    $this->getJson('/api/v1/platform/companies?search=Renamed&status=active')->assertOk()->assertJsonPath('meta.total', 1);
    expect(PlatformAuditLog::query()->pluck('action')->all())->toBe(['company.created', 'company.updated']);
});

it('paginates company and user searches without leaking other rows', function () {
    $before = Company::query()->count();
    Company::factory()->count(23)->create();
    $this->actingAs($this->platformAdmin)->getJson('/api/v1/platform/companies?per_page=20&page=2')->assertOk()->assertJsonCount($before + 3, 'data')->assertJsonPath('meta.total', $before + 23);
    $company = Company::factory()->create();
    User::factory()->create(['company_id' => $company->id, 'name' => 'Find Me']);
    User::factory()->create(['company_id' => Company::factory()->create()->id, 'name' => 'Find Me']);
    $this->getJson('/api/v1/platform/users?search=Find&companyId='.$company->uuid)->assertOk()->assertJsonPath('meta.total', 1);
});

it('suspends companies, revokes tokens, and blocks suspended accounts from existing sessions', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('admin');
    $user->createToken('existing-browser');
    $this->actingAs($this->platformAdmin)->patchJson('/api/v1/platform/companies/'.$company->uuid, ['status' => 'suspended'])->assertOk();
    expect($user->tokens()->count())->toBe(0);
    $this->actingAs($user)->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->actingAs($this->platformAdmin)->patchJson('/api/v1/platform/companies/'.$company->uuid, ['status' => 'active'])->assertOk();
    $this->actingAs($user->fresh())->getJson('/api/v1/auth/me')->assertOk();
});

it('deletes only suspended confirmed companies and does not orphan users or erase the audit trail', function () {
    $company = Company::factory()->create();
    $other = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('admin');
    $user->createToken('browser');
    $otherUser = User::factory()->create(['company_id' => $other->id]);
    $payload = ['confirmation' => $company->name, 'currentPassword' => 'platform-password'];
    $this->actingAs($this->platformAdmin)->deleteJson('/api/v1/platform/companies/'.$company->uuid, $payload)->assertUnprocessable();
    $company->update(['status' => 'suspended']);
    $this->deleteJson('/api/v1/platform/companies/'.$company->uuid, [...$payload, 'currentPassword' => 'wrong'])->assertUnprocessable();
    $this->deleteJson('/api/v1/platform/companies/'.$company->uuid, $payload)->assertNoContent();
    expect(User::find($user->id))->toBeNull();
    expect($user->tokens()->count())->toBe(0);
    expect(User::find($otherUser->id))->not->toBeNull();
    $log = PlatformAuditLog::query()->where('action', 'company.deleted')->sole();
    expect($log->company_id)->toBeNull()->and($log->company_name)->toBe($company->name);
});

it('creates tenant users and platform administrators and keeps their scopes separate', function () {
    $company = Company::factory()->create();
    $this->actingAs($this->platformAdmin)->postJson('/api/v1/platform/users', platformUserPayload($company))->assertCreated()->assertJsonPath('data.role', 'agent')->assertJsonPath('data.companyId', $company->uuid);
    $this->postJson('/api/v1/platform/users', ['name' => 'Second Superadmin', 'email' => 'second@example.test', 'password' => 'safe-password', 'role' => 'superadmin', 'status' => 'active'])->assertCreated()->assertJsonPath('data.companyId', null);
    $this->postJson('/api/v1/platform/users', platformUserPayload($company, ['email' => 'bad@example.test', 'role' => 'superadmin']))->assertUnprocessable()->assertJsonValidationErrors('companyId');
});

it('requires branch roles to use a branch from their own company', function () {
    $company = Company::factory()->create();
    $otherBranch = Branch::factory()->create(['company_id' => Company::factory()->create()->id]);
    $this->actingAs($this->platformAdmin)->postJson('/api/v1/platform/users', platformUserPayload($company, ['role' => 'staff']))->assertUnprocessable();
    $this->postJson('/api/v1/platform/users', platformUserPayload($company, ['role' => 'staff', 'staffBranchId' => $otherBranch->uuid]))->assertUnprocessable();
    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $this->postJson('/api/v1/platform/users', platformUserPayload($company, ['role' => 'staff', 'staffBranchId' => $branch->uuid]))->assertCreated()->assertJsonPath('data.staffBranchId', $branch->uuid);
});

it('edits users, revokes access on role changes, and rejects cross-company transfers', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('agent');
    $user->createToken('browser');
    $payload = platformUserPayload($company, ['name' => 'Updated', 'email' => $user->email, 'role' => 'manager']);
    unset($payload['password']);
    $this->actingAs($this->platformAdmin)->patchJson('/api/v1/platform/users/'.$user->uuid, $payload)->assertOk()->assertJsonPath('data.role', 'manager');
    expect($user->tokens()->count())->toBe(0);
    $this->patchJson('/api/v1/platform/users/'.$user->uuid, [...$payload, 'companyId' => Company::factory()->create()->uuid])->assertUnprocessable();
    $this->patchJson('/api/v1/platform/users/'.$user->uuid, [...$payload, 'role' => 'superadmin', 'companyId' => null])->assertUnprocessable();
});

it('protects the last active company administrator and the current superadmin', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->create(['company_id' => $company->id]);
    $admin->assignRole('admin');
    $payload = platformUserPayload($company, ['email' => $admin->email, 'role' => 'agent']);
    unset($payload['password']);
    $this->actingAs($this->platformAdmin)->patchJson('/api/v1/platform/users/'.$admin->uuid, $payload)->assertUnprocessable();
    $this->deleteJson('/api/v1/platform/users/'.$admin->uuid, ['confirmation' => $admin->email, 'currentPassword' => 'platform-password'])->assertUnprocessable();
    $this->deleteJson('/api/v1/platform/users/'.$this->platformAdmin->uuid, ['confirmation' => $this->platformAdmin->email, 'currentPassword' => 'platform-password'])->assertUnprocessable();
});

it('resets passwords with reauthentication and deletes a user without affecting their company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('agent');
    $user->createToken('browser');
    $this->actingAs($this->platformAdmin)->postJson('/api/v1/platform/users/'.$user->uuid.'/password', ['currentPassword' => 'wrong', 'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertUnprocessable();
    $this->postJson('/api/v1/platform/users/'.$user->uuid.'/password', ['currentPassword' => 'platform-password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertOk();
    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue()->and($user->tokens()->count())->toBe(0);
    $this->deleteJson('/api/v1/platform/users/'.$user->uuid, ['confirmation' => $user->email, 'currentPassword' => 'platform-password'])->assertNoContent();
    expect($company->fresh())->not->toBeNull();
});

it('blocks suspended users on login and token-authenticated requests', function () {
    $user = User::factory()->create(['suspended_at' => now(), 'password' => 'old-password']);
    $user->assignRole('agent');
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'old-password'])->assertUnprocessable();
    Sanctum::actingAs($user);
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('returns actual global usage and immutable searchable platform audit records', function () {
    $before = Company::query()->count();
    $company = Company::factory()->create();
    $this->actingAs($this->platformAdmin)->getJson('/api/v1/platform/summary')->assertOk()->assertJsonPath('data.companies', $before + 1)->assertJsonPath('data.companiesWithoutAdmin', $before + 1);
    $this->patchJson('/api/v1/platform/companies/'.$company->uuid, ['status' => 'suspended'])->assertOk();
    $this->getJson('/api/v1/platform/activity?search=suspended&action=company.suspended')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.action', 'company.suspended');
    $uuid = PlatformAuditLog::query()->sole()->uuid;
    $this->deleteJson('/api/v1/platform/activity/'.$uuid)->assertNotFound();
});

it('allows secure own-account changes and preserves the current bearer token', function () {
    $token = $this->platformAdmin->createToken('current');
    $this->platformAdmin->createToken('other-browser');
    $this->withToken($token->plainTextToken)->patchJson('/api/v1/platform/profile', ['name' => 'Updated Owner', 'email' => 'owner@example.test', 'currentPassword' => 'platform-password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertOk()->assertJsonPath('data.name', 'Updated Owner');
    expect($this->platformAdmin->tokens()->count())->toBe(1);
    expect(Hash::check('new-password', $this->platformAdmin->fresh()->password))->toBeTrue();
});
