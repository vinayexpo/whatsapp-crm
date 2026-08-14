<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsappCallResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'callFlowId' => $this->callFlow?->uuid,
            'contactId' => $this->contact?->uuid,
            'conversationId' => $this->conversation?->uuid,
            'direction' => $this->direction,
            'status' => $this->status,
            'metaCallId' => $this->meta_call_id,
            'sdpExchangeStatus' => $this->sdp_exchange_status,
            'permissionRequestStatus' => $this->permission_request_status,
            'permissionRequestFailureReason' => $this->permission_request_failure_reason,
            'transcript' => $this->transcript ?? [],
            'collectedVariables' => $this->collected_variables ?? [],
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
