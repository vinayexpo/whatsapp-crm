<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'conversationId' => $this->conversation?->uuid,
            'direction' => $this->direction,
            'text' => $this->text,
            'timestamp' => $this->sent_at?->toIso8601String(),
            'status' => $this->status,
            'attachmentUrl' => $this->attachment_url,
            'attachmentType' => $this->attachment_type,
        ];
    }
}
