<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    config(['app.frontend_url' => env('FRONTEND_URL', 'http://localhost:5173')]);
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('reports no superadmin exists initially', function () {
    $response = $this->getJson('/api/v1/auth/bootstrap-status');

    $response->assertOk();
    $response->assertJsonPath('superadminExists', false);
});

it('reports a superadmin exists once one is created', function () {
    $user = User::factory()->create(['company_id' => null]);
    $user->assignRole('superadmin');

    $response = $this->getJson('/api/v1/auth/bootstrap-status');

    $response->assertOk();
    $response->assertJsonPath('superadminExists', true);
});

it('creates a superadmin and logs them in', function () {
    $response = $this->withHeader('Origin', config('app.frontend_url', 'http://localhost:5173'))
        ->postJson('/api/v1/auth/setup-superadmin', [
        'name' => 'Sam Superadmin',
        'email' => 'super@omnichat.test',
        'password' => 'password123',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.role', 'superadmin');
    $response->assertJsonPath('data.companyId', null);

    $user = User::query()->where('email', 'super@omnichat.test')->first();
    expect($user)->not->toBeNull();
    expect($user->company_id)->toBeNull();
    expect($user->role())->toBe('superadmin');

    $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email', 'super@omnichat.test');
});

it('refuses to create a second superadmin', function () {
    $existing = User::factory()->create(['company_id' => null]);
    $existing->assignRole('superadmin');

    $response = $this->postJson('/api/v1/auth/setup-superadmin', [
        'name' => 'Second Super',
        'email' => 'second@omnichat.test',
        'password' => 'password123',
    ]);

    $response->assertStatus(409);
    expect(User::query()->where('email', 'second@omnichat.test')->exists())->toBeFalse();
});

it('allows a superadmin to create a company', function () {
    $superadmin = User::factory()->create(['company_id' => null]);
    $superadmin->assignRole('superadmin');

    $response = $this->actingAs($superadmin)->postJson('/api/v1/companies', [
        'name' => 'Acme Inc',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Acme Inc');
    $response->assertJsonPath('data.status', 'active');
});

it('forbids a non-superadmin from creating a company', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->create(['company_id' => $company->id]);
    $admin->assignRole('admin');

    $this->actingAs($admin)->postJson('/api/v1/companies', [
        'name' => 'Acme Inc',
    ])->assertForbidden();
});

it('allows a superadmin to create an admin for a company with a working password', function () {
    $superadmin = User::factory()->create(['company_id' => null]);
    $superadmin->assignRole('superadmin');
    $company = Company::factory()->create();

    $response = $this->actingAs($superadmin)->postJson("/api/v1/companies/{$company->uuid}/admins", [
        'name' => 'Company Admin',
        'email' => 'admin@acme.test',
        'password' => 'password123',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.role', 'admin');
    $response->assertJsonPath('data.companyId', $company->uuid);

    $created = User::query()->where('email', 'admin@acme.test')->first();
    expect(\Illuminate\Support\Facades\Hash::check('password123', $created->password))->toBeTrue();
});

it('forbids a non-superadmin from creating a company admin', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->create(['company_id' => $company->id]);
    $admin->assignRole('admin');

    $this->actingAs($admin)->postJson("/api/v1/companies/{$company->uuid}/admins", [
        'name' => 'Sneaky',
        'email' => 'sneaky@acme.test',
        'password' => 'password123',
    ])->assertForbidden();
});

it('rejects login for a user in a suspended company', function () {
    $company = Company::factory()->create(['status' => 'suspended']);
    $user = User::factory()->create(['company_id' => $company->id, 'password' => bcrypt('password123')]);
    $user->assignRole('admin');

    $this->withHeader('Origin', config('app.frontend_url', 'http://localhost:5173'))
        ->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertUnprocessable();
});
