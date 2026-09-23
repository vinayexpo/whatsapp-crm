<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        if (! $user->can('branches.view')) {
            return false;
        }

        return $this->withinScope($user, $branch);
    }

    public function create(User $user): bool
    {
        return $user->can('branches.manage') && ! $user->hasRole('branch_manager');
    }

    public function update(User $user, Branch $branch): bool
    {
        if (! $user->can('branches.manage')) {
            return false;
        }

        // branch_manager only has branches.view, so this already excludes
        // them; this guard keeps behavior explicit if their permission set
        // ever changes.
        if ($user->hasRole('branch_manager')) {
            return false;
        }

        return $this->withinScope($user, $branch);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $this->update($user, $branch);
    }

    private function withinScope(User $user, Branch $branch): bool
    {
        if (! $user->hasAnyRole(['branch_manager', 'staff'])) {
            return true;
        }

        return $user->staff_branch_id === $branch->id;
    }
}
