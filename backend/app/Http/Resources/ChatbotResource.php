<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatbotResource extends JsonResource
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
            'status' => $this->status,
            'widgetKey' => $this->widget_key,
            'allowedOrigins' => $this->allowed_origins ?? [],
            'welcomeMessage' => $this->welcome_message,
            'generalFallbackEnabled' => $this->general_fallback_enabled,
            'channels' => $this->channels ?? ['website'],
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
