<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsappFlowResource extends JsonResource
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
            'categories' => $this->categories ?? [],
            'syncedAt' => $this->synced_at?->toIso8601String(),
        ];
    }
}
