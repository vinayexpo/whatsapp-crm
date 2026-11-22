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

    public bool $afterCommit = true;

    public function __construct(public Conversation $conversation) {}

    public function broadcastOn(): array
    {
        $conversationUuid = $this->conversation->uuid;

        $channels = $conversationUuid ? [new PrivateChannel('conversation.'.$conversationUuid)] : [];
        $companyUuid = $this->conversation->company?->uuid;

        if ($companyUuid) {
            $channels[] = new PrivateChannel('company.'.$companyUuid.'.conversations');
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    public function broadcastWith(): array
    {
        // Only the most recent message is needed for the inbox preview.
        // Loading the whole thread here loaded every message of the
        // conversation into memory on each broadcast (queued), which is what
        // exhausted the worker's memory limit on long-running conversations.
        $conversation = $this->conversation->fresh(['contact', 'assignedTo']);
        $conversation?->load(['messages' => fn ($query) => $query->latest('sent_at')->limit(1)]);

        return [
            'conversation' => (new ConversationResource($conversation ?? $this->conversation))->resolve(),
        ];
    }
}
