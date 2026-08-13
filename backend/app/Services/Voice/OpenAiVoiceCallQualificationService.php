<?php

namespace App\Services\Voice;

use App\Models\AiAssistantSetting;
use App\Models\VoiceAgent;
use App\Models\VoiceCall;
use App\Scopes\CompanyScope;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenAiVoiceCallQualificationService implements VoiceCallQualificationServiceInterface
{
    private const DONE_SENTINEL = 'DONE';

    public function nextQuestion(VoiceAgent $agent, VoiceCall $call): ?string
    {
        $settings = $this->settingsFor($agent);

        if (! $settings) {
            return $this->fallback()->nextQuestion($agent, $call);
        }

        $instruction = 'Based on the conversation so far and the qualification criteria, ask the single '.
            'next most relevant question to gather missing information. If all criteria are '.
            'sufficiently gathered, respond with exactly the word '.self::DONE_SENTINEL.' and nothing else.';

        $messages = $this->buildMessages($agent, $call, $instruction);

        $content = $this->complete($settings, $messages);

        if ($content === null) {
            return $this->fallback()->nextQuestion($agent, $call);
        }

        $content = trim($content);

        return $content === '' || strcasecmp($content, self::DONE_SENTINEL) === 0 ? null : $content;
    }

    public function judge(VoiceAgent $agent, VoiceCall $call): array
    {
        $settings = $this->settingsFor($agent);

        if (! $settings) {
            return $this->fallback()->judge($agent, $call);
        }

        $messages = $this->buildMessages($agent, $call, <<<'PROMPT'
        Based on the full conversation, produce a final qualification judgment. Respond with
        strict JSON only, no prose, no markdown fences, matching this shape exactly:
        {"outcome": "qualified"|"not_qualified"|"uncertain", "fields": {"<criterion_key>": "<value>"}, "summary": "<short prose summary>"}
        PROMPT);

        $content = $this->complete($settings, $messages);

        if ($content === null) {
            return $this->fallback()->judge($agent, $call);
        }

        $decoded = json_decode(trim($content), true);

        if (! is_array($decoded) || ! isset($decoded['outcome'], $decoded['fields'], $decoded['summary'])) {
            Log::warning('Voice qualification judgment could not be parsed', [
                'voice_agent_id' => $agent->id,
                'voice_call_id' => $call->id,
            ]);

            return $this->fallback()->judge($agent, $call);
        }

        return [
            'outcome' => $decoded['outcome'],
            'fields' => $decoded['fields'],
            'summary' => $decoded['summary'],
        ];
    }

    private function settingsFor(VoiceAgent $agent): ?AiAssistantSetting
    {
        $settings = AiAssistantSetting::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $agent->company_id)
            ->first();

        if (! $settings || empty($settings->base_url) || empty($settings->api_key) || empty($settings->model)) {
            return null;
        }

        return $settings;
    }

    private function complete(AiAssistantSetting $settings, array $messages): ?string
    {
        try {
            $response = Http::withToken($settings->api_key)
                ->timeout(30)
                ->post(rtrim($settings->base_url, '/').'/chat/completions', [
                    'model' => $settings->model,
                    'messages' => $messages,
                ]);

            if (! $response->successful()) {
                Log::warning('Voice qualification AI request failed', ['status' => $response->status()]);

                return null;
            }

            $content = $this->extractContent($response->json());

            return is_string($content) && trim($content) !== '' ? $content : null;
        } catch (Throwable $e) {
            Log::warning('Voice qualification AI request errored', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function buildMessages(VoiceAgent $agent, VoiceCall $call, string $instruction): array
    {
        $criteria = collect($agent->qualification_criteria ?? [])
            ->map(fn ($c) => "- {$c['key']} ({$c['label']}): {$c['question']}")
            ->implode("\n");

        $systemPrompt = ($agent->system_prompt ?: 'You are a friendly sales qualification agent conducting a phone call.')
            ."\n\nQualification criteria to gather:\n{$criteria}"
            ."\n\nPass criteria: ".($agent->qualification_pass_criteria ?: 'Use your judgment based on the gathered criteria.')
            ."\n\n{$instruction}";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($call->transcript ?? [] as $turn) {
            $messages[] = [
                'role' => $turn['role'] === 'lead' ? 'user' : 'assistant',
                'content' => $turn['text'],
            ];
        }

        return $messages;
    }

    private function fallback(): FakeVoiceCallQualificationService
    {
        return new FakeVoiceCallQualificationService;
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

        foreach ([
            data_get($payload, 'output_text'),
            data_get($payload, 'choices.0.text'),
            data_get($payload, 'choices.0.message.text'),
            data_get($payload, 'output.0.content.0.text'),
            data_get($payload, 'output.0.content.0.text.value'),
            data_get($payload, 'message.content.0.text.value'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }
}
