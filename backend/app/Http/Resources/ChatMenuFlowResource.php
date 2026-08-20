<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMenuFlowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'channel' => $this->channel,
            'status' => $this->status,
            'triggerKeyword' => $this->trigger_keyword,
            'entryNodeId' => $this->entry_node_id,
            'nodes' => $this->nodes ?? [],
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
