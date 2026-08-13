<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VoiceAgent;

class VoiceAgentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('voice-agents.view');
    }

    public function view(User $user, VoiceAgent $voiceAgent): bool
    {
        return $user->can('voice-agents.view');
    }

    public function create(User $user): bool
    {
        return $user->can('voice-agents.manage');
    }

    public function update(User $user, VoiceAgent $voiceAgent): bool
    {
        return $user->can('voice-agents.manage');
    }

    public function delete(User $user, VoiceAgent $voiceAgent): bool
    {
        return $user->can('voice-agents.manage');
    }
}
