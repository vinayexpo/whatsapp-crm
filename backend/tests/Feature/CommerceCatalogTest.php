<?php

use App\Models\AddonDefinition;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function actingAsCommerceRole(string $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => ($company ?? Company::factory()->create())->id]);
    $user->assignRole($role);

    return $user;
}

// Branches

it('rejects unauthenticated branch listing', function () {
    $this->getJson('/api/v1/commerce/branches')->assertUnauthorized();
});

it('allows an admin to create a branch', function () {
    $admin = actingAsCommerceRole('admin');

    $response = $this->actingAs($admin)->postJson('/api/v1/commerce/branches', [
        'name' => 'Downtown',
        'slug' => 'downtown',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Downtown')
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('branches', ['name' => 'Downtown', 'company_id' => $admin->company_id]);
});

it('forbids an agent from creating a branch', function () {
    $agent = actingAsCommerceRole('agent');

    $this->actingAs($agent)->postJson('/api/v1/commerce/branches', [
        'name' => 'Downtown',
        'slug' => 'downtown',
    ])->assertForbidden();
});

it('scopes branches to the caller company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = actingAsCommerceRole('manager', $companyA);

    Branch::factory()->create(['company_id' => $companyA->id]);
    Branch::factory()->create(['company_id' => $companyB->id]);

    $response = $this->actingAs($userA)->getJson('/api/v1/commerce/branches');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('404s when accessing a branch belonging to another company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = actingAsCommerceRole('manager', $companyA);
    $foreignBranch = Branch::factory()->create(['company_id' => $companyB->id]);

    $this->actingAs($userA)->getJson("/api/v1/commerce/branches/{$foreignBranch->uuid}")->assertNotFound();
});

// Categories

it('allows a manager to create a category with a parent', function () {
    $manager = actingAsCommerceRole('manager');
    $parent = Category::factory()->create(['company_id' => $manager->company_id]);

    $response = $this->actingAs($manager)->postJson('/api/v1/commerce/categories', [
        'name' => 'Beverages',
        'slug' => 'beverages',
        'parentId' => $parent->uuid,
    ]);

    $response->assertCreated()->assertJsonPath('data.parentId', $parent->uuid);
});

// Products + variants + addons

it('allows a manager to create a product with a category', function () {
    $manager = actingAsCommerceRole('manager');
    $category = Category::factory()->create(['company_id' => $manager->company_id]);

    $response = $this->actingAs($manager)->postJson('/api/v1/commerce/products', [
        'name' => 'Chicken Biryani',
        'basePrice' => 22000,
        'categoryId' => $category->uuid,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Chicken Biryani')
        ->assertJsonPath('data.basePrice', 22000);
});

it('allows attaching a variant to a product', function () {
    $manager = actingAsCommerceRole('manager');
    $product = Product::factory()->create(['company_id' => $manager->company_id]);

    $response = $this->actingAs($manager)->postJson("/api/v1/commerce/products/{$product->uuid}/variants", [
        'name' => 'Large',
        'priceDelta' => 5000,
    ]);

    $response->assertCreated()->assertJsonPath('data.name', 'Large');
});

it('attaches shared addon definitions to multiple products via the pivot', function () {
    $manager = actingAsCommerceRole('manager');
    $productA = Product::factory()->create(['company_id' => $manager->company_id]);
    $productB = Product::factory()->create(['company_id' => $manager->company_id]);
    $addon = AddonDefinition::factory()->create(['company_id' => $manager->company_id, 'name' => 'Extra Cheese']);

    $this->actingAs($manager)->putJson("/api/v1/commerce/products/{$productA->uuid}/addons", [
        'addonDefinitionIds' => [$addon->uuid],
    ])->assertOk();

    $this->actingAs($manager)->putJson("/api/v1/commerce/products/{$productB->uuid}/addons", [
        'addonDefinitionIds' => [$addon->uuid],
    ])->assertOk();

    expect($addon->fresh()->products()->count())->toBe(2);
});

it('scopes products to the caller company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = actingAsCommerceRole('manager', $companyA);

    Product::factory()->create(['company_id' => $companyA->id]);
    Product::factory()->create(['company_id' => $companyB->id]);

    $response = $this->actingAs($userA)->getJson('/api/v1/commerce/products');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

// Branch-level pricing/availability

it('sets a branch-specific price override for a product', function () {
    $manager = actingAsCommerceRole('manager');
    $branch = Branch::factory()->create(['company_id' => $manager->company_id]);
    $product = Product::factory()->create(['company_id' => $manager->company_id, 'base_price' => 10000]);

    $response = $this->actingAs($manager)->putJson(
        "/api/v1/commerce/branches/{$branch->uuid}/products/{$product->uuid}/price",
        ['price' => 12000]
    );

    $response->assertOk()->assertJsonPath('data.price', 12000);

    $this->assertDatabaseHas('branch_prices', [
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'price' => 12000,
    ]);
});

it('sets branch product availability', function () {
    $manager = actingAsCommerceRole('manager');
    $branch = Branch::factory()->create(['company_id' => $manager->company_id]);
    $product = Product::factory()->create(['company_id' => $manager->company_id]);

    $response = $this->actingAs($manager)->putJson(
        "/api/v1/commerce/branches/{$branch->uuid}/products/{$product->uuid}/availability",
        ['isAvailable' => false]
    );

    $response->assertOk()->assertJsonPath('data.isAvailable', false);
});

// Inventory

it('upserts inventory for a branch/product pair', function () {
    $manager = actingAsCommerceRole('manager');
    $branch = Branch::factory()->create(['company_id' => $manager->company_id]);
    $product = Product::factory()->create(['company_id' => $manager->company_id]);

    $response = $this->actingAs($manager)->postJson('/api/v1/commerce/inventory', [
        'branchId' => $branch->uuid,
        'productId' => $product->uuid,
        'stockQuantity' => 50,
        'trackStock' => true,
    ]);

    $response->assertCreated()->assertJsonPath('data.stockQuantity', 50);

    $this->assertDatabaseHas('inventory', [
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'stock_quantity' => 50,
    ]);
});

it('scopes inventory to the caller company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = actingAsCommerceRole('manager', $companyA);

    Inventory::factory()->create([
        'company_id' => $companyA->id,
        'branch_id' => Branch::factory()->create(['company_id' => $companyA->id]),
        'product_id' => Product::factory()->create(['company_id' => $companyA->id]),
    ]);
    Inventory::factory()->create([
        'company_id' => $companyB->id,
        'branch_id' => Branch::factory()->create(['company_id' => $companyB->id]),
        'product_id' => Product::factory()->create(['company_id' => $companyB->id]),
    ]);

    $response = $this->actingAs($userA)->getJson('/api/v1/commerce/inventory');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});
