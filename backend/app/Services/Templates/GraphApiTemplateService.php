<?php

namespace App\Services\Templates;

use App\Models\ApiConnection;
use App\Models\WhatsappTemplate;
use Illuminate\Support\Facades\Http;

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
}
