<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $latestMessage = $this->relationLoaded('messages') ? $this->messages->last() : null;

        return [
            'id' => $this->uuid,
            'contactId' => $this->contact?->uuid,
            'channel' => $this->channel,
            'status' => $this->status,
            'unreadCount' => $this->unread_count,
            'lastMessagePreview' => $latestMessage?->text ?? '',
            'lastMessageAt' => $this->last_message_at?->toIso8601String(),
            'assignedTo' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo ? [
                'id' => $this->assignedTo->uuid,
                'name' => $this->assignedTo->name,
                'avatarUrl' => $this->assignedTo->avatar_url,
            ] : null),
        ];
    }
}
