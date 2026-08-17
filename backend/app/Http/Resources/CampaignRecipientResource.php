<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignRecipientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'contactName' => $this->contact?->name,
            'contactHandle' => $this->contact?->handle,
            'status' => $this->status,
            'failureReason' => $this->failure_reason,
            'sentAt' => $this->sent_at?->toIso8601String(),
            'deliveredAt' => $this->delivered_at?->toIso8601String(),
            'readAt' => $this->read_at?->toIso8601String(),
            'repliedAt' => $this->replied_at?->toIso8601String(),
        ];
    }
}
