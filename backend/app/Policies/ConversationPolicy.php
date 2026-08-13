<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('conversations.view');
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $user->can('conversations.view');
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $user->can('conversations.view');
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        return $user->can('conversations.reply');
    }

    public function assign(User $user, Conversation $conversation): bool
    {
        return $user->can('conversations.view');
    }
}
