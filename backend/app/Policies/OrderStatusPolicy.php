<?php

namespace App\Policies;

use App\Models\User;

class OrderStatusPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orders.view');
    }

    public function manage(User $user): bool
    {
        return $user->can('commerce-settings.manage');
    }
}
