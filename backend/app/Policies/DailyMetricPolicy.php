<?php

namespace App\Policies;

use App\Models\User;

class DailyMetricPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('analytics.view');
    }
}
