<?php

use App\Models\ApiConnection;
use App\Models\CommerceSetting;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\CatalogDriverResolver;
use App\Services\Catalog\FakeMetaCatalogService;
use App\Services\Catalog\GraphApiCatalogService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function actingAsCatalogRole(string $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => ($company ?? Company::factory()->create())->id]);
    $user->assignRole($role);

    return $user;
}

it('rejects sync when no meta catalog id is configured', function () {
    $admin = actingAsCatalogRole('admin');

    $this->actingAs($admin)->postJson('/api/v1/commerce/products/sync-meta')
        ->assertStatus(422);
});

it('rejects sync for a user without catalog.manage permission', function () {
    $agent = actingAsCatalogRole('agent');

    CommerceSetting::factory()->create([
        'company_id' => $agent->company_id,
        'meta_catalog_id' => 'catalog-123',
    ]);

    $this->actingAs($agent)->postJson('/api/v1/commerce/products/sync-meta')
        ->assertForbidden();
});

it('creates new products from fake catalog data when no matching sku exists', function () {
    $admin = actingAsCatalogRole('admin');

    CommerceSetting::factory()->create([
        'company_id' => $admin->company_id,
        'meta_catalog_id' => 'catalog-123',
    ]);

    $response = $this->actingAs($admin)->postJson('/api/v1/commerce/products/sync-meta');

    $response->assertOk()
        ->assertJsonPath('data.created', 2)
        ->assertJsonPath('data.updated', 0)
        ->assertJsonPath('data.total', 2);

    $this->assertDatabaseHas('products', [
        'company_id' => $admin->company_id,
        'sku' => 'fake-sku-001',
        'meta_retailer_id' => 'fake-sku-001',
    ]);
});

it('updates an existing product matched by sku instead of duplicating', function () {
    $admin = actingAsCatalogRole('admin');

    CommerceSetting::factory()->create([
        'company_id' => $admin->company_id,
        'meta_catalog_id' => 'catalog-123',
    ]);

    $existing = Product::factory()->create([
        'company_id' => $admin->company_id,
        'sku' => 'fake-sku-001',
        'name' => 'Old Name',
        'base_price' => 1,
    ]);

    $response = $this->actingAs($admin)->postJson('/api/v1/commerce/products/sync-meta');

    $response->assertOk()
        ->assertJsonPath('data.created', 1)
        ->assertJsonPath('data.updated', 1);

    expect(Product::query()->where('company_id', $admin->company_id)->where('sku', 'fake-sku-001')->count())->toBe(1);

    $existing->refresh();
    expect($existing->name)->toBe('Sample Product One');
    expect($existing->base_price)->toBe(129900);
});

it('parses meta price strings into integer minor units', function () {
    $connection = ApiConnection::factory()->connected()->create(['channel' => 'whatsapp']);

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => [
                [
                    'id' => 'meta-1',
                    'retailer_id' => 'sku-a',
                    'name' => 'Widget',
                    'description' => 'A widget',
                    'price' => '12.99 USD',
                    'availability' => 'in stock',
                    'image_url' => 'https://example.com/w.jpg',
                ],
            ],
        ], 200),
    ]);

    $service = new GraphApiCatalogService();
    $items = $service->fetchProducts($connection, 'catalog-abc');

    expect($items[0]['price_minor'])->toBe(1299);
});

it('stamps meta_retailer_id and meta_synced_at on synced rows', function () {
    $admin = actingAsCatalogRole('admin');

    CommerceSetting::factory()->create([
        'company_id' => $admin->company_id,
        'meta_catalog_id' => 'catalog-123',
    ]);

    $this->actingAs($admin)->postJson('/api/v1/commerce/products/sync-meta')->assertOk();

    $product = Product::query()->where('company_id', $admin->company_id)->where('sku', 'fake-sku-001')->first();

    expect($product->meta_retailer_id)->toBe('fake-sku-001');
    expect($product->meta_synced_at)->not->toBeNull();
});

it('keeps sync tenant-isolated even with colliding skus', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $adminA = actingAsCatalogRole('admin', $companyA);

    CommerceSetting::factory()->create([
        'company_id' => $companyA->id,
        'meta_catalog_id' => 'catalog-123',
    ]);

    $foreignProduct = Product::factory()->create([
        'company_id' => $companyB->id,
        'sku' => 'fake-sku-001',
        'name' => 'Should Not Change',
    ]);

    $this->actingAs($adminA)->postJson('/api/v1/commerce/products/sync-meta')->assertOk();

    $foreignProduct->refresh();
    expect($foreignProduct->name)->toBe('Should Not Change');
});

it('resolves the fake driver when no connected api connection exists and the real driver when one does', function () {
    $resolver = new CatalogDriverResolver();

    expect($resolver->forConnection(null))->toBeInstanceOf(FakeMetaCatalogService::class);

    $disconnected = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    expect($resolver->forConnection($disconnected))->toBeInstanceOf(FakeMetaCatalogService::class);

    $connected = ApiConnection::factory()->connected()->create(['channel' => 'whatsapp']);
    expect($resolver->forConnection($connected))->toBeInstanceOf(GraphApiCatalogService::class);
});

it('syncs successfully via the fake driver even without a whatsapp connection configured', function () {
    $admin = actingAsCatalogRole('admin');

    CommerceSetting::factory()->create([
        'company_id' => $admin->company_id,
        'meta_catalog_id' => 'catalog-123',
    ]);

    expect(ApiConnection::query()->where('company_id', $admin->company_id)->exists())->toBeFalse();

    $this->actingAs($admin)->postJson('/api/v1/commerce/products/sync-meta')
        ->assertOk()
        ->assertJsonPath('data.total', 2);
});
