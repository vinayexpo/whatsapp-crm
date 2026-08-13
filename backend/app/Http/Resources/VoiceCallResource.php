<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoiceCallResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'voiceAgentId' => $this->voiceAgent?->uuid,
            'contactId' => $this->contact?->uuid,
            'conversationId' => $this->conversation?->uuid,
            'direction' => $this->direction,
            'medium' => $this->medium,
            'status' => $this->status,
            'recordingUrl' => $this->recording_url,
            'transcript' => $this->transcript ?? [],
            'qualificationFields' => $this->qualification_fields ?? [],
            'qualificationOutcome' => $this->qualification_outcome,
            'qualificationSummary' => $this->qualification_summary,
            'needsHumanFollowup' => (bool) $this->needs_human_followup,
            'humanFollowupAssignedTo' => $this->humanFollowupAssignee ? [
                'id' => $this->humanFollowupAssignee->uuid,
                'name' => $this->humanFollowupAssignee->name,
            ] : null,
            'humanFollowupCompletedAt' => $this->human_followup_completed_at?->toIso8601String(),
            'startedAt' => $this->started_at?->toIso8601String(),
            'endedAt' => $this->ended_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
