<?php

namespace App\Events;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        $conversationUuid = $this->message->conversation?->uuid;

        $channels = $conversationUuid ? [new PrivateChannel('conversation.'.$conversationUuid)] : [];
        $companyUuid = $this->message->conversation?->company?->uuid;

        if ($companyUuid) {
            $channels[] = new PrivateChannel('company.'.$companyUuid.'.conversations');
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.received';
    }

    public function broadcastWith(): array
    {
        return ['message' => (new MessageResource($this->message))->resolve()];
    }
}
