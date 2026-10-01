<?php

namespace App\Services\Ai;

use App\Models\AiAssistantSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenAiCompletionClient
{
    /**
     * Call the company's configured OpenAI-compatible chat/completions endpoint.
     *
     * Returns the extracted text content, or null if the settings are
     * unconfigured, the request failed, or the response had no parseable
     * content. Callers are responsible for falling back on a null result.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function complete(?AiAssistantSetting $settings, array $messages, ?array $responseFormat = null, array $logContext = []): ?string
    {
        $baseUrl = $settings?->base_url;
        $apiKey = $settings?->api_key;
        $model = $settings?->model;

        if (empty($baseUrl) || empty($apiKey) || empty($model)) {
            return null;
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];

        if ($responseFormat !== null) {
            $payload['response_format'] = $responseFormat;
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post(rtrim($baseUrl, '/').'/chat/completions', $payload);

            if (! $response->successful()) {
                Log::warning('OpenAI-compatible completion request failed', array_merge($logContext, [
                    'status' => $response->status(),
                ]));

                return null;
            }

            $content = $this->extractContent($response->json());

            if (! is_string($content) || trim($content) === '') {
                Log::warning('OpenAI-compatible completion payload was empty or unsupported', array_merge($logContext, [
                    'keys' => array_keys($response->json()),
                ]));

                return null;
            }

            return trim($content);
        } catch (Throwable $e) {
            Log::warning('OpenAI-compatible completion request errored', array_merge($logContext, [
                'message' => $e->getMessage(),
            ]));

            return null;
        }
    }

    private function extractContent(array $payload): ?string
    {
        $content = data_get($payload, 'choices.0.message.content');

        if (is_string($content) && trim($content) !== '') {
            return trim($content);
        }

        if (is_array($content)) {
            $text = collect($content)
                ->map(function ($part) {
                    if (is_string($part)) {
                        return $part;
                    }

                    if (! is_array($part)) {
                        return null;
                    }

                    return data_get($part, 'text.value')
                        ?? data_get($part, 'text')
                        ?? data_get($part, 'content');
                })
                ->filter(fn ($part) => is_string($part) && trim($part) !== '')
                ->implode("\n");

            if ($text !== '') {
                return trim($text);
            }
        }

        $alternatives = [
            data_get($payload, 'output_text'),
            data_get($payload, 'choices.0.text'),
            data_get($payload, 'choices.0.message.text'),
            data_get($payload, 'output.0.content.0.text'),
            data_get($payload, 'output.0.content.0.text.value'),
            data_get($payload, 'message.content.0.text.value'),
        ];

        foreach ($alternatives as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }
}
