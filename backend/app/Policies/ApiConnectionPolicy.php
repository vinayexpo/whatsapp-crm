<?php

namespace App\Policies;

use App\Models\ApiConnection;
use App\Models\User;

class ApiConnectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('settings.manage');
    }

    public function update(User $user, ApiConnection $apiConnection): bool
    {
        return $user->can('settings.manage');
    }
}
