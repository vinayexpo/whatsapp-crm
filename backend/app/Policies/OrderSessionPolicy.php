<?php

namespace App\Policies;

use App\Models\OrderSession;
use App\Models\User;

class OrderSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orders.view');
    }

    public function view(User $user, OrderSession $orderSession): bool
    {
        return $user->can('orders.view');
    }
}
