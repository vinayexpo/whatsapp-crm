<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoiceAgentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'status' => $this->status,
            'voiceMode' => $this->voice_mode,
            'ttsVoiceId' => $this->tts_voice_id,
            'greetingAudioUrl' => $this->greeting_audio_url,
            'qualificationCriteria' => $this->qualification_criteria ?? [],
            'qualificationPassCriteria' => $this->qualification_pass_criteria,
            'maxCallAttempts' => $this->max_call_attempts,
            'systemPrompt' => $this->system_prompt,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
