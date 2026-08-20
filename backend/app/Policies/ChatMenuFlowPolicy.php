<?php

namespace App\Policies;

use App\Models\ChatMenuFlow;
use App\Models\User;

class ChatMenuFlowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('chatbots.view');
    }

    public function view(User $user, ChatMenuFlow $chatMenuFlow): bool
    {
        return $user->can('chatbots.view');
    }

    public function create(User $user): bool
    {
        return $user->can('chatbots.manage');
    }

    public function update(User $user, ChatMenuFlow $chatMenuFlow): bool
    {
        return $user->can('chatbots.manage');
    }

    public function delete(User $user, ChatMenuFlow $chatMenuFlow): bool
    {
        return $user->can('chatbots.manage');
    }
}
