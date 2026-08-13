<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        $conversationUuid = $this->message->conversation?->uuid;

        return $conversationUuid ? [new PrivateChannel('conversation.'.$conversationUuid)] : [];
    }

    public function broadcastAs(): string
    {
        return 'message.status-updated';
    }

    public function broadcastWith(): array
    {
        return [
            'messageId' => $this->message->uuid,
            'status' => $this->message->status,
        ];
    }
}
