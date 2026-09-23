<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orders.view');
    }

    public function view(User $user, Order $order): bool
    {
        if (! $user->can('orders.view')) {
            return false;
        }

        return $this->withinScope($user, $order);
    }

    public function update(User $user, Order $order): bool
    {
        if (! $user->can('orders.manage')) {
            return false;
        }

        return $this->withinScope($user, $order);
    }

    private function withinScope(User $user, Order $order): bool
    {
        if ($user->hasRole('staff')) {
            return $order->assigned_staff_user_id === $user->id;
        }

        if ($user->hasRole('branch_manager')) {
            return $user->staff_branch_id === $order->branch_id;
        }

        return true;
    }
}
