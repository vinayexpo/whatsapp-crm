<?php

namespace App\Events;

use App\Models\WhatsappCall;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsappCallSdpAnswerReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public WhatsappCall $whatsappCall) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('whatsapp-call.'.$this->whatsappCall->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'whatsapp-call.sdp-answer';
    }

    public function broadcastWith(): array
    {
        return ['sdp' => $this->whatsappCall->remote_sdp_answer];
    }
}
