<?php

namespace App\Policies;

use App\Models\AddonDefinition;
use App\Models\User;

class AddonDefinitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('catalog.view');
    }

    public function view(User $user, AddonDefinition $addonDefinition): bool
    {
        return $user->can('catalog.view');
    }

    public function create(User $user): bool
    {
        return $user->can('catalog.manage');
    }

    public function update(User $user, AddonDefinition $addonDefinition): bool
    {
        return $user->can('catalog.manage');
    }

    public function delete(User $user, AddonDefinition $addonDefinition): bool
    {
        return $user->can('catalog.manage');
    }
}
