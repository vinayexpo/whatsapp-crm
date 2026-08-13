<?php

namespace App\Services\Chatbot;

use App\Models\AiAssistantSetting;
use App\Models\Chatbot;
use App\Models\Conversation;
use App\Scopes\CompanyScope;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenAiChatbotReplyService implements ChatbotReplyServiceInterface
{
    private const FALLBACK_REPLY = "Sorry, I'm unable to answer right now.";

    private const HISTORY_LIMIT = 10;

    public function reply(Chatbot $chatbot, Conversation $conversation, string $visitorMessage): string
    {
        $settings = AiAssistantSetting::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $chatbot->company_id)
            ->first();

        $baseUrl = $settings?->base_url;
        $apiKey = $settings?->api_key;
        $model = $settings?->model;

        if (empty($baseUrl) || empty($apiKey) || empty($model)) {
            return self::FALLBACK_REPLY;
        }

        $messages = $this->buildMessages($chatbot, $conversation, $visitorMessage);

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post(rtrim($baseUrl, '/').'/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                ]);

            if (! $response->successful()) {
                Log::warning('Chatbot AI reply request failed', [
                    'chatbot_id' => $chatbot->id,
                    'status' => $response->status(),
                ]);

                return self::FALLBACK_REPLY;
            }

            $content = $this->extractContent($response->json());

            if (! is_string($content) || trim($content) === '') {
                Log::warning('Chatbot AI reply payload was empty or unsupported', [
                    'chatbot_id' => $chatbot->id,
                    'keys' => array_keys($response->json()),
                ]);

                return self::FALLBACK_REPLY;
            }

            return trim($content);
        } catch (Throwable $e) {
            Log::warning('Chatbot AI reply request errored', [
                'chatbot_id' => $chatbot->id,
                'message' => $e->getMessage(),
            ]);

            return self::FALLBACK_REPLY;
        }
    }

    public function isHandoff(string $reply): bool
    {
        return trim($reply) === self::FALLBACK_REPLY;
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function buildMessages(Chatbot $chatbot, Conversation $conversation, string $visitorMessage): array
    {
        $trainingEntries = $chatbot->trainingEntries()->orderByDesc('created_at')->get();

        $channelDescription = match ($conversation->channel) {
            'instagram' => 'via Instagram Direct Message',
            'whatsapp' => 'via WhatsApp',
            default => 'via a website chat widget',
        };

        $systemPrompt = "You are a helpful assistant answering questions on behalf of \"{$chatbot->name}\" {$channelDescription}. ".
            'Use the following question/answer knowledge base as grounding context when relevant. ';

        $systemPrompt .= $chatbot->general_fallback_enabled
            ? "If a question can't be answered from this context, answer helpfully and concisely anyway, but don't invent company-specific facts."
            : "If a question can't be answered from this knowledge base, politely say you don't have that information and offer to connect them with a human — do not guess or use outside knowledge.";

        if ($trainingEntries->isNotEmpty()) {
            $systemPrompt .= "\n\nKnowledge base:\n";
            $systemPrompt .= $trainingEntries->map(fn ($entry) => "Q: {$entry->question}\nA: {$entry->answer}")->implode("\n\n");
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        $history = $conversation->messages()
            ->orderByDesc('sent_at')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->values();

        foreach ($history as $message) {
            $messages[] = [
                'role' => $message->direction === 'inbound' ? 'user' : 'assistant',
                'content' => $message->text,
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $visitorMessage];

        return $messages;
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
