<?php

use App\Models\BusinessType;
use App\Models\Company;
use App\Models\OrderStatus;
use App\Models\User;
use App\Models\WhatsappTemplate;
use App\Services\Commerce\OrderStatusProvisioningService;
use Database\Seeders\BusinessTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(BusinessTypeSeeder::class);
});

function provisionedOrderStatusCompany(): Company
{
    $company = Company::factory()->create();
    $businessType = BusinessType::query()->where('slug', 'restaurant')->firstOrFail();
    app(OrderStatusProvisioningService::class)->provisionForCompany($company->id, $businessType);

    return $company;
}

it('rejects unauthenticated order status listing', function () {
    $this->getJson('/api/v1/commerce/order-statuses')->assertUnauthorized();
});

it('lists order statuses scoped to the requesting company', function () {
    $company = provisionedOrderStatusCompany();
    $otherCompany = provisionedOrderStatusCompany();

    $admin = User::factory()->create(['company_id' => $company->id]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->getJson('/api/v1/commerce/order-statuses')->assertOk();

    expect($response->json('data'))->toHaveCount(6);
    $companyIds = OrderStatus::query()->where('company_id', $otherCompany->id)->pluck('uuid');
    $returnedIds = collect($response->json('data'))->pluck('id');
    expect($returnedIds->intersect($companyIds))->toBeEmpty();
});

it('forbids a manager without commerce-settings.manage from updating an order status', function () {
    $company = provisionedOrderStatusCompany();
    $manager = User::factory()->create(['company_id' => $company->id]);
    $manager->assignRole('agent');

    $status = OrderStatus::query()->where('company_id', $company->id)->where('slug', 'pending')->firstOrFail();

    $this->actingAs($manager)->patchJson("/api/v1/commerce/order-statuses/{$status->uuid}", [
        'label' => 'Awaiting confirmation',
    ])->assertForbidden();
});

it('allows an admin to update a status label and notify flag', function () {
    $company = provisionedOrderStatusCompany();
    $admin = User::factory()->create(['company_id' => $company->id]);
    $admin->assignRole('admin');

    $status = OrderStatus::query()->where('company_id', $company->id)->where('slug', 'pending')->firstOrFail();

    $response = $this->actingAs($admin)->patchJson("/api/v1/commerce/order-statuses/{$status->uuid}", [
        'label' => 'Awaiting confirmation',
        'notifyCustomer' => true,
    ])->assertOk();

    expect($response->json('data.label'))->toBe('Awaiting confirmation');
    expect($response->json('data.notifyCustomer'))->toBeTrue();
    expect($status->fresh()->label)->toBe('Awaiting confirmation');
});

it('allows an admin to attach a notification template and remap allowed transitions', function () {
    $company = provisionedOrderStatusCompany();
    $admin = User::factory()->create(['company_id' => $company->id]);
    $admin->assignRole('admin');

    $template = WhatsappTemplate::factory()->create(['company_id' => $company->id]);

    $pending = OrderStatus::query()->where('company_id', $company->id)->where('slug', 'pending')->firstOrFail();
    $cancelled = OrderStatus::query()->where('company_id', $company->id)->where('slug', 'cancelled')->firstOrFail();

    $response = $this->actingAs($admin)->patchJson("/api/v1/commerce/order-statuses/{$pending->uuid}", [
        'notificationTemplateId' => $template->uuid,
        'allowedNextStatusIds' => [$cancelled->uuid],
    ])->assertOk();

    expect($response->json('data.notificationTemplateId'))->toBe($template->uuid);
    expect($response->json('data.allowedNextStatusIds'))->toBe([$cancelled->uuid]);
    expect($pending->fresh()->allowed_next_status_ids)->toBe([$cancelled->id]);
});

it('prevents updating an order status belonging to another company', function () {
    $company = provisionedOrderStatusCompany();
    $otherCompany = provisionedOrderStatusCompany();

    $admin = User::factory()->create(['company_id' => $company->id]);
    $admin->assignRole('admin');

    $otherStatus = OrderStatus::query()->where('company_id', $otherCompany->id)->where('slug', 'pending')->firstOrFail();

    $this->actingAs($admin)->patchJson("/api/v1/commerce/order-statuses/{$otherStatus->uuid}", [
        'label' => 'Hijacked',
    ])->assertNotFound();
});
