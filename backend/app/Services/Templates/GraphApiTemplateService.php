<?php

namespace App\Services\Templates;

use App\Models\ApiConnection;
use App\Models\WhatsappTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GraphApiTemplateService implements TemplateSyncServiceInterface
{
    public function fetchTemplates(ApiConnection $connection): array
    {
        $response = Http::withToken($connection->access_token)
            ->get("https://graph.facebook.com/v20.0/{$connection->waba_id}/message_templates", [
                'fields' => 'id,name,language,category,status,components',
                'limit' => 100,
            ])
            ->throw();

        $templates = [];

        foreach ($response->json('data', []) as $template) {
            $bodyComponent = collect($template['components'] ?? [])
                ->firstWhere('type', 'BODY');

            $body = $bodyComponent['text'] ?? '';

            preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $body, $matches);

            $templates[] = [
                'meta_template_id' => (string) $template['id'],
                'name' => $template['name'],
                'language' => $template['language'],
                'category' => strtolower($template['category']),
                'status' => strtolower($template['status']),
                'body' => $body,
                'variables' => array_values(array_unique($matches[1] ?? [])),
                'components' => $template['components'] ?? [],
            ];
        }

        return $templates;
    }

    public function submitTemplate(ApiConnection $connection, WhatsappTemplate $template): array
    {
        $response = Http::withToken($connection->access_token)
            ->post("https://graph.facebook.com/v20.0/{$connection->waba_id}/message_templates", [
                'name' => $template->name,
                'language' => $template->language,
                'category' => strtoupper($template->category),
                'components' => $template->components ?? [],
            ])
            ->throw();

        return [
            'meta_template_id' => (string) $response->json('id'),
            'status' => strtolower($response->json('status', 'pending')),
        ];
    }

    public function uploadHeaderMedia(ApiConnection $connection, UploadedFile $file): string
    {
        $appId = $this->appIdFor($connection);

        // Meta's Resumable Upload API: first create an upload session, then
        // POST the raw file bytes to the returned session id with a
        // `file_offset` header. Sending the bytes as a multipart/form-data
        // field (instead of the raw body) makes Meta answer 500.
        $session = Http::withToken($connection->access_token)
            ->post("https://graph.facebook.com/v20.0/{$appId}/uploads", [
                'file_name' => $file->getClientOriginalName(),
                'file_length' => $file->getSize(),
                'file_type' => $file->getMimeType(),
            ])
            ->throw();

        $uploadSessionId = $session->json('id');

        $upload = Http::withToken($connection->access_token)
            ->withHeaders(['file_offset' => '0'])
            ->withBody(file_get_contents($file->getRealPath()), $file->getMimeType())
            ->post("https://graph.facebook.com/v20.0/{$uploadSessionId}")
            ->throw();

        return (string) $upload->json('h');
    }

    /**
     * Resolve the Meta app id used for the Resumable Upload API. A single
     * META_APP_ID env var doesn't work when several WhatsApp accounts are
     * connected through different Meta apps, so fall back to asking Meta
     * which app the connection's token belongs to (cached per connection).
     */
    private function appIdFor(ApiConnection $connection): string
    {
        $configured = config('services.meta.app_id');

        if ($configured) {
            return (string) $configured;
        }

        $appId = Cache::remember(
            "meta:app_id:connection:{$connection->id}",
            now()->addDay(),
            fn () => Http::withToken($connection->access_token)
                ->get('https://graph.facebook.com/v20.0/app', ['fields' => 'id'])
                ->throw()
                ->json('id'),
        );

        if (! $appId) {
            throw new RuntimeException('Could not determine the Meta app id for this WhatsApp connection.');
        }

        return (string) $appId;
    }

    public function pushTemplateEdits(ApiConnection $connection, WhatsappTemplate $template): array
    {
        $response = Http::withToken($connection->access_token)
            ->post("https://graph.facebook.com/v20.0/{$template->meta_template_id}", [
                'category' => strtoupper($template->category),
                'components' => $template->components ?? [],
            ])
            ->throw();

        return [
            'status' => strtolower($response->json('status', 'pending')),
        ];
    }
}
