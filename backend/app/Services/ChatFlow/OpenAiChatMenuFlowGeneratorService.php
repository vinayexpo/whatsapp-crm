<?php

namespace App\Services\ChatFlow;

use App\Models\AiAssistantSetting;
use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class OpenAiChatMenuFlowGeneratorService implements ChatMenuFlowGeneratorServiceInterface
{
    public function generate(Company $company, string $prompt): ?array
    {
        $settings = AiAssistantSetting::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->first();

        $baseUrl = $settings?->base_url;
        $apiKey = $settings?->api_key;
        $model = $settings?->model;

        if (empty($baseUrl) || empty($apiKey) || empty($model)) {
            return null;
        }

        $systemPrompt = 'You design button-based WhatsApp/web-chat menu flows for a customer support bot. '.
            'Given a description of the desired menu, produce a JSON object of the form '.
            '{"entryNodeId":"root","nodes":[{"id":"...","type":"menu|content","message":"...","renderAs":"button|list","buttons":[{"id":"...","label":"...","nextNodeId":"..."}]}]}. '.
            'Rules: node ids and button ids must be short unique lowercase-kebab-case strings you invent (e.g. "root", "catering", "btn-catering"). '.
            'A "menu" node has 1 or more buttons, each with a "nextNodeId" pointing to another node id in the same array. '.
            'A "content" node is a leaf and must have an empty "buttons" array — it is the final message shown for that branch. '.
            'Use "renderAs":"list" only when a node has more than 3 buttons, otherwise use "button". '.
            'Keep messages concise and written for a real customer, not placeholders. '.
            'Respond with JSON only, no other commentary.';

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post(rtrim($baseUrl, '/').'/chat/completions', [
                    'model' => $model,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Chat menu flow generation request failed', [
                    'company_id' => $company->id,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $content = data_get($response->json(), 'choices.0.message.content');

            if (! is_string($content) || trim($content) === '') {
                return null;
            }

            $decoded = json_decode($content, true);

            return $this->sanitize($decoded);
        } catch (Throwable $e) {
            Log::warning('Chat menu flow generation request errored', [
                'company_id' => $company->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{entryNodeId: string, nodes: array}|null
     */
    private function sanitize(mixed $decoded): ?array
    {
        if (! is_array($decoded) || ! is_array($decoded['nodes'] ?? null) || count($decoded['nodes']) === 0) {
            return null;
        }

        $idMap = [];
        $nodes = [];

        foreach ($decoded['nodes'] as $rawNode) {
            if (! is_array($rawNode) || ! is_string($rawNode['id'] ?? null) || trim($rawNode['id']) === '') {
                continue;
            }

            $idMap[$rawNode['id']] = (string) Str::uuid();
        }

        if (count($idMap) === 0) {
            return null;
        }

        foreach ($decoded['nodes'] as $rawNode) {
            if (! is_array($rawNode) || ! isset($idMap[$rawNode['id'] ?? null])) {
                continue;
            }

            $buttons = collect(is_array($rawNode['buttons'] ?? null) ? $rawNode['buttons'] : [])
                ->filter(fn ($button) => is_array($button)
                    && is_string($button['label'] ?? null)
                    && trim($button['label']) !== ''
                    && isset($idMap[$button['nextNodeId'] ?? null]))
                ->map(fn ($button) => [
                    'id' => (string) Str::uuid(),
                    // Meta caps button/list-row titles at 20 characters.
                    'label' => Str::limit(trim($button['label']), 20, ''),
                    'nextNodeId' => $idMap[$button['nextNodeId']],
                ])
                ->values()
                ->all();

            $nodes[] = [
                'id' => $idMap[$rawNode['id']],
                'type' => count($buttons) > 0 ? 'menu' : 'content',
                'message' => is_string($rawNode['message'] ?? null) ? Str::limit(trim($rawNode['message']), 1024, '') : '',
                'mediaUrl' => null,
                'mediaType' => null,
                'renderAs' => count($buttons) > 3 ? 'list' : 'button',
                'buttons' => $buttons,
            ];
        }

        $nodes = array_values(array_filter($nodes, fn ($node) => $node['message'] !== ''));

        if (count($nodes) === 0) {
            return null;
        }

        $entryNodeId = $idMap[$decoded['entryNodeId'] ?? null] ?? $nodes[0]['id'];

        return [
            'entryNodeId' => $entryNodeId,
            'nodes' => $nodes,
        ];
    }
}
