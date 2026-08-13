<?php

namespace App\Services\Messaging;

use App\Models\ApiConnection;
use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GraphApiMessagingService implements OutboundMessageServiceInterface
{
    public function send(Message $message, ApiConnection $connection): string
    {
        $conversation = $message->conversation()->with('contact')->first();

        $senderId = $conversation->channel === 'instagram'
            ? $connection->instagram_account_id
            : $connection->phone_number_id;

        $recipient = $conversation->channel === 'instagram'
            ? Str::after($conversation->contact->handle, '@ig_')
            : $conversation->contact->handle;

        $payload = [
            'messaging_product' => $conversation->channel,
            'to' => $recipient,
        ];

        if ($message->attachment_url && in_array($message->attachment_type, ['image', 'video'], true)) {
            $payload['type'] = $message->attachment_type;
            $payload[$message->attachment_type] = [
                'link' => $message->attachment_url,
                'caption' => $message->text,
            ];
        } else {
            $payload['text'] = ['body' => $message->text];
        }

        $response = Http::withToken($connection->access_token)
            ->post("https://graph.facebook.com/v20.0/{$senderId}/messages", $payload)
            ->throw();

        return $response->json('messages.0.id');
    }
}
