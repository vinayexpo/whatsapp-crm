<?php

namespace App\Policies;

use App\Models\Inventory;
use App\Models\User;

class InventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function view(User $user, Inventory $inventory): bool
    {
        if (! $user->can('inventory.view')) {
            return false;
        }

        return $this->withinScope($user, $inventory);
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.manage');
    }

    public function update(User $user, Inventory $inventory): bool
    {
        if (! $user->can('inventory.manage')) {
            return false;
        }

        return $this->withinScope($user, $inventory);
    }

    public function delete(User $user, Inventory $inventory): bool
    {
        return $this->update($user, $inventory);
    }

    private function withinScope(User $user, Inventory $inventory): bool
    {
        if (! $user->hasAnyRole(['branch_manager', 'staff'])) {
            return true;
        }

        return $user->staff_branch_id === $inventory->branch_id;
    }
}
