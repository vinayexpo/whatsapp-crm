<?php

use App\Models\Branch;
use App\Models\BusinessType;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Order;
use App\Models\User;
use App\Models\WhatsappTemplate;
use App\Services\Commerce\OrderStatusProvisioningService;
use App\Services\Commerce\OrderStatusTransitionService;
use Database\Seeders\BusinessTypeSeeder;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
    $this->seed(BusinessTypeSeeder::class);
});

function provisionedCompany(): array {
    $company = Company::factory()->create();
    $businessType = BusinessType::query()->where('slug', 'restaurant')->firstOrFail();
    app(OrderStatusProvisioningService::class)->provisionForCompany($company->id, $businessType);

    return [$company, $businessType];
}

it('transitions an order to an allowed next status and writes an activity log', function () {
    [$company] = provisionedCompany();
    $transitions = app(OrderStatusTransitionService::class);

    $pending = $transitions->resolveBySlug($company->id, 'pending');
    $confirmed = $transitions->resolveBySlug($company->id, 'confirmed');

    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $contact = Contact::factory()->create(['company_id' => $company->id, 'channel' => 'whatsapp']);
    $order = Order::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'contact_id' => $contact->id,
        'status' => 'pending',
        'status_id' => $pending->id,
    ]);

    $transitions->transition($order, $confirmed);

    $order->refresh();
    expect($order->status)->toBe('confirmed');
    expect($order->status_id)->toBe($confirmed->id);

    expect(App\Models\ActivityLog::query()->where('company_id', $company->id)->where('type', 'order')->exists())->toBeTrue();
});

it('rejects a transition not present in allowed_next_status_ids', function () {
    [$company] = provisionedCompany();
    $transitions = app(OrderStatusTransitionService::class);

    $pending = $transitions->resolveBySlug($company->id, 'pending');
    $outForDelivery = $transitions->resolveBySlug($company->id, 'out_for_delivery');

    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $contact = Contact::factory()->create(['company_id' => $company->id, 'channel' => 'whatsapp']);
    $order = Order::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'contact_id' => $contact->id,
        'status' => 'pending',
        'status_id' => $pending->id,
    ]);

    expect(fn () => $transitions->transition($order, $outForDelivery))->toThrow(RuntimeException::class);
});

it('rejects any transition out of a terminal status', function () {
    [$company] = provisionedCompany();
    $transitions = app(OrderStatusTransitionService::class);

    $cancelled = $transitions->resolveBySlug($company->id, 'cancelled');
    $confirmed = $transitions->resolveBySlug($company->id, 'confirmed');

    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $contact = Contact::factory()->create(['company_id' => $company->id, 'channel' => 'whatsapp']);
    $order = Order::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'contact_id' => $contact->id,
        'status' => 'cancelled',
        'status_id' => $cancelled->id,
    ]);

    expect(fn () => $transitions->transition($order, $confirmed))->toThrow(RuntimeException::class);
});

it('dispatches NotifyOrderStatusChanged when the new status notifies the customer', function () {
    Bus::fake();

    [$company] = provisionedCompany();
    $transitions = app(OrderStatusTransitionService::class);

    $pending = $transitions->resolveBySlug($company->id, 'pending');
    $confirmed = $transitions->resolveBySlug($company->id, 'confirmed');
    $confirmed->update(['notify_customer' => true]);

    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $contact = Contact::factory()->create(['company_id' => $company->id, 'channel' => 'whatsapp']);
    $order = Order::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'contact_id' => $contact->id,
        'status' => 'pending',
        'status_id' => $pending->id,
    ]);

    $transitions->transition($order, $confirmed);

    Bus::assertDispatched(App\Jobs\NotifyOrderStatusChanged::class, function ($job) use ($order, $confirmed) {
        return $job->orderId === $order->id && $job->orderStatusId === $confirmed->id;
    });
});

it('does not dispatch a notification when the new status does not notify the customer', function () {
    Bus::fake();

    [$company] = provisionedCompany();
    $transitions = app(OrderStatusTransitionService::class);

    $pending = $transitions->resolveBySlug($company->id, 'pending');
    $confirmed = $transitions->resolveBySlug($company->id, 'confirmed');
    $confirmed->update(['notify_customer' => false]);

    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $contact = Contact::factory()->create(['company_id' => $company->id, 'channel' => 'whatsapp']);
    $order = Order::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'contact_id' => $contact->id,
        'status' => 'pending',
        'status_id' => $pending->id,
    ]);

    $transitions->transition($order, $confirmed);

    Bus::assertNotDispatched(App\Jobs\NotifyOrderStatusChanged::class);
});

it('lets an admin update order status via the API for a provisioned company', function () {
    [$company] = provisionedCompany();

    $admin = User::factory()->create(['company_id' => $company->id]);
    $admin->assignRole('admin');

    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $contact = Contact::factory()->create(['company_id' => $company->id, 'channel' => 'whatsapp']);
    $order = Order::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'contact_id' => $contact->id,
        'status' => 'pending',
        'status_id' => app(OrderStatusTransitionService::class)->resolveBySlug($company->id, 'pending')->id,
    ]);

    $response = $this->actingAs($admin)->patchJson("/api/v1/commerce/orders/{$order->uuid}/status", [
        'status' => 'confirmed',
    ]);

    $response->assertOk()->assertJsonPath('data.status', 'confirmed');
});

it('rejects an invalid status transition via the API', function () {
    [$company] = provisionedCompany();

    $admin = User::factory()->create(['company_id' => $company->id]);
    $admin->assignRole('admin');

    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $contact = Contact::factory()->create(['company_id' => $company->id, 'channel' => 'whatsapp']);
    $order = Order::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'contact_id' => $contact->id,
        'status' => 'pending',
        'status_id' => app(OrderStatusTransitionService::class)->resolveBySlug($company->id, 'pending')->id,
    ]);

    $response = $this->actingAs($admin)->patchJson("/api/v1/commerce/orders/{$order->uuid}/status", [
        'status' => 'out_for_delivery',
    ]);

    $response->assertStatus(500);
});

it('still auto-confirms via the legacy path for an unprovisioned order', function () {
    $company = Company::factory()->create();
    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $contact = Contact::factory()->create(['company_id' => $company->id, 'channel' => 'whatsapp']);
    $order = Order::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'contact_id' => $contact->id,
        'status' => 'pending',
        'status_id' => null,
    ]);

    app(OrderStatusTransitionService::class)->maybeAutoConfirmOnPayment($order);

    $order->refresh();
    expect($order->status)->toBe('confirmed');
    expect($order->payment_status)->toBe('paid');
});

it('auto-confirms via the data-driven path and dispatches a notification when configured', function () {
    Bus::fake();

    [$company] = provisionedCompany();
    $transitions = app(OrderStatusTransitionService::class);
    $pending = $transitions->resolveBySlug($company->id, 'pending');
    $confirmed = $transitions->resolveBySlug($company->id, 'confirmed');
    $confirmed->update(['notify_customer' => true]);

    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $contact = Contact::factory()->create(['company_id' => $company->id, 'channel' => 'whatsapp']);
    $order = Order::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'contact_id' => $contact->id,
        'status' => 'pending',
        'status_id' => $pending->id,
    ]);

    $transitions->maybeAutoConfirmOnPayment($order);

    $order->refresh();
    expect($order->status)->toBe('confirmed');
    expect($order->payment_status)->toBe('paid');
    Bus::assertDispatched(App\Jobs\NotifyOrderStatusChanged::class);
});

it('renders a notification template with order number and status label', function () {
    [$company] = provisionedCompany();
    $transitions = app(OrderStatusTransitionService::class);

    $template = WhatsappTemplate::factory()->create([
        'company_id' => $company->id,
        'body' => 'Hi! Order {{order_number}} is now {{status}}.',
    ]);

    $confirmed = $transitions->resolveBySlug($company->id, 'confirmed');
    $confirmed->update(['notification_template_id' => $template->id]);

    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $contact = Contact::factory()->create(['company_id' => $company->id, 'channel' => 'whatsapp']);
    $conversation = App\Models\Conversation::factory()->create([
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'channel' => 'whatsapp',
    ]);
    $order = Order::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'contact_id' => $contact->id,
        'conversation_id' => $conversation->id,
        'order_number' => 'ORD-1001',
    ]);

    $job = new App\Jobs\NotifyOrderStatusChanged($order->id, $confirmed->id);
    $job->handle(app(App\Services\Commerce\CommerceMessageSender::class));

    $message = App\Models\Message::query()->where('conversation_id', $conversation->id)->latest('id')->first();
    expect($message->text)->toBe('Hi! Order ORD-1001 is now Confirmed.');
});
