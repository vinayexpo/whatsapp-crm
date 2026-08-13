<?php

namespace App\Services\Chatbot;

use App\Models\Chatbot;
use App\Models\Conversation;

class FakeChatbotReplyService implements ChatbotReplyServiceInterface
{
    public function reply(Chatbot $chatbot, Conversation $conversation, string $visitorMessage): string
    {
        return "Fake reply to: {$visitorMessage}";
    }

    public function isHandoff(string $reply): bool
    {
        return false;
    }
}
