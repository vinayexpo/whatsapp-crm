<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\DeliveryZone;
use App\Services\Commerce\DeliveryPricingService;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->service = app(DeliveryPricingService::class);
});

it('falls back to the branch flat delivery charge when no zones exist', function () {
    $branch = Branch::factory()->create([
        'company_id' => $this->company->id,
        'default_delivery_charge' => 5000,
    ]);

    $result = $this->service->priceFor($branch, 10000);

    expect($result['delivery_charge'])->toBe(5000);
    expect($result['zone_id'])->toBeNull();
});

it('returns zero delivery charge when the branch minimum order amount is not met and no zones exist', function () {
    $branch = Branch::factory()->create([
        'company_id' => $this->company->id,
        'default_delivery_charge' => 5000,
        'min_order_amount' => 20000,
    ]);

    $result = $this->service->priceFor($branch, 10000);

    expect($result['delivery_charge'])->toBe(0);
    expect($result['below_minimum'])->toBeTrue();
});

it('charges the zone rate when the destination is within the zone radius', function () {
    $branch = Branch::factory()->create([
        'company_id' => $this->company->id,
        'latitude' => 12.9716,
        'longitude' => 77.5946,
    ]);
    $zone = DeliveryZone::factory()->create([
        'company_id' => $this->company->id,
        'branch_id' => $branch->id,
        'radius_km' => 5,
        'delivery_charge' => 3000,
        'sort_order' => 0,
    ]);

    // ~1km away
    $result = $this->service->priceFor($branch, 10000, 12.98, 77.5946);

    expect($result['zone_id'])->toBe($zone->id);
    expect($result['delivery_charge'])->toBe(3000);
});

it('does not match a zone when the destination is outside the zone radius', function () {
    $branch = Branch::factory()->create([
        'company_id' => $this->company->id,
        'latitude' => 12.9716,
        'longitude' => 77.5946,
        'default_delivery_charge' => 9000,
    ]);
    DeliveryZone::factory()->create([
        'company_id' => $this->company->id,
        'branch_id' => $branch->id,
        'radius_km' => 2,
        'delivery_charge' => 3000,
        'sort_order' => 0,
    ]);

    // Far outside the 2km radius zone
    $result = $this->service->priceFor($branch, 10000, 13.5, 78.2);

    expect($result['zone_id'])->toBeNull();
    expect($result['delivery_charge'])->toBe(9000);
});

it('waives delivery charge once subtotal reaches the zone free delivery threshold', function () {
    $branch = Branch::factory()->create([
        'company_id' => $this->company->id,
        'latitude' => 12.9716,
        'longitude' => 77.5946,
    ]);
    $zone = DeliveryZone::factory()->create([
        'company_id' => $this->company->id,
        'branch_id' => $branch->id,
        'radius_km' => 5,
        'delivery_charge' => 3000,
        'free_delivery_threshold' => 15000,
        'sort_order' => 0,
    ]);

    $result = $this->service->priceFor($branch, 20000, 12.98, 77.5946);

    expect($result['zone_id'])->toBe($zone->id);
    expect($result['delivery_charge'])->toBe(0);
});

it('rejects orders below the zone minimum order amount', function () {
    $branch = Branch::factory()->create([
        'company_id' => $this->company->id,
        'latitude' => 12.9716,
        'longitude' => 77.5946,
    ]);
    DeliveryZone::factory()->create([
        'company_id' => $this->company->id,
        'branch_id' => $branch->id,
        'radius_km' => 5,
        'delivery_charge' => 3000,
        'min_order_amount' => 8000,
        'sort_order' => 0,
    ]);

    $result = $this->service->priceFor($branch, 5000, 12.98, 77.5946);

    expect($result['below_minimum'])->toBeTrue();
    expect($result['delivery_charge'])->toBe(0);
});

it('ignores inactive zones', function () {
    $branch = Branch::factory()->create([
        'company_id' => $this->company->id,
        'latitude' => 12.9716,
        'longitude' => 77.5946,
        'default_delivery_charge' => 7000,
    ]);
    DeliveryZone::factory()->create([
        'company_id' => $this->company->id,
        'branch_id' => $branch->id,
        'radius_km' => 5,
        'delivery_charge' => 3000,
        'is_active' => false,
        'sort_order' => 0,
    ]);

    $result = $this->service->priceFor($branch, 10000, 12.98, 77.5946);

    expect($result['zone_id'])->toBeNull();
    expect($result['delivery_charge'])->toBe(7000);
});

it('falls back to a flat zone with no radius when no coordinates are given', function () {
    $branch = Branch::factory()->create(['company_id' => $this->company->id]);
    $flatZone = DeliveryZone::factory()->create([
        'company_id' => $this->company->id,
        'branch_id' => $branch->id,
        'radius_km' => null,
        'delivery_charge' => 2500,
        'sort_order' => 0,
    ]);

    $result = $this->service->priceFor($branch, 10000);

    expect($result['zone_id'])->toBe($flatZone->id);
    expect($result['delivery_charge'])->toBe(2500);
});
