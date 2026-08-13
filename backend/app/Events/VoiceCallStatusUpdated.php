<?php

namespace App\Events;

use App\Http\Resources\VoiceCallResource;
use App\Models\VoiceCall;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoiceCallStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public VoiceCall $voiceCall) {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('voice-call.'.$this->voiceCall->uuid)];

        if ($this->voiceCall->conversation) {
            $channels[] = new PrivateChannel('conversation.'.$this->voiceCall->conversation->uuid);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'voice-call.status-updated';
    }

    public function broadcastWith(): array
    {
        return ['voiceCall' => (new VoiceCallResource($this->voiceCall))->resolve()];
    }
}
