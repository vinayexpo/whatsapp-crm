<?php

namespace App\Services\Voice;

use App\Models\VoiceAgent;
use App\Models\VoiceCall;

interface VoiceCallQualificationServiceInterface
{
    /**
     * Given the call's transcript-so-far and which qualification_criteria
     * fields are still unfilled, return the next question to ask (or null
     * if enough information has been gathered to move to final judgment).
     */
    public function nextQuestion(VoiceAgent $agent, VoiceCall $call): ?string;

    /**
     * Produce the final structured judgment: fills remaining
     * qualification_fields from the transcript, decides pass/fail/uncertain
     * against qualification_pass_criteria, and writes a prose summary.
     *
     * @return array{outcome: string, fields: array<string,mixed>, summary: string}
     */
    public function judge(VoiceAgent $agent, VoiceCall $call): array;
}
