<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsappFlow;

class WhatsappFlowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('campaigns.view');
    }

    public function view(User $user, WhatsappFlow $whatsappFlow): bool
    {
        return $user->can('campaigns.view');
    }

    public function sync(User $user): bool
    {
        return $user->can('campaigns.manage');
    }
}
