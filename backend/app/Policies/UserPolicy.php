<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('team.manage');
    }

    public function viewAssignable(User $user): bool
    {
        return $user->can('conversations.view');
    }

    public function create(User $user): bool
    {
        return $user->can('team.manage');
    }

    public function update(User $user, User $teamMember): bool
    {
        return $user->can('team.manage') && $user->company_id === $teamMember->company_id;
    }

    public function delete(User $user, User $teamMember): bool
    {
        return $user->can('team.manage') && $user->company_id === $teamMember->company_id;
    }
}
