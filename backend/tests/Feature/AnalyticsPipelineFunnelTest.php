<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

function actingAsAnalyticsRole(string $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

it('returns contact counts grouped by pipeline stage', function () {
    $company = Company::factory()->create();
    $user = actingAsAnalyticsRole('admin', $company);

    Contact::factory()->count(2)->create(['company_id' => $company->id, 'pipeline_stage_id' => 'new-lead']);
    Contact::factory()->create(['company_id' => $company->id, 'pipeline_stage_id' => 'qualified']);
    Contact::factory()->create(['company_id' => $company->id, 'pipeline_stage_id' => 'lost']);

    $response = $this->actingAs($user)->getJson('/api/v1/analytics/pipeline-funnel');

    $response->assertOk();
    $counts = collect($response->json('data'))->pluck('count', 'stage');

    expect($counts->get('new-lead'))->toBe(2);
    expect($counts->get('qualified'))->toBe(1);
    expect($counts->get('lost'))->toBe(1);
});

it('only counts contacts within the authenticated company', function () {
    $company = Company::factory()->create();
    $user = actingAsAnalyticsRole('admin', $company);
    Contact::factory()->create(['company_id' => $company->id, 'pipeline_stage_id' => 'new-lead']);

    $otherCompany = Company::factory()->create();
    Contact::factory()->create(['company_id' => $otherCompany->id, 'pipeline_stage_id' => 'new-lead']);

    $response = $this->actingAs($user)->getJson('/api/v1/analytics/pipeline-funnel');

    $response->assertOk();
    $counts = collect($response->json('data'))->pluck('count', 'stage');

    expect($counts->get('new-lead'))->toBe(1);
});
