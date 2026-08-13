<?php

namespace App\Services\Chatbot;

use App\Models\Chatbot;
use App\Models\Conversation;

interface ChatbotReplyServiceInterface
{
    /**
     * Generate the chatbot's reply to a visitor message, grounded on the
     * chatbot's training entries and recent conversation history.
     */
    public function reply(Chatbot $chatbot, Conversation $conversation, string $visitorMessage): string;

    /**
     * Whether the given reply text indicates the bot could not help and the
     * conversation should be flagged for human follow-up.
     */
    public function isHandoff(string $reply): bool;
}
