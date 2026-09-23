<?php

namespace App\Policies;

use App\Models\User;

class CommerceSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('catalog.view') || $user->can('commerce-settings.manage');
    }

    public function manage(User $user): bool
    {
        return $user->can('commerce-settings.manage');
    }
}
