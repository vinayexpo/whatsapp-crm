<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsappCall;

class WhatsappCallPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('whatsapp-calling.view');
    }

    public function view(User $user, WhatsappCall $whatsappCall): bool
    {
        return $user->can('whatsapp-calling.view');
    }

    public function create(User $user): bool
    {
        return $user->can('whatsapp-calling.manage') || $user->can('conversations.assign');
    }

    public function manageFollowup(User $user, WhatsappCall $whatsappCall): bool
    {
        return $user->can('whatsapp-calling.manage') || $user->can('conversations.assign');
    }
}
