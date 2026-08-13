<?php

namespace App\Services\Chatbot;

use App\Models\Chatbot;

interface TrainingEntryGeneratorServiceInterface
{
    /**
     * Extract candidate question/answer pairs from raw source text for a
     * chatbot's training data. Returns an empty array on any failure.
     *
     * @return array<int, array{question: string, answer: string}>
     */
    public function generate(Chatbot $chatbot, string $sourceText): array;
}
