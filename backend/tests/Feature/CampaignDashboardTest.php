<?php

use App\Models\Campaign;
use App\Models\Company;
use App\Models\DailyMetric;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function actingAsDashboardRole(string $role, ?Company $company = null): User
{
    $company ??= Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

it('requires authentication for the campaign dashboard', function () {
    $this->getJson('/api/v1/campaigns/dashboard')->assertUnauthorized();
});

it('returns aggregated totals across the company campaigns', function () {
    $admin = actingAsDashboardRole('admin');

    Campaign::factory()->create([
        'company_id' => $admin->company_id,
        'recipient_count' => 100,
        'delivered_count' => 80,
        'read_count' => 40,
        'replied_count' => 10,
    ]);
    Campaign::factory()->create([
        'company_id' => $admin->company_id,
        'recipient_count' => 50,
        'delivered_count' => 50,
        'read_count' => 25,
        'replied_count' => 5,
    ]);

    $response = $this->actingAs($admin)->getJson('/api/v1/campaigns/dashboard')->assertOk();

    $totals = $response->json('data.totals');
    expect($totals['campaignCount'])->toBe(2)
        ->and($totals['recipientCount'])->toBe(150)
        ->and($totals['deliveredCount'])->toBe(130)
        ->and($totals['readCount'])->toBe(65)
        ->and($totals['repliedCount'])->toBe(15);
});

it('excludes another companys campaigns from the dashboard', function () {
    $admin = actingAsDashboardRole('admin');
    $otherCompany = Company::factory()->create();

    Campaign::factory()->create(['company_id' => $admin->company_id, 'recipient_count' => 20, 'delivered_count' => 20]);
    Campaign::factory()->create(['company_id' => $otherCompany->id, 'recipient_count' => 999, 'delivered_count' => 999]);

    $response = $this->actingAs($admin)->getJson('/api/v1/campaigns/dashboard')->assertOk();

    expect($response->json('data.totals.recipientCount'))->toBe(20);
});

it('filters campaigns by created_at date range', function () {
    $admin = actingAsDashboardRole('admin');

    Campaign::factory()->create([
        'company_id' => $admin->company_id,
        'recipient_count' => 10,
        'created_at' => now()->subDays(10),
    ]);
    Campaign::factory()->create([
        'company_id' => $admin->company_id,
        'recipient_count' => 30,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/v1/campaigns/dashboard?from='.now()->subDay()->toDateString())
        ->assertOk();

    expect($response->json('data.totals.campaignCount'))->toBe(1)
        ->and($response->json('data.totals.recipientCount'))->toBe(30);
});

it('excludes zero-recipient campaigns from top and bottom performer rankings', function () {
    $admin = actingAsDashboardRole('admin');

    Campaign::factory()->create([
        'company_id' => $admin->company_id,
        'recipient_count' => 0,
        'delivered_count' => 0,
    ]);
    Campaign::factory()->create([
        'company_id' => $admin->company_id,
        'recipient_count' => 10,
        'delivered_count' => 8,
    ]);

    $response = $this->actingAs($admin)->getJson('/api/v1/campaigns/dashboard')->assertOk();

    expect($response->json('data.topPerformers'))->toHaveCount(1)
        ->and($response->json('data.bottomPerformers'))->toHaveCount(1);
});

it('returns a daily trend series from daily metrics', function () {
    $admin = actingAsDashboardRole('admin');

    DailyMetric::factory()->create([
        'company_id' => $admin->company_id,
        'date' => now()->toDateString(),
        'whatsapp_sent' => 10,
        'instagram_sent' => 5,
        'delivered' => 12,
        'read' => 6,
        'replied' => 2,
    ]);

    $response = $this->actingAs($admin)->getJson('/api/v1/campaigns/dashboard')->assertOk();

    $trend = $response->json('data.trend');
    expect($trend)->toHaveCount(1)
        ->and($trend[0]['sent'])->toBe(15)
        ->and($trend[0]['delivered'])->toBe(12);
});

it('forbids a non-privileged role from viewing the dashboard', function () {
    $agent = actingAsDashboardRole('agent');

    $this->actingAs($agent)->getJson('/api/v1/campaigns/dashboard')->assertForbidden();
});
