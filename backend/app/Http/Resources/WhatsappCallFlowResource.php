<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsappCallFlowResource extends JsonResource
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
            'status' => $this->status,
            'greetingMessage' => $this->greeting_message,
            'nodes' => $this->nodes ?? [],
            'fallbackMessage' => $this->fallback_message,
            'maxRetries' => $this->max_retries,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
