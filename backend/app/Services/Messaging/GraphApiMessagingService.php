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
        } elseif ($message->attachment_url && $message->attachment_type && $conversation->channel === 'whatsapp') {
            // Campaign sends only ever have a stored URL, not an UploadedFile
            // (the file was uploaded once at campaign-creation time, sends
            // happen later via a queued job) -- fetch the bytes ourselves and
            // upload them to Meta's /media endpoint instead of handing Meta a
            // "link", so delivery doesn't depend on that URL still being
            // reachable (our own storage disk is ephemeral without S3, and
            // even with S3, a bad/stale link would otherwise silently fail
            // every recipient the same way this campaign did).
            $mediaId = $this->uploadMediaFromUrl($message->attachment_url, $senderId, $connection);
            $payload['type'] = $message->attachment_type;
            $payload[$message->attachment_type] = array_filter([
                'id' => $mediaId,
                'caption' => in_array($message->attachment_type, ['document', 'image', 'video'], true) ? $message->text : null,
            ]);
        } elseif ($message->attachment_url && in_array($message->attachment_type, ['image', 'video'], true)) {
            // Instagram has no equivalent media-upload endpoint here, so it
            // must still pass a fetchable link.
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

    private function uploadMediaFromUrl(string $url, string $phoneNumberId, ApiConnection $connection): string
    {
        $file = Http::get($url)->throw();

        $response = Http::withToken($connection->access_token)
            ->attach('file', $file->body(), basename(parse_url($url, PHP_URL_PATH) ?? 'attachment'), [
                'Content-Type' => $file->header('Content-Type') ?: 'application/octet-stream',
            ])
            ->post("https://graph.facebook.com/v20.0/{$phoneNumberId}/media", [
                'messaging_product' => 'whatsapp',
            ])
            ->throw();

        return $response->json('id');
    }
}
