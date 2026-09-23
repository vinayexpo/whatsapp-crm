<?php

use App\Models\BusinessType;
use App\Models\Company;
use App\Models\OrderStatus;
use App\Services\Commerce\OrderStatusProvisioningService;
use Database\Seeders\BusinessTypeSeeder;

beforeEach(function () {
    $this->seed(BusinessTypeSeeder::class);
});

it('creates system-default order statuses from a business type template', function () {
    $businessType = BusinessType::query()->where('slug', 'restaurant')->firstOrFail();

    app(OrderStatusProvisioningService::class)->ensureSystemDefaults($businessType);

    $defaults = OrderStatus::query()
        ->whereNull('company_id')
        ->where('business_type_id', $businessType->id)
        ->orderBy('sort_order')
        ->get();

    expect($defaults)->toHaveCount(6);
    expect($defaults->first()->slug)->toBe('pending');
    expect($defaults->last()->slug)->toBe('cancelled');
    expect($defaults->last()->is_terminal)->toBeTrue();

    $pending = $defaults->firstWhere('slug', 'pending');
    $confirmed = $defaults->firstWhere('slug', 'confirmed');
    $cancelled = $defaults->firstWhere('slug', 'cancelled');

    expect($pending->allowed_next_status_ids)->toContain($confirmed->id);
    expect($pending->allowed_next_status_ids)->toContain($cancelled->id);
});

it('is idempotent when system defaults already exist', function () {
    $businessType = BusinessType::query()->where('slug', 'restaurant')->firstOrFail();
    $service = app(OrderStatusProvisioningService::class);

    $service->ensureSystemDefaults($businessType);
    $countAfterFirst = OrderStatus::query()->whereNull('company_id')->count();

    $service->ensureSystemDefaults($businessType);
    $countAfterSecond = OrderStatus::query()->whereNull('company_id')->count();

    expect($countAfterSecond)->toBe($countAfterFirst);
});

it('clones system-default statuses into company-owned rows preserving transitions', function () {
    $businessType = BusinessType::query()->where('slug', 'restaurant')->firstOrFail();
    $company = Company::factory()->create();

    app(OrderStatusProvisioningService::class)->provisionForCompany($company->id, $businessType);

    $companyStatuses = OrderStatus::query()
        ->where('company_id', $company->id)
        ->orderBy('sort_order')
        ->get();

    expect($companyStatuses)->toHaveCount(6);

    $pending = $companyStatuses->firstWhere('slug', 'pending');
    $confirmed = $companyStatuses->firstWhere('slug', 'confirmed');

    expect($pending->allowed_next_status_ids)->toContain($confirmed->id);

    // Cloned ids must be company-scoped, not accidentally pointing at the
    // shared system-default rows.
    $systemDefaultIds = OrderStatus::query()->whereNull('company_id')->pluck('id');
    expect($systemDefaultIds->intersect(collect($pending->allowed_next_status_ids)))->toBeEmpty();
});

it('does not re-provision a company that already has order statuses', function () {
    $businessType = BusinessType::query()->where('slug', 'restaurant')->firstOrFail();
    $company = Company::factory()->create();
    $service = app(OrderStatusProvisioningService::class);

    $service->provisionForCompany($company->id, $businessType);
    $countAfterFirst = OrderStatus::query()->where('company_id', $company->id)->count();

    $service->provisionForCompany($company->id, $businessType);
    $countAfterSecond = OrderStatus::query()->where('company_id', $company->id)->count();

    expect($countAfterSecond)->toBe($countAfterFirst);
});

it('provisions order statuses when a company sets its business type via commerce settings', function () {
    $this->seed(Database\Seeders\RolesAndPermissionsSeeder::class);

    $company = Company::factory()->create();
    $businessType = BusinessType::query()->where('slug', 'grocery')->firstOrFail();
    $admin = App\Models\User::factory()->create(['company_id' => $company->id]);
    $admin->assignRole('admin');

    $this->actingAs($admin)->patchJson('/api/v1/commerce/settings', [
        'businessTypeId' => $businessType->id,
    ])->assertOk();

    expect(OrderStatus::query()->where('company_id', $company->id)->count())->toBe(6);
});
