<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\PipelineStage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function actingAsCompanyRole(string $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

function pipelineStageFor(Company $company): PipelineStage
{
    return PipelineStage::query()->create([
        'id' => 'new-lead-'.$company->id,
        'company_id' => $company->id,
        'name' => 'New Lead',
        'position' => 0,
        'color' => '#3B82C4',
    ]);
}

it('lets an admin create a manager or agent scoped to their own company with a working password', function () {
    $companyA = Company::factory()->create();
    $admin = actingAsCompanyRole('admin', $companyA);

    $response = $this->actingAs($admin)->postJson('/api/v1/team-members', [
        'name' => 'New Manager',
        'email' => 'manager@companya.test',
        'password' => 'password123',
        'role' => 'manager',
    ]);

    $response->assertCreated();
    $created = User::query()->where('email', 'manager@companya.test')->first();
    expect($created->company_id)->toBe($companyA->id);
    expect(\Illuminate\Support\Facades\Hash::check('password123', $created->password))->toBeTrue();
});

it('excludes other companies from the team-members index', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $adminA = actingAsCompanyRole('admin', $companyA);
    actingAsCompanyRole('agent', $companyB);

    $response = $this->actingAs($adminA)->getJson('/api/v1/team-members');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('email'))->not->toContain(
        User::query()->where('company_id', $companyB->id)->value('email')
    );
});

it('scopes the contacts index to the caller company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = actingAsCompanyRole('manager', $companyA);

    Contact::factory()->create(['company_id' => $companyA->id, 'pipeline_stage_id' => pipelineStageFor($companyA)->id]);
    Contact::factory()->create(['company_id' => $companyB->id, 'pipeline_stage_id' => pipelineStageFor($companyB)->id]);

    $response = $this->actingAs($userA)->getJson('/api/v1/contacts');

    $response->assertOk();
    expect(collect($response->json('data')))->toHaveCount(1);
});

it('404s when accessing a contact belonging to another company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = actingAsCompanyRole('agent', $companyA);
    $foreignContact = Contact::factory()->create(['company_id' => $companyB->id, 'pipeline_stage_id' => pipelineStageFor($companyB)->id]);

    $this->actingAs($userA)->getJson("/api/v1/contacts/{$foreignContact->uuid}")->assertNotFound();
});

it('auto-assigns the caller company_id when creating a contact', function () {
    $company = Company::factory()->create();
    $user = actingAsCompanyRole('manager', $company);
    $stage = pipelineStageFor($company);

    $response = $this->actingAs($user)->postJson('/api/v1/contacts', [
        'name' => 'Fresh Contact',
        'channel' => 'whatsapp',
        'handle' => '+1 555 000 1111',
        'phone' => '+1 555 000 1111',
        'pipelineStage' => $stage->id,
    ]);

    $response->assertCreated();
    $contact = Contact::query()->where('handle', '+1 555 000 1111')->first();
    expect($contact->company_id)->toBe($company->id);
});
