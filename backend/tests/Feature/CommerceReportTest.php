<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

function actingAsReportRole(string $role, ?Branch $branch = null): User
{
    $companyId = $branch?->company_id ?? Company::factory()->create()->id;
    $user = User::factory()->create([
        'company_id' => $companyId,
        'staff_branch_id' => $branch?->id,
    ]);
    $user->assignRole($role);

    return $user;
}

it('rejects unauthenticated report access', function () {
    $this->getJson('/api/v1/commerce/reports/sales')->assertUnauthorized();
});

it('forbids staff from viewing reports', function () {
    $company = Company::factory()->create();
    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $staff = actingAsReportRole('staff', $branch);

    $this->actingAs($staff)->getJson('/api/v1/commerce/reports/sales')->assertForbidden();
});

it('returns aggregated sales for an admin across all branches', function () {
    $admin = actingAsReportRole('admin');
    $branchA = Branch::factory()->create(['company_id' => $admin->company_id]);
    $branchB = Branch::factory()->create(['company_id' => $admin->company_id]);
    $contact = Contact::factory()->create(['company_id' => $admin->company_id, 'channel' => 'whatsapp']);

    Order::factory()->create(['company_id' => $admin->company_id, 'branch_id' => $branchA->id, 'contact_id' => $contact->id, 'grand_total' => 1000, 'placed_at' => now()]);
    Order::factory()->create(['company_id' => $admin->company_id, 'branch_id' => $branchB->id, 'contact_id' => $contact->id, 'grand_total' => 2000, 'placed_at' => now()]);

    $response = $this->actingAs($admin)->getJson('/api/v1/commerce/reports/sales')->assertOk();

    $totalRevenue = collect($response->json('data'))->sum('revenue');
    $totalOrders = collect($response->json('data'))->sum('orderCount');

    expect($totalRevenue)->toBe(3000);
    expect($totalOrders)->toBe(2);
});

it('scopes sales report to the branch manager own branch', function () {
    $company = Company::factory()->create();
    $ownBranch = Branch::factory()->create(['company_id' => $company->id]);
    $otherBranch = Branch::factory()->create(['company_id' => $company->id]);
    $contact = Contact::factory()->create(['company_id' => $company->id, 'channel' => 'whatsapp']);

    Order::factory()->create(['company_id' => $company->id, 'branch_id' => $ownBranch->id, 'contact_id' => $contact->id, 'grand_total' => 1500, 'placed_at' => now()]);
    Order::factory()->create(['company_id' => $company->id, 'branch_id' => $otherBranch->id, 'contact_id' => $contact->id, 'grand_total' => 9000, 'placed_at' => now()]);

    $manager = actingAsReportRole('branch_manager', $ownBranch);

    $response = $this->actingAs($manager)->getJson('/api/v1/commerce/reports/sales')->assertOk();

    $totalRevenue = collect($response->json('data'))->sum('revenue');

    expect($totalRevenue)->toBe(1500);
});

it('returns sales grouped by branch for an admin', function () {
    $admin = actingAsReportRole('admin');
    $branch = Branch::factory()->create(['company_id' => $admin->company_id]);
    $contact = Contact::factory()->create(['company_id' => $admin->company_id, 'channel' => 'whatsapp']);

    Order::factory()->create(['company_id' => $admin->company_id, 'branch_id' => $branch->id, 'contact_id' => $contact->id, 'grand_total' => 4000, 'placed_at' => now()]);

    $response = $this->actingAs($admin)->getJson('/api/v1/commerce/reports/sales-by-branch')->assertOk();

    expect($response->json('data.0.branchId'))->toBe($branch->uuid);
    expect($response->json('data.0.revenue'))->toBe(4000);
});

it('returns top products ranked by quantity sold', function () {
    $admin = actingAsReportRole('admin');
    $branch = Branch::factory()->create(['company_id' => $admin->company_id]);
    $contact = Contact::factory()->create(['company_id' => $admin->company_id, 'channel' => 'whatsapp']);
    $productA = Product::factory()->create(['company_id' => $admin->company_id, 'name' => 'Product A']);
    $productB = Product::factory()->create(['company_id' => $admin->company_id, 'name' => 'Product B']);

    $order = Order::factory()->create(['company_id' => $admin->company_id, 'branch_id' => $branch->id, 'contact_id' => $contact->id, 'placed_at' => now()]);

    OrderItem::factory()->create([
        'company_id' => $admin->company_id,
        'order_id' => $order->id,
        'product_id' => $productA->id,
        'name_snapshot' => $productA->name,
        'quantity' => 5,
        'line_total' => 5000,
    ]);
    OrderItem::factory()->create([
        'company_id' => $admin->company_id,
        'order_id' => $order->id,
        'product_id' => $productB->id,
        'name_snapshot' => $productB->name,
        'quantity' => 2,
        'line_total' => 2000,
    ]);

    $response = $this->actingAs($admin)->getJson('/api/v1/commerce/reports/top-products')->assertOk();

    expect($response->json('data.0.name'))->toBe('Product A');
    expect($response->json('data.0.totalQuantity'))->toBe(5);
    expect($response->json('data.1.name'))->toBe('Product B');
});
