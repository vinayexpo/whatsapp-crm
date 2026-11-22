<?php

namespace App\Events;

use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public Conversation $conversation) {}

    public function broadcastOn(): array
    {
        $companyUuid = $this->conversation->company?->uuid;

        return $companyUuid ? [new PrivateChannel('company.'.$companyUuid.'.conversations')] : [];
    }

    public function broadcastAs(): string
    {
        return 'conversation.created';
    }

    public function broadcastWith(): array
    {
        $conversation = $this->conversation->fresh(['contact', 'assignedTo']);
        $conversation?->load(['messages' => fn ($query) => $query->latest('sent_at')->limit(1)]);

        return ['conversation' => (new ConversationResource($conversation ?? $this->conversation))->resolve()];
    }
}
