<?php

namespace App\Services\Messaging;

use App\Jobs\SimulateMessageDeliveryTick;
use App\Models\ApiConnection;
use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FakeMetaMessagingService implements OutboundMessageServiceInterface
{
    public function send(Message $message, ApiConnection $connection, ?array $template = null): string
    {
        $externalId = 'fake_'.Str::uuid();

        Log::info('FakeMetaMessagingService: simulated outbound send', [
            'message_id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'external_message_id' => $externalId,
            'text' => $message->text,
            'attachment_url' => $message->attachment_url,
            'attachment_type' => $message->attachment_type,
        ]);

        SimulateMessageDeliveryTick::dispatch($message->id, 'delivered')->delay(now()->addSeconds(3));
        SimulateMessageDeliveryTick::dispatch($message->id, 'read')->delay(now()->addSeconds(8));

        return $externalId;
    }
}
