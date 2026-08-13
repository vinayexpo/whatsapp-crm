<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('campaigns.view');
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->can('campaigns.view');
    }

    public function create(User $user): bool
    {
        return $user->can('campaigns.manage');
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->can('campaigns.manage');
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->can('campaigns.manage');
    }
}
