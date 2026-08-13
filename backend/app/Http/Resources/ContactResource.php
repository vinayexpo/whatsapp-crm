<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
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
            'name' => $this->name,
            'avatarUrl' => $this->avatar_url,
            'channel' => $this->channel,
            'handle' => $this->handle,
            'phone' => $this->phone,
            'email' => $this->email,
            'location' => $this->location,
            'tags' => $this->tags->pluck('name'),
            'pipelineStage' => $this->pipeline_stage_id,
            'dealValue' => $this->deal_value,
            'lastInteractionAt' => $this->last_interaction_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'notes' => $this->notes ?? [],
            'purchases' => $this->purchases ?? [],
        ];
    }
}
