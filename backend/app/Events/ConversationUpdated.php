<?php

namespace App\Events;

use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Conversation $conversation) {}

    public function broadcastOn(): array
    {
        $conversationUuid = $this->conversation->uuid;

        return $conversationUuid ? [new PrivateChannel('conversation.'.$conversationUuid)] : [];
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    public function broadcastWith(): array
    {
        return ['conversation' => (new ConversationResource($this->conversation->fresh(['contact', 'assignedTo', 'messages'])))->resolve()];
    }
}
