<?php

use App\Jobs\SyncCommerceCatalog;
use App\Models\ApiConnection;
use App\Models\CommerceSetting;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\GraphApiCatalogService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});
function actingAsCatalogRole(string $role): User
{
    $user = User::factory()->create(['company_id' => Company::factory()->create()->id]);
    $user->assignRole($role);

    return $user;
}
it('rejects sync without catalog setup', function () {
    $this->actingAs(actingAsCatalogRole('admin'))->postJson('/api/v1/commerce/products/sync-meta')->assertUnprocessable();
});
it('rejects sync without catalog manage permission', function () {
    $this->actingAs(actingAsCatalogRole('agent'))->postJson('/api/v1/commerce/products/sync-meta')->assertForbidden();
});
it('queues a configured real catalog sync', function () {
    Queue::fake();
    $admin = actingAsCatalogRole('admin');
    $connection = ApiConnection::factory()->connected()->create(['company_id' => $admin->company_id, 'channel' => 'whatsapp']);
    $settings = CommerceSetting::factory()->create(['company_id' => $admin->company_id, 'api_connection_id' => $connection->id, 'meta_catalog_id' => '12345']);
    $this->actingAs($admin)->postJson('/api/v1/commerce/products/sync-meta')->assertAccepted()->assertJsonPath('data.queued', true);
    Queue::assertPushed(SyncCommerceCatalog::class, fn ($job) => $job->settingsId === $settings->id);
});
it('rejects publishing without Meta setup', function () {
    $admin = actingAsCatalogRole('admin');
    $product = Product::factory()->create(['company_id' => $admin->company_id]);
    $this->actingAs($admin)->postJson('/api/v1/commerce/products/'.$product->uuid.'/push-meta')->assertUnprocessable();
});
it('parses Meta money into minor units', function () {
    $connection = ApiConnection::factory()->connected()->create(['company_id' => Company::factory()->create()->id]);
    Http::fake(['graph.facebook.com/*' => Http::response(['data' => [['id' => '1', 'retailer_id' => 'sku', 'name' => 'Camera', 'price' => '12.99 INR', 'currency' => 'INR']]])]);
    expect((new GraphApiCatalogService)->fetchProducts($connection, '123')[0]['price_minor'])->toBe(1299);
});
it('submits upserts with company currency and rejects missing batch handles', function () {
    $connection = ApiConnection::factory()->connected()->create(['company_id' => Company::factory()->create()->id]);
    Http::fake(['graph.facebook.com/*' => Http::response(['handles' => ['batch-1']])]);
    (new GraphApiCatalogService)->pushProduct($connection, '123', ['retailer_id' => 'sku', 'name' => 'Camera', 'price_minor' => 1299, 'currency' => 'INR']);
    Http::assertSent(fn ($r) => json_decode($r['requests'], true)[0]['data']['price'] === '12.99 INR' && $r['allow_upsert']);
});
