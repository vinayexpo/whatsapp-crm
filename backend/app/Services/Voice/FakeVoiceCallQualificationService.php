<?php

namespace App\Services\Voice;

use App\Models\VoiceAgent;
use App\Models\VoiceCall;

class FakeVoiceCallQualificationService implements VoiceCallQualificationServiceInterface
{
    public function nextQuestion(VoiceAgent $agent, VoiceCall $call): ?string
    {
        $filledKeys = array_keys($call->qualification_fields ?? []);

        foreach ($agent->qualification_criteria ?? [] as $criterion) {
            if (! in_array($criterion['key'], $filledKeys, true)) {
                return $criterion['question'];
            }
        }

        return null;
    }

    public function judge(VoiceAgent $agent, VoiceCall $call): array
    {
        $criteriaKeys = array_column($agent->qualification_criteria ?? [], 'key');
        $fields = $call->qualification_fields ?? [];

        $allFilled = count($criteriaKeys) > 0
            && collect($criteriaKeys)->every(fn ($key) => filled($fields[$key] ?? null));

        return [
            'outcome' => $allFilled ? 'qualified' : 'uncertain',
            'fields' => $fields,
            'summary' => $allFilled
                ? 'Fake judgment: all qualification criteria were filled.'
                : 'Fake judgment: some qualification criteria are missing.',
        ];
    }
}
