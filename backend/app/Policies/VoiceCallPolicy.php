<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VoiceCall;

class VoiceCallPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('voice-agents.view');
    }

    public function view(User $user, VoiceCall $voiceCall): bool
    {
        return $user->can('voice-agents.view');
    }

    public function create(User $user): bool
    {
        return $user->can('voice-agents.view');
    }

    public function manageFollowup(User $user, VoiceCall $voiceCall): bool
    {
        return $user->can('voice-agents.manage') || $user->can('conversations.assign');
    }
}
