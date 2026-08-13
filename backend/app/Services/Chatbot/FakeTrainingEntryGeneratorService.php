<?php

namespace App\Services\Chatbot;

use App\Models\Chatbot;

class FakeTrainingEntryGeneratorService implements TrainingEntryGeneratorServiceInterface
{
    public function generate(Chatbot $chatbot, string $sourceText): array
    {
        $firstSentence = trim(explode('.', $sourceText)[0] ?? $sourceText);

        if ($firstSentence === '') {
            return [];
        }

        return [
            ['question' => "What is: {$firstSentence}?", 'answer' => $firstSentence],
        ];
    }
}
