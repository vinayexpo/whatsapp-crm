<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

function branchScopedUser(string $role, Branch $branch): User
{
    $user = User::factory()->create([
        'company_id' => $branch->company_id,
        'staff_branch_id' => $branch->id,
    ]);
    $user->assignRole($role);

    return $user;
}

it('prevents a branch manager from viewing another branch', function () {
    $company = Company::factory()->create();
    $ownBranch = Branch::factory()->create(['company_id' => $company->id]);
    $otherBranch = Branch::factory()->create(['company_id' => $company->id]);
    $manager = branchScopedUser('branch_manager', $ownBranch);

    $this->actingAs($manager)->getJson("/api/v1/commerce/branches/{$otherBranch->uuid}")->assertForbidden();
    $this->actingAs($manager)->getJson("/api/v1/commerce/branches/{$ownBranch->uuid}")->assertOk();
});

it('scopes branch listing to the branch manager own branch', function () {
    $company = Company::factory()->create();
    $ownBranch = Branch::factory()->create(['company_id' => $company->id]);
    Branch::factory()->create(['company_id' => $company->id]);
    $manager = branchScopedUser('branch_manager', $ownBranch);

    $response = $this->actingAs($manager)->getJson('/api/v1/commerce/branches')->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$ownBranch->uuid]);
});

it('prevents a branch manager from creating a new branch', function () {
    $company = Company::factory()->create();
    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $manager = branchScopedUser('branch_manager', $branch);

    $this->actingAs($manager)->postJson('/api/v1/commerce/branches', [
        'name' => 'New Branch',
        'slug' => 'new-branch',
    ])->assertForbidden();
});

it('scopes order listing to the branch manager own branch', function () {
    $company = Company::factory()->create();
    $ownBranch = Branch::factory()->create(['company_id' => $company->id]);
    $otherBranch = Branch::factory()->create(['company_id' => $company->id]);
    $contact = Contact::factory()->create(['company_id' => $company->id, 'channel' => 'whatsapp']);

    $ownOrder = Order::factory()->create(['company_id' => $company->id, 'branch_id' => $ownBranch->id, 'contact_id' => $contact->id]);
    Order::factory()->create(['company_id' => $company->id, 'branch_id' => $otherBranch->id, 'contact_id' => $contact->id]);

    $manager = branchScopedUser('branch_manager', $ownBranch);

    $response = $this->actingAs($manager)->getJson('/api/v1/commerce/orders')->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$ownOrder->uuid]);
});

it('prevents a staff member from viewing an order not assigned to them', function () {
    $company = Company::factory()->create();
    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $contact = Contact::factory()->create(['company_id' => $company->id, 'channel' => 'whatsapp']);
    $staff = branchScopedUser('staff', $branch);
    $otherStaff = branchScopedUser('staff', $branch);

    $assignedOrder = Order::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'contact_id' => $contact->id,
        'assigned_staff_user_id' => $staff->id,
    ]);
    $unassignedOrder = Order::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'contact_id' => $contact->id,
        'assigned_staff_user_id' => $otherStaff->id,
    ]);

    $this->actingAs($staff)->getJson("/api/v1/commerce/orders/{$assignedOrder->uuid}")->assertOk();
    $this->actingAs($staff)->getJson("/api/v1/commerce/orders/{$unassignedOrder->uuid}")->assertForbidden();

    $response = $this->actingAs($staff)->getJson('/api/v1/commerce/orders')->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$assignedOrder->uuid]);
});

it('scopes inventory listing and updates to the branch manager own branch', function () {
    $company = Company::factory()->create();
    $ownBranch = Branch::factory()->create(['company_id' => $company->id]);
    $otherBranch = Branch::factory()->create(['company_id' => $company->id]);
    $product = Product::factory()->create(['company_id' => $company->id]);

    $ownInventory = Inventory::factory()->create(['company_id' => $company->id, 'branch_id' => $ownBranch->id, 'product_id' => $product->id]);
    $otherInventory = Inventory::factory()->create(['company_id' => $company->id, 'branch_id' => $otherBranch->id, 'product_id' => $product->id]);

    $manager = branchScopedUser('branch_manager', $ownBranch);

    $response = $this->actingAs($manager)->getJson('/api/v1/commerce/inventory')->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$ownInventory->id]);

    $this->actingAs($manager)->putJson("/api/v1/commerce/inventory/{$otherInventory->id}", ['stockQuantity' => 5])->assertForbidden();
    $this->actingAs($manager)->putJson("/api/v1/commerce/inventory/{$ownInventory->id}", ['stockQuantity' => 5])->assertOk();
});
