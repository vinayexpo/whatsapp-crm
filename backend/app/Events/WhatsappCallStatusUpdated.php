<?php

namespace App\Events;

use App\Http\Resources\WhatsappCallResource;
use App\Models\WhatsappCall;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsappCallStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public WhatsappCall $whatsappCall) {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('whatsapp-call.'.$this->whatsappCall->uuid)];

        if ($this->whatsappCall->conversation) {
            $channels[] = new PrivateChannel('conversation.'.$this->whatsappCall->conversation->uuid);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'whatsapp-call.status-updated';
    }

    public function broadcastWith(): array
    {
        return ['whatsappCall' => (new WhatsappCallResource($this->whatsappCall))->resolve()];
    }
}
