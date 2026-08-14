<?php

namespace App\Services\Messaging;

use App\Models\ApiConnection;
use App\Models\Message;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GraphApiMessagingService implements OutboundMessageServiceInterface
{
    public function send(Message $message, ApiConnection $connection, ?array $template = null, ?UploadedFile $attachmentFile = null): string
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

        if ($template) {
            // Only a real Meta template message (not free-form text) is
            // allowed to reach a contact outside the 24-hour customer
            // service window, so this must use Meta's "template" message
            // type rather than sending the rendered body as plain text.
            $payload['type'] = 'template';
            $payload['template'] = [
                'name' => $template['name'],
                'language' => ['code' => $template['language']],
            ];

            if (! empty($template['components'])) {
                $payload['template']['components'] = $template['components'];
            }
        } elseif ($attachmentFile && $message->attachment_type && $conversation->channel === 'whatsapp') {
            // Uploading the raw bytes to Meta and referencing the returned
            // media id (instead of passing our own storage URL as a "link")
            // means delivery never depends on our backend being reachable
            // over the public internet -- our own storage disk is ephemeral
            // on this host and was the actual cause of media never arriving.
            $mediaId = $this->uploadMedia($attachmentFile, $senderId, $connection);
            $payload['type'] = $message->attachment_type;
            $payload[$message->attachment_type] = array_filter([
                'id' => $mediaId,
                'caption' => $message->attachment_type === 'document' || $message->attachment_type === 'image' || $message->attachment_type === 'video'
                    ? $message->text
                    : null,
                'filename' => $message->attachment_type === 'document' ? $attachmentFile->getClientOriginalName() : null,
            ]);
        } elseif ($message->attachment_url && in_array($message->attachment_type, ['image', 'video'], true)) {
            // Fallback for channels/paths that only have a stored URL (e.g.
            // Instagram, which has no equivalent media-upload endpoint here).
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

    private function uploadMedia(UploadedFile $file, string $phoneNumberId, ApiConnection $connection): string
    {
        $response = Http::withToken($connection->access_token)
            ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName(), [
                'Content-Type' => $file->getMimeType(),
            ])
            ->post("https://graph.facebook.com/v20.0/{$phoneNumberId}/media", [
                'messaging_product' => 'whatsapp',
            ])
            ->throw();

        return $response->json('id');
    }
}
