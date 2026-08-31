<?php

use App\Models\Campaign;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

function actingAsSummaryRole(string $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

it('returns real aggregate counts beyond the 100-record pagination cap', function () {
    $company = Company::factory()->create();
    $user = actingAsSummaryRole('admin', $company);

    Contact::factory()->count(120)->create(['company_id' => $company->id, 'pipeline_stage_id' => 'new-lead']);
    Contact::factory()->count(5)->create(['company_id' => $company->id, 'pipeline_stage_id' => 'won', 'deal_value' => 1000]);
    Contact::factory()->count(3)->create(['company_id' => $company->id, 'pipeline_stage_id' => 'lost']);

    Conversation::factory()->count(110)->create(['company_id' => $company->id, 'status' => 'open']);
    Conversation::factory()->count(2)->create(['company_id' => $company->id, 'status' => 'resolved']);

    Campaign::factory()->count(101)->create(['company_id' => $company->id, 'status' => 'active']);

    $response = $this->actingAs($user)->getJson('/api/v1/analytics/dashboard-summary');

    $response->assertOk();
    $data = $response->json('data');

    expect($data['totalContacts'])->toBe(128);
    expect($data['activeLeads'])->toBe(120);
    expect($data['wonValue'])->toBe(5000);
    expect($data['openChats'])->toBe(110);
    expect($data['activeCampaigns'])->toBe(101);
    expect($data['totalCampaigns'])->toBe(101);
});

it('only aggregates records within the authenticated company', function () {
    $company = Company::factory()->create();
    $user = actingAsSummaryRole('admin', $company);
    Contact::factory()->create(['company_id' => $company->id, 'pipeline_stage_id' => 'new-lead']);

    $otherCompany = Company::factory()->create();
    Contact::factory()->count(5)->create(['company_id' => $otherCompany->id, 'pipeline_stage_id' => 'new-lead']);

    $response = $this->actingAs($user)->getJson('/api/v1/analytics/dashboard-summary');

    $response->assertOk();
    expect($response->json('data.totalContacts'))->toBe(1);
});
