<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsappTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'apiConnectionId' => $this->apiConnection?->uuid,
            'name' => $this->name,
            'language' => $this->language,
            'category' => $this->category,
            'status' => $this->status,
            'body' => $this->body,
            'variables' => $this->variables ?? [],
            'components' => $this->components ?? [],
            'createdByUserId' => $this->createdByUser?->uuid,
            'rejectionReason' => $this->rejection_reason,
            'submittedAt' => $this->submitted_at?->toIso8601String(),
            'syncedAt' => $this->synced_at?->toIso8601String(),
        ];
    }
}
