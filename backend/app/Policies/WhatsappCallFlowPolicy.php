<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsappCallFlow;

class WhatsappCallFlowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('whatsapp-calling.view');
    }

    public function view(User $user, WhatsappCallFlow $whatsappCallFlow): bool
    {
        return $user->can('whatsapp-calling.view');
    }

    public function create(User $user): bool
    {
        return $user->can('whatsapp-calling.manage');
    }

    public function update(User $user, WhatsappCallFlow $whatsappCallFlow): bool
    {
        return $user->can('whatsapp-calling.manage');
    }

    public function delete(User $user, WhatsappCallFlow $whatsappCallFlow): bool
    {
        return $user->can('whatsapp-calling.manage');
    }
}
