<?php

namespace App\Policies;

use App\Models\AutomationFlow;
use App\Models\User;

class AutomationFlowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('automations.manage');
    }

    public function view(User $user, AutomationFlow $automationFlow): bool
    {
        return $user->can('automations.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('automations.manage');
    }

    public function update(User $user, AutomationFlow $automationFlow): bool
    {
        return $user->can('automations.manage');
    }

    public function delete(User $user, AutomationFlow $automationFlow): bool
    {
        return $user->can('automations.manage');
    }
}
