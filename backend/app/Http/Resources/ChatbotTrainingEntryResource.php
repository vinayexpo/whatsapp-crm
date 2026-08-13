<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatbotTrainingEntryResource extends JsonResource
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
            'question' => $this->question,
            'answer' => $this->answer,
            'source' => $this->source,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
