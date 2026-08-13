<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsappTemplate;

class WhatsappTemplatePolicy
{
    public function create(User $user): bool
    {
        return $user->can('campaigns.manage');
    }

    public function update(User $user, WhatsappTemplate $whatsappTemplate): bool
    {
        return $user->can('campaigns.manage');
    }

    public function delete(User $user, WhatsappTemplate $whatsappTemplate): bool
    {
        return $user->can('campaigns.manage');
    }
}
