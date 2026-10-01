<?php

namespace App\Services\Chatbot;

use App\Models\AiAssistantSetting;
use App\Models\Chatbot;
use App\Models\Conversation;
use App\Scopes\CompanyScope;
use App\Services\Ai\OpenAiCompletionClient;

class OpenAiChatbotReplyService implements ChatbotReplyServiceInterface
{
    private const FALLBACK_REPLY = "Sorry, I'm unable to answer right now.";

    private const HISTORY_LIMIT = 10;

    public function __construct(private OpenAiCompletionClient $client)
    {
    }

    public function reply(Chatbot $chatbot, Conversation $conversation, string $visitorMessage): string
    {
        $settings = AiAssistantSetting::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $chatbot->company_id)
            ->first();

        $messages = $this->buildMessages($chatbot, $conversation, $visitorMessage);

        $content = $this->client->complete($settings, $messages, null, ['chatbot_id' => $chatbot->id]);

        return $content ?? self::FALLBACK_REPLY;
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
}
