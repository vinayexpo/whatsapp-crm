<?php

namespace App\Services\Chatbot;

use App\Models\AiAssistantSetting;
use App\Models\Chatbot;
use App\Scopes\CompanyScope;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenAiTrainingEntryGeneratorService implements TrainingEntryGeneratorServiceInterface
{
    public function generate(Chatbot $chatbot, string $sourceText): array
    {
        $settings = AiAssistantSetting::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $chatbot->company_id)
            ->first();

        $baseUrl = $settings?->base_url;
        $apiKey = $settings?->api_key;
        $model = $settings?->model;

        if (empty($baseUrl) || empty($apiKey) || empty($model)) {
            return [];
        }

        $systemPrompt = 'You extract training question/answer pairs from source text for a customer-support chatbot. '.
            'Read the provided text and produce a JSON object of the form {"pairs":[{"question":"...","answer":"..."}]} '.
            'containing distinct, self-contained question/answer pairs that capture the key facts in the text. '.
            'Only use information present in the text. Respond with JSON only, no other commentary.';

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post(rtrim($baseUrl, '/').'/chat/completions', [
                    'model' => $model,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $sourceText],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Chatbot training-entry generation request failed', [
                    'chatbot_id' => $chatbot->id,
                    'status' => $response->status(),
                ]);

                return [];
            }

            $content = data_get($response->json(), 'choices.0.message.content');

            if (! is_string($content) || trim($content) === '') {
                return [];
            }

            $decoded = json_decode($content, true);
            $pairs = $decoded['pairs'] ?? null;

            if (! is_array($pairs)) {
                return [];
            }

            return collect($pairs)
                ->filter(fn ($pair) => is_array($pair)
                    && is_string($pair['question'] ?? null)
                    && is_string($pair['answer'] ?? null)
                    && trim($pair['question']) !== ''
                    && trim($pair['answer']) !== '')
                ->map(fn ($pair) => [
                    'question' => trim($pair['question']),
                    'answer' => trim($pair['answer']),
                ])
                ->values()
                ->all();
        } catch (Throwable $e) {
            Log::warning('Chatbot training-entry generation request errored', [
                'chatbot_id' => $chatbot->id,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
