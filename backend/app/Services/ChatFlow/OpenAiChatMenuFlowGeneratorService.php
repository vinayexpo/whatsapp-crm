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
    private ?string $lastError = null;

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function generate(Company $company, string $prompt): ?array
    {
        $this->lastError = null;

        $settings = AiAssistantSetting::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->first();

        $baseUrl = $settings?->base_url;
        $apiKey = $settings?->api_key;
        $model = $settings?->model;

        if (empty($baseUrl) || empty($apiKey) || empty($model)) {
            $this->lastError = 'No AI assistant is configured for this company yet. Add one in Settings → AI Assistant first.';

            return null;
        }

        $systemPrompt = 'You design button-based WhatsApp/web-chat menu flows for a customer support bot. '.
            'Given a description of the desired menu, produce a JSON object of the form '.
            '{"entryNodeId":"root","nodes":[{"id":"...","type":"menu|content","message":"...","renderAs":"button|list","buttons":[{"id":"...","label":"...","nextNodeId":"..."}]}]}. '.
            'Rules: node ids and button ids must be short unique lowercase-kebab-case strings you invent (e.g. "root", "catering", "btn-catering"). '.
            'A "menu" node has 1 or more buttons, each with a "nextNodeId" pointing to another node id in the same array. '.
            'A "content" node is a leaf and must have an empty "buttons" array — it is the final message shown for that branch. '.
            'Use "renderAs":"list" only when a node has more than 3 buttons, otherwise use "button". '.
            'Every button label must be 20 characters or fewer, and every message must be 1024 characters or fewer — '.
            'shorten wording as needed to fit, never omit a button to save space. '.
            'Keep messages concise and written for a real customer, not placeholders. '.
            'Respond with JSON only, no other commentary, and no fields other than the ones described above.';

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

                $this->lastError = "The AI provider returned an error (HTTP {$response->status()}). Check the AI assistant settings and try again.";

                return null;
            }

            $content = data_get($response->json(), 'choices.0.message.content');

            if (! is_string($content) || trim($content) === '') {
                $this->lastError = 'The AI returned an empty response. Try a shorter or more specific description.';

                return null;
            }

            $decoded = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                Log::warning('Chat menu flow generation returned invalid JSON', [
                    'company_id' => $company->id,
                    'content' => Str::limit($content, 500),
                ]);

                $this->lastError = 'The AI response was not in the expected format. Try simplifying your description (fewer branches, shorter rules) and generate again.';

                return null;
            }

            return $this->sanitize($decoded);
        } catch (Throwable $e) {
            Log::warning('Chat menu flow generation request errored', [
                'company_id' => $company->id,
                'message' => $e->getMessage(),
            ]);

            $this->lastError = 'Something went wrong contacting the AI provider. Please try again.';

            return null;
        }
    }

    /**
     * @return array{entryNodeId: string, nodes: array}|null
     */
    private function sanitize(array $decoded): ?array
    {
        // Some models nest the payload under a wrapper key (e.g. "flow" or
        // "data") despite instructions -- unwrap the first array value that
        // actually looks like our shape before giving up.
        if (! is_array($decoded['nodes'] ?? null)) {
            foreach ($decoded as $value) {
                if (is_array($value) && is_array($value['nodes'] ?? null)) {
                    $decoded = $value;
                    break;
                }
            }
        }

        $rawNodes = $decoded['nodes'] ?? null;

        if (! is_array($rawNodes) || count($rawNodes) === 0) {
            $this->lastError = 'The AI response did not include any menu steps. Try a more specific description and generate again.';

            return null;
        }

        $idMap = [];

        foreach ($rawNodes as $rawNode) {
            $rawId = is_array($rawNode) ? ($rawNode['id'] ?? null) : null;
            if (is_string($rawId) && trim($rawId) !== '') {
                $idMap[$rawId] = (string) Str::uuid();
            } elseif (is_int($rawId)) {
                $idMap[(string) $rawId] = (string) Str::uuid();
            }
        }

        if (count($idMap) === 0) {
            $this->lastError = 'The AI response did not include valid step identifiers. Try generating again.';

            return null;
        }

        $nodes = [];

        foreach ($rawNodes as $rawNode) {
            if (! is_array($rawNode)) {
                continue;
            }

            $rawId = $rawNode['id'] ?? null;
            $key = is_int($rawId) ? (string) $rawId : $rawId;

            if (! is_string($key) || ! isset($idMap[$key])) {
                continue;
            }

            $rawButtons = $rawNode['buttons'] ?? null;

            $buttons = collect(is_array($rawButtons) ? $rawButtons : [])
                ->filter(function ($button) use ($idMap) {
                    if (! is_array($button) || ! is_string($button['label'] ?? null) || trim($button['label']) === '') {
                        return false;
                    }
                    $next = $button['nextNodeId'] ?? null;
                    $nextKey = is_int($next) ? (string) $next : $next;

                    return is_string($nextKey) && isset($idMap[$nextKey]);
                })
                ->map(function ($button) use ($idMap) {
                    $next = $button['nextNodeId'];
                    $nextKey = is_int($next) ? (string) $next : $next;

                    return [
                        'id' => (string) Str::uuid(),
                        // Meta caps button/list-row titles at 20 characters.
                        'label' => Str::limit(trim($button['label']), 20, ''),
                        'nextNodeId' => $idMap[$nextKey],
                    ];
                })
                ->values()
                ->all();

            $message = $rawNode['message'] ?? null;

            $nodes[] = [
                'id' => $idMap[$key],
                'type' => count($buttons) > 0 ? 'menu' : 'content',
                'message' => is_string($message) ? Str::limit(trim($message), 1024, '') : '',
                'mediaUrl' => null,
                'mediaType' => null,
                'renderAs' => count($buttons) > 3 ? 'list' : 'button',
                'buttons' => $buttons,
            ];
        }

        $nodes = array_values(array_filter($nodes, fn ($node) => $node['message'] !== ''));

        if (count($nodes) === 0) {
            $this->lastError = 'The AI response did not include any steps with a message. Try generating again.';

            return null;
        }

        $rawEntryId = $decoded['entryNodeId'] ?? null;
        $entryKey = is_int($rawEntryId) ? (string) $rawEntryId : $rawEntryId;
        $entryNodeId = (is_string($entryKey) ? $idMap[$entryKey] ?? null : null) ?? $nodes[0]['id'];

        return [
            'entryNodeId' => $entryNodeId,
            'nodes' => $nodes,
        ];
    }
}
