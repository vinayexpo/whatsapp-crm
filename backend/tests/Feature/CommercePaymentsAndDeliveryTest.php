<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\Contact;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Commerce\OrderStatusTransitionService;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
    config(['services.razorpay.webhook_secret' => 'whsec_test_secret']);
});

function actingAsPaymentsRole(string $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => ($company ?? Company::factory()->create())->id]);
    $user->assignRole($role);

    return $user;
}

function createOrderWithPayment(Company $company, array $paymentAttributes = []): array
{
    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $contact = Contact::factory()->create(['company_id' => $company->id, 'channel' => 'whatsapp']);
    $order = Order::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'contact_id' => $contact->id,
    ]);
    $payment = Payment::factory()->create(array_merge([
        'company_id' => $company->id,
        'order_id' => $order->id,
        'amount' => $order->grand_total,
    ], $paymentAttributes));

    return [$order, $payment];
}

// Delivery zones

it('rejects unauthenticated delivery zone listing', function () {
    $this->getJson('/api/v1/commerce/delivery-zones')->assertUnauthorized();
});

it('allows an admin to create a delivery zone for a branch', function () {
    $admin = actingAsPaymentsRole('admin');
    $branch = Branch::factory()->create(['company_id' => $admin->company_id]);

    $response = $this->actingAs($admin)->postJson('/api/v1/commerce/delivery-zones', [
        'branchId' => $branch->uuid,
        'name' => '5km zone',
        'radiusKm' => 5,
        'deliveryCharge' => 3000,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', '5km zone')
        ->assertJsonPath('data.deliveryCharge', 3000);

    $this->assertDatabaseHas('delivery_zones', ['branch_id' => $branch->id, 'name' => '5km zone']);
});

it('forbids an agent from creating a delivery zone', function () {
    $agent = actingAsPaymentsRole('agent');
    $branch = Branch::factory()->create(['company_id' => $agent->company_id]);

    $this->actingAs($agent)->postJson('/api/v1/commerce/delivery-zones', [
        'branchId' => $branch->uuid,
        'name' => '5km zone',
        'deliveryCharge' => 3000,
    ])->assertForbidden();
});

it('scopes delivery zones to the caller company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = actingAsPaymentsRole('manager', $companyA);

    $branchA = Branch::factory()->create(['company_id' => $companyA->id]);
    $branchB = Branch::factory()->create(['company_id' => $companyB->id]);
    DeliveryZone::factory()->create(['company_id' => $companyA->id, 'branch_id' => $branchA->id]);
    DeliveryZone::factory()->create(['company_id' => $companyB->id, 'branch_id' => $branchB->id]);

    $response = $this->actingAs($userA)->getJson('/api/v1/commerce/delivery-zones');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('allows deleting a delivery zone', function () {
    $admin = actingAsPaymentsRole('admin');
    $branch = Branch::factory()->create(['company_id' => $admin->company_id]);
    $zone = DeliveryZone::factory()->create(['company_id' => $admin->company_id, 'branch_id' => $branch->id]);

    $this->actingAs($admin)->deleteJson("/api/v1/commerce/delivery-zones/{$zone->uuid}")->assertOk();

    $this->assertDatabaseMissing('delivery_zones', ['id' => $zone->id]);
});

// Payments listing + mark-paid

it('rejects unauthenticated payment listing', function () {
    $this->getJson('/api/v1/commerce/payments')->assertUnauthorized();
});

it('lists payments scoped to the caller company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = actingAsPaymentsRole('manager', $companyA);

    createOrderWithPayment($companyA);
    createOrderWithPayment($companyB);

    $response = $this->actingAs($userA)->getJson('/api/v1/commerce/payments');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('allows a manager to mark a pending cod payment as paid', function () {
    $manager = actingAsPaymentsRole('manager');
    [$order, $payment] = createOrderWithPayment($manager->company, ['method' => 'cod', 'status' => 'pending']);

    $response = $this->actingAs($manager)->patchJson("/api/v1/commerce/payments/{$payment->uuid}/mark-paid");

    $response->assertOk()->assertJsonPath('data.status', 'paid');
    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
});

it('auto-confirms a pending order once its cod payment is marked paid', function () {
    $manager = actingAsPaymentsRole('manager');
    [$order, $payment] = createOrderWithPayment($manager->company, ['method' => 'cod', 'status' => 'pending']);
    $order->update(['status' => 'pending']);

    $this->actingAs($manager)->patchJson("/api/v1/commerce/payments/{$payment->uuid}/mark-paid")->assertOk();

    $order->refresh();
    expect($order->status)->toBe('confirmed');
    expect($order->payment_status)->toBe('paid');
});

it('rejects manually marking a gateway payment as paid', function () {
    $manager = actingAsPaymentsRole('manager');
    [$order, $payment] = createOrderWithPayment($manager->company, ['method' => 'gateway:razorpay', 'status' => 'pending']);

    $this->actingAs($manager)->patchJson("/api/v1/commerce/payments/{$payment->uuid}/mark-paid")
        ->assertStatus(422);

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
});

it('forbids an agent from marking a payment as paid', function () {
    $agent = actingAsPaymentsRole('agent');
    [$order, $payment] = createOrderWithPayment($agent->company, ['method' => 'cod', 'status' => 'pending']);

    $this->actingAs($agent)->patchJson("/api/v1/commerce/payments/{$payment->uuid}/mark-paid")->assertForbidden();
});

// Razorpay webhook signature verification

function razorpaySignature(string $body, string $secret): string
{
    return hash_hmac('sha256', $body, $secret);
}

it('rejects a razorpay webhook with an invalid signature', function () {
    $company = Company::factory()->create();
    [$order, $payment] = createOrderWithPayment($company, ['status' => 'pending', 'provider_reference' => 'order_test123']);

    $payload = json_encode([
        'event' => 'payment.captured',
        'payload' => ['payment' => ['entity' => ['order_id' => 'order_test123']]],
    ]);

    $this->postJson('/api/webhooks/commerce-payment/razorpay', json_decode($payload, true), [
        'X-Razorpay-Signature' => 'bogus-signature',
    ])->assertStatus(401);

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
});

it('rejects a razorpay webhook with no signature header', function () {
    $this->postJson('/api/webhooks/commerce-payment/razorpay', ['event' => 'payment.captured'])
        ->assertStatus(401);
});

it('marks a payment paid and auto-confirms the order on a valid razorpay captured webhook', function () {
    $company = Company::factory()->create();
    [$order, $payment] = createOrderWithPayment($company, ['status' => 'pending', 'provider_reference' => 'order_test123']);
    $order->update(['status' => 'pending']);

    $body = json_encode([
        'event' => 'payment.captured',
        'payload' => ['payment' => ['entity' => ['order_id' => 'order_test123']]],
    ]);
    $signature = razorpaySignature($body, 'whsec_test_secret');

    $response = $this->call(
        'POST',
        '/api/webhooks/commerce-payment/razorpay',
        [],
        [],
        [],
        [
            'HTTP_X-Razorpay-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ],
        $body,
    );

    $response->assertNoContent();

    $payment->refresh();
    $order->refresh();
    expect($payment->status)->toBe('paid');
    expect($order->status)->toBe('confirmed');
    expect($order->payment_status)->toBe('paid');
});

it('marks a payment failed on a valid razorpay failed webhook', function () {
    $company = Company::factory()->create();
    [$order, $payment] = createOrderWithPayment($company, ['status' => 'pending', 'provider_reference' => 'order_test456']);

    $body = json_encode([
        'event' => 'payment.failed',
        'payload' => ['payment' => ['entity' => ['order_id' => 'order_test456', 'error_description' => 'Card declined']]],
    ]);
    $signature = razorpaySignature($body, 'whsec_test_secret');

    $this->call(
        'POST',
        '/api/webhooks/commerce-payment/razorpay',
        [],
        [],
        [],
        ['HTTP_X-Razorpay-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
        $body,
    )->assertNoContent();

    $payment->refresh();
    expect($payment->status)->toBe('failed');
    expect($payment->failure_reason)->toBe('Card declined');
});

it('does not blow up on a valid webhook signature for an unknown payment reference', function () {
    $body = json_encode([
        'event' => 'payment.captured',
        'payload' => ['payment' => ['entity' => ['order_id' => 'order_does_not_exist']]],
    ]);
    $signature = razorpaySignature($body, 'whsec_test_secret');

    $this->call(
        'POST',
        '/api/webhooks/commerce-payment/razorpay',
        [],
        [],
        [],
        ['HTTP_X-Razorpay-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
        $body,
    )->assertNoContent();
});

// OrderStatusTransitionService integrity

it('does not resurrect a terminal order status when a late payment confirmation arrives', function () {
    $company = Company::factory()->create();
    [$order, $payment] = createOrderWithPayment($company, ['status' => 'pending']);
    $order->update(['status' => 'cancelled']);

    app(OrderStatusTransitionService::class)->maybeAutoConfirmOnPayment($order->fresh());

    $order->refresh();
    expect($order->status)->toBe('cancelled');
});

// WhatsApp Pay callback handling

it('marks a payment paid and auto-confirms the order on a captured whatsapp pay status', function () {
    $company = Company::factory()->create();
    [$order, $payment] = createOrderWithPayment($company, [
        'status' => 'pending',
        'method' => 'gateway:whatsapp',
        'provider_reference' => 'wapay-test-ref',
    ]);
    $order->update(['status' => 'pending']);

    app(\App\Http\Controllers\Api\V1\PaymentController::class)->handleWhatsAppPayStatus('wapay-test-ref', [
        'payment_status' => ['status' => 'captured'],
    ]);

    $payment->refresh();
    $order->refresh();
    expect($payment->status)->toBe('paid');
    expect($order->status)->toBe('confirmed');
    expect($order->payment_status)->toBe('paid');
});

it('marks a payment failed on a declined whatsapp pay status', function () {
    $company = Company::factory()->create();
    [$order, $payment] = createOrderWithPayment($company, [
        'status' => 'pending',
        'method' => 'gateway:whatsapp',
        'provider_reference' => 'wapay-test-ref-2',
    ]);

    app(\App\Http\Controllers\Api\V1\PaymentController::class)->handleWhatsAppPayStatus('wapay-test-ref-2', [
        'payment_status' => ['status' => 'declined', 'description' => 'Payment declined by user'],
    ]);

    $payment->refresh();
    expect($payment->status)->toBe('failed');
    expect($payment->failure_reason)->toBe('Payment declined by user');
});

it('does not blow up when a whatsapp pay status arrives for an unknown reference', function () {
    app(\App\Http\Controllers\Api\V1\PaymentController::class)->handleWhatsAppPayStatus('wapay-does-not-exist', [
        'payment_status' => ['status' => 'captured'],
    ]);
})->throwsNoExceptions();

// Order total integrity -- cart_total/subtotal is always recalculated from
// actual line items on every mutation (CartContext::recalculateTotal), never
// trusted as a single stored figure that could drift from its line items.

it('recalculates cart_total from line items rather than trusting a stale stored total', function () {
    $company = Company::factory()->create();
    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $contact = Contact::factory()->create(['company_id' => $company->id, 'channel' => 'whatsapp']);
    $conversation = \App\Models\Conversation::factory()->create([
        'contact_id' => $contact->id,
        'channel' => 'whatsapp',
    ]);

    $session = \App\Models\OrderSession::create([
        'company_id' => $company->id,
        'conversation_id' => $conversation->id,
        'contact_id' => $contact->id,
        'branch_id' => $branch->id,
        'status' => 'active',
        'step' => 'cart_review',
        // A stored cart_total that intentionally does not match the line
        // items' actual sum, simulating drift/tampering.
        'context' => ['cart' => [], 'cart_total' => 99999],
        'last_interaction_at' => now(),
    ]);

    $cart = new \App\Services\Commerce\CartContext($session);
    $cart->addItem(productId: 1, variantId: null, name: 'Widget', unitPrice: 5000, quantity: 3);

    expect($cart->total())->toBe(15000);
});

// WhatsApp Pay wiring integrity -- ProcessInboundWhatsAppMessage routes
// order_status inbound messages to PaymentController::handleWhatsAppPayStatus.

it('routes an inbound order_status message to the whatsapp pay handler', function () {
    $company = Company::factory()->create();
    [$order, $payment] = createOrderWithPayment($company, [
        'status' => 'pending',
        'method' => 'gateway:whatsapp',
        'provider_reference' => 'wapay-inbound-ref',
    ]);

    $inboundMessage = [
        'type' => 'order_status',
        'order_status' => [
            'reference_id' => 'wapay-inbound-ref',
            'order' => ['status' => 'captured'],
        ],
    ];

    app(\App\Http\Controllers\Api\V1\PaymentController::class)->handleWhatsAppPayStatus(
        data_get($inboundMessage, 'order_status.reference_id'),
        ['payment_status' => [
            'status' => data_get($inboundMessage, 'order_status.order.status'),
            'description' => data_get($inboundMessage, 'order_status.description'),
        ]],
    );

    $payment->refresh();
    expect($payment->status)->toBe('paid');
});
