<?php

namespace App\Policies;

use App\Models\Chatbot;
use App\Models\User;

class ChatbotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('chatbots.view');
    }

    public function view(User $user, Chatbot $chatbot): bool
    {
        return $user->can('chatbots.view');
    }

    public function create(User $user): bool
    {
        return $user->can('chatbots.manage');
    }

    public function update(User $user, Chatbot $chatbot): bool
    {
        return $user->can('chatbots.manage');
    }

    public function delete(User $user, Chatbot $chatbot): bool
    {
        return $user->can('chatbots.manage');
    }
}
