<?php

use App\Models\ApiConnection;
use App\Models\Branch;
use App\Models\CommerceSetting;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderSession;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\TwoWayCatalogSync;
use App\Services\Commerce\CartContext;
use App\Services\Commerce\Payments\WhatsAppPayPaymentService;
use App\Services\Commerce\ReleaseOrderInventory;
use App\Services\Commerce\Steps\OrderConfirmationStepHandler;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

function commerceHttpFake(array|callable $responses): void
{
    Http::swap(new Factory);
    Http::fake($responses);
}

function commerceSetupForTest(): CommerceSetting
{
    $company = Company::factory()->create();
    $connection = ApiConnection::factory()->connected()->create(['company_id' => $company->id, 'channel' => 'whatsapp', 'phone_number_id' => '12345']);

    return CommerceSetting::factory()->create(['company_id' => $company->id, 'api_connection_id' => $connection->id, 'meta_catalog_id' => '123456', 'currency' => 'INR', 'payment_configuration_name' => 'store', 'whatsapp_pay_enabled' => true, 'razorpay_key_id' => 'rzp_test_key', 'razorpay_key_secret' => 'test-secret']);
}
function remoteProductForTest(array $overrides = []): array
{
    return array_replace(['id' => 'meta-1', 'retailer_id' => 'sku-1', 'name' => 'Camera', 'description' => 'A camera', 'price' => '120.00 INR', 'currency' => 'INR', 'availability' => 'in stock', 'image_url' => 'https://example.com/camera.jpg', 'url' => 'https://example.com/camera', 'condition' => 'new'], $overrides);
}

it('imports real Meta data and preserves tenant boundaries', function () {
    $settings = commerceSetupForTest();
    $foreign = Product::factory()->create(['company_id' => Company::factory()->create()->id, 'sku' => 'sku-1']);
    commerceHttpFake(['graph.facebook.com/*' => Http::response(['data' => [remoteProductForTest()]])]);
    $result = app(TwoWayCatalogSync::class)->run($settings);
    expect($result['created'])->toBe(1);
    expect(Product::where('company_id', $settings->company_id)->first()->base_price)->toBe(12000);
    expect($foreign->fresh()->name)->toBe($foreign->name);
});

it('submits local changes but waits for remote readback before claiming sync', function () {
    $settings = commerceSetupForTest();
    commerceHttpFake(['graph.facebook.com/*' => Http::response(['data' => [remoteProductForTest()]])]);
    $sync = app(TwoWayCatalogSync::class);
    $sync->run($settings);
    $product = Product::where('company_id', $settings->company_id)->first();
    $product->update(['name' => 'Local Camera']);
    commerceHttpFake(['*/products*' => Http::response(['data' => [remoteProductForTest()]]), '*/items_batch' => Http::response(['handles' => ['batch-1']])]);
    $sync->run($settings->fresh());
    expect($product->fresh()->meta_sync_state)->toBe('pending');
    commerceHttpFake(['graph.facebook.com/*' => Http::response(['data' => [remoteProductForTest(['name' => 'Local Camera'])]])]);
    $sync->run($settings->fresh());
    expect($product->fresh()->meta_sync_state)->toBe('synced');
});

it('flags concurrent edits instead of overwriting the local product', function () {
    $settings = commerceSetupForTest();
    commerceHttpFake(['graph.facebook.com/*' => Http::response(['data' => [remoteProductForTest()]])]);
    $sync = app(TwoWayCatalogSync::class);
    $sync->run($settings);
    $product = Product::where('company_id', $settings->company_id)->first();
    $product->update(['name' => 'Local Camera']);
    commerceHttpFake(['graph.facebook.com/*' => Http::response(['data' => [remoteProductForTest(['name' => 'Meta Camera'])]])]);
    $sync->run($settings->fresh());
    expect($product->fresh()->name)->toBe('Local Camera')->and($product->fresh()->meta_sync_state)->toBe('conflict');
    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});

it('imports remote edits and removes an unchanged product removed remotely', function () {
    $settings = commerceSetupForTest();
    commerceHttpFake(['graph.facebook.com/*' => Http::response(['data' => [remoteProductForTest()]])]);
    $sync = app(TwoWayCatalogSync::class);
    $sync->run($settings);
    $product = Product::where('company_id', $settings->company_id)->first();
    commerceHttpFake(['graph.facebook.com/*' => Http::response(['data' => [remoteProductForTest(['name' => 'Remote Camera'])]])]);
    $sync->run($settings->fresh());
    expect($product->fresh()->name)->toBe('Remote Camera');
    commerceHttpFake(['graph.facebook.com/*' => Http::response(['data' => []])]);
    $sync->run($settings->fresh());
    expect($product->fresh()->trashed())->toBeTrue();
});

it('refuses currency mismatches without importing data', function () {
    $settings = commerceSetupForTest();
    commerceHttpFake(['graph.facebook.com/*' => Http::response(['data' => [remoteProductForTest(['currency' => 'USD'])]])]);
    expect(fn () => app(TwoWayCatalogSync::class)->run($settings))->toThrow(ValidationException::class);
    expect(Product::where('company_id', $settings->company_id)->count())->toBe(0);
});

it('rejects catalog sync without a real connection instead of creating samples', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create(['company_id' => Company::factory()->create()->id]);
    $user->assignRole('admin');
    CommerceSetting::factory()->create(['company_id' => $user->company_id, 'meta_catalog_id' => '12345']);
    $this->actingAs($user)->postJson('/api/v1/commerce/products/sync-meta')->assertUnprocessable();
    expect(Product::count())->toBe(0);
});

it('encrypts payment credentials and never returns them in settings', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $settings = commerceSetupForTest();
    $user = User::factory()->create(['company_id' => $settings->company_id]);
    $user->assignRole('admin');
    $response = $this->actingAs($user)->getJson('/api/v1/commerce/settings')->assertOk();
    expect(json_encode($response->json()))->not->toContain('test-secret');
    expect(DB::table('commerce_settings')->where('id', $settings->id)->value('razorpay_key_secret'))->not->toBe('test-secret');
});

it('verifies receipt identity amount and currency and never regresses paid state', function () {
    $settings = commerceSetupForTest();
    $branch = Branch::factory()->create(['company_id' => $settings->company_id]);
    $contact = Contact::factory()->create(['company_id' => $settings->company_id]);
    $order = Order::factory()->create(['company_id' => $settings->company_id, 'branch_id' => $branch->id, 'contact_id' => $contact->id]);
    $payment = Payment::factory()->create(['company_id' => $settings->company_id, 'order_id' => $order->id, 'method' => 'gateway:whatsapp', 'amount' => 12000, 'provider_reference' => 'ref-1', 'raw_payload' => ['configuration' => 'store', 'connection_id' => $settings->api_connection_id]]);
    $receipt = ['reference_id' => 'ref-1', 'currency' => 'INR', 'amount' => ['value' => 12000, 'offset' => 100], 'status' => 'captured'];
    commerceHttpFake(['graph.facebook.com/*' => Http::response($receipt)]);
    $service = app(WhatsAppPayPaymentService::class);
    expect($service->verify($payment))->toBe('paid');
    $receipt['status'] = 'pending';
    commerceHttpFake(['graph.facebook.com/*' => Http::response($receipt)]);
    expect($service->verify($payment->fresh()))->toBe('paid');
    $receipt['amount']['value'] = 1;
    commerceHttpFake(['graph.facebook.com/*' => Http::response($receipt)]);
    $payment->update(['status' => 'pending']);
    expect(fn () => $service->verify($payment))->toThrow(ValidationException::class);
});

it('reset is explicit and a dry run never deletes commerce data', function () {
    $settings = commerceSetupForTest();
    Product::factory()->create(['company_id' => $settings->company_id]);
    $this->artisan('commerce:reset')->assertFailed();
    $this->artisan('commerce:reset --all-companies --dry-run')->assertSuccessful();
    expect(Product::count())->toBe(1);
    $this->artisan('commerce:reset --all-companies --confirm=DELETE-ALL-COMMERCE')->assertSuccessful();
    expect(Product::withTrashed()->count())->toBe(0)->and(CommerceSetting::count())->toBe(0)->and(ApiConnection::count())->toBe(1);
});

it('sends one native Razorpay request with a stable short reference', function () {
    $settings = commerceSetupForTest();
    $branch = Branch::factory()->create(['company_id' => $settings->company_id]);
    $contact = Contact::factory()->create(['company_id' => $settings->company_id, 'channel' => 'whatsapp']);
    $order = Order::factory()->create(['company_id' => $settings->company_id, 'branch_id' => $branch->id, 'contact_id' => $contact->id, 'subtotal' => 50000, 'grand_total' => 50000]);
    $order->items()->create(['company_id' => $settings->company_id, 'name_snapshot' => 'Camera', 'unit_price_snapshot' => 25000, 'quantity' => 2, 'line_total' => 50000]);
    $payment = Payment::factory()->create(['company_id' => $settings->company_id, 'order_id' => $order->id, 'method' => 'gateway:whatsapp', 'amount' => 50000, 'status' => 'pending', 'provider_reference' => null]);
    commerceHttpFake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid-payment']]])]);
    $service = app(WhatsAppPayPaymentService::class);
    $reference = $service->initiate($payment);
    $service->initiate($payment->fresh());
    expect(strlen($reference))->toBeLessThanOrEqual(35);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => data_get($request->data(), 'interactive.action.parameters.payment_settings.0.payment_gateway.type') === 'razorpay' && data_get($request->data(), 'interactive.action.parameters.total_amount.value') === 50000);
});

it('reserves tracked stock only once and returns it only once on cancellation', function () {
    Queue::fake();
    commerceHttpFake(fn () => Http::response(['messages' => [['id' => 'wamid-'.Str::uuid()]]]));
    $settings = commerceSetupForTest();
    $branch = Branch::factory()->create(['company_id' => $settings->company_id]);
    $contact = Contact::factory()->create(['company_id' => $settings->company_id, 'channel' => 'whatsapp']);
    $conversation = Conversation::factory()->create(['company_id' => $settings->company_id, 'contact_id' => $contact->id, 'channel' => 'whatsapp']);
    $product = Product::factory()->create(['company_id' => $settings->company_id, 'base_price' => 5000, 'sale_price' => null]);
    $stock = Inventory::factory()->create(['company_id' => $settings->company_id, 'branch_id' => $branch->id, 'product_id' => $product->id, 'product_variant_id' => null, 'track_stock' => true, 'stock_quantity' => 1]);
    $session = OrderSession::create(['company_id' => $settings->company_id, 'conversation_id' => $conversation->id, 'contact_id' => $contact->id, 'branch_id' => $branch->id, 'status' => 'active', 'step' => 'order_confirmation', 'context' => []]);
    $cart = new CartContext($session);
    $cart->setBranch($branch->id, $branch->name);
    $cart->addItem($product->id, null, $product->name, 5000, 2);
    $cart->persist('order_confirmation');
    $handler = app(OrderConfirmationStepHandler::class);
    $handler->enter($session);
    expect(Order::count())->toBe(0)->and($stock->fresh()->stock_quantity)->toBe(1);
    $stock->update(['stock_quantity' => 2]);
    $handler->enter($session->fresh());
    $handler->enter($session->fresh());
    expect(Order::count())->toBe(1)->and($stock->fresh()->stock_quantity)->toBe(0);
    $order = Order::first();
    $order->update(['status' => 'cancelled']);
    $release = app(ReleaseOrderInventory::class);
    $release->run($order);
    $release->run($order);
    expect($stock->fresh()->stock_quantity)->toBe(2);
});
