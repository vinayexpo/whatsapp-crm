<?php

namespace App\Services\Messaging;

use App\Models\ApiConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WhatsAppMediaDownloader
{
    /**
     * Meta only exposes inbound media as a short-lived media id -- the actual
     * bytes have to be fetched (via a second, temporary download URL) and
     * persisted on our own storage before that URL expires.
     *
     * @return array{url: string, type: string}|null
     */
    public function download(string $mediaId, string $mimeType, ApiConnection $connection): ?array
    {
        $metadata = Http::withToken($connection->access_token)
            ->get("https://graph.facebook.com/v20.0/{$mediaId}");

        if (! $metadata->successful() || ! $metadata->json('url')) {
            Log::warning('Failed to resolve WhatsApp inbound media URL', [
                'media_id' => $mediaId,
                'status' => $metadata->status(),
            ]);

            return null;
        }

        $file = Http::withToken($connection->access_token)->get($metadata->json('url'));

        if (! $file->successful()) {
            Log::warning('Failed to download WhatsApp inbound media', [
                'media_id' => $mediaId,
                'status' => $file->status(),
            ]);

            return null;
        }

        $extension = Str::after($mimeType, '/');
        $path = 'inbound-media/'.Str::uuid().'.'.$extension;

        Storage::disk('public')->put($path, $file->body());

        return [
            'url' => Storage::disk('public')->url($path),
            'type' => $this->attachmentType($mimeType),
        ];
    }

    private function attachmentType(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            default => 'document',
        };
    }
}
