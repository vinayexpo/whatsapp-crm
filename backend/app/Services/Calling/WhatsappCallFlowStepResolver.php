<?php

namespace App\Services\Calling;

use App\Events\WhatsappCallStatusUpdated;
use App\Models\AiAssistantSetting;
use App\Models\WhatsappCall;
use App\Models\WhatsappCallFlow;
use App\Scopes\CompanyScope;
use App\Services\Ai\OpenAiCompletionClient;

class WhatsappCallFlowStepResolver
{
    // Hard safety net independent of the LLM's own judgment: a free-form
    // ai_conversation call is force-terminated after this many caller turns,
    // so a misbehaving model can never keep a caller on hold indefinitely.
    private const MAX_AI_CONVERSATION_TURNS = 20;

    public function __construct(
        private CallTurnRecorder $turnRecorder,
        private OpenAiCompletionClient $client,
    ) {
    }

    /**
     * Record the caller's speech turn (if any), advance the flow, and
     * return the next step as an array: {action, prompt, options?}.
     */
    public function resolve(WhatsappCall $whatsappCall, ?string $speech): array
    {
        $speech ??= '';

        if ($speech !== '') {
            $transcript = $whatsappCall->transcript ?? [];
            $transcript[] = ['role' => 'lead', 'text' => $speech, 'at' => now()->toIso8601String()];
            $attributes = ['transcript' => $transcript];

            // A duplicate/out-of-order turn (e.g. a retried sidecar request)
            // can reach this point after the call has already been marked
            // terminal elsewhere — never downgrade a finished call back to
            // in_progress.
            if (! in_array($whatsappCall->status, ['completed', 'failed', 'missed'], true)) {
                $attributes['status'] = 'in_progress';
            }

            $whatsappCall->update($attributes);
            $this->turnRecorder->record($whatsappCall, 'caller', $speech);
        }

        $flow = $whatsappCall->callFlow;

        if ($flow && $flow->conversation_mode === 'ai_conversation') {
            return $this->resolveAiConversation($whatsappCall, $flow, $speech);
        }

        $nodes = $flow?->nodes ?? [];
        $currentIndex = count($whatsappCall->collected_variables ?? []);

        if (! $flow || $currentIndex >= count($nodes)) {
            // Don't mark the call completed here -- the sidecar still has to
            // speak its closing line and actually tear down the peer
            // connection. Marking completed now (before any of that happens)
            // makes the inbox show "Completed" while the caller is still on
            // the line. The sidecar reports real completion via POST
            // .../ended once it has actually closed the session (see
            // SidecarCallController::ended()).
            return ['action' => 'terminate'];
        }

        $node = $nodes[$currentIndex];

        if (isset($node['variable_key']) && $speech !== '') {
            $variables = $whatsappCall->collected_variables ?? [];
            $variables[$node['variable_key']] = $speech;
            $whatsappCall->update(['collected_variables' => $variables]);
        }

        WhatsappCallStatusUpdated::dispatch($whatsappCall->fresh());

        if ($node['type'] === 'end_call') {
            return ['action' => 'terminate', 'prompt' => $node['prompt'] ?? null];
        }

        if ($node['type'] === 'transfer_human') {
            $whatsappCall->update(['needs_human_followup' => true]);

            return ['action' => 'terminate', 'prompt' => $node['prompt'] ?? null];
        }

        // Note: the AI's own spoken prompt is recorded separately, once the
        // sidecar confirms it actually spoke it (see SidecarCallController::spoken()).

        return [
            'action' => 'prompt',
            'prompt' => $node['prompt'] ?? null,
            'options' => $node['options'] ?? null,
        ];
    }

    /**
     * Drive a free-form, goal-directed conversation instead of walking fixed
     * nodes. The LLM is required to reply with structured JSON
     * {"say", "done", "handoff_to_human"} so termination is read from an
     * explicit field rather than parsed out of prose.
     */
    private function resolveAiConversation(WhatsappCall $whatsappCall, WhatsappCallFlow $flow, string $speech): array
    {
        $transcript = $whatsappCall->transcript ?? [];

        if (count($transcript) >= self::MAX_AI_CONVERSATION_TURNS) {
            return ['action' => 'terminate', 'prompt' => $flow->fallback_message];
        }

        $settings = AiAssistantSetting::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $flow->company_id)
            ->first();

        $messages = $this->buildAiConversationMessages($flow, $transcript);

        $content = $this->client->complete(
            $settings,
            $messages,
            ['type' => 'json_object'],
            ['whatsapp_call_id' => $whatsappCall->id, 'context' => 'ai_conversation'],
        );

        $decoded = $content !== null ? json_decode($content, true) : null;

        if (! is_array($decoded) || ! isset($decoded['say']) || ! is_string($decoded['say'])) {
            return ['action' => 'terminate', 'prompt' => $flow->fallback_message];
        }

        WhatsappCallStatusUpdated::dispatch($whatsappCall->fresh());

        if (! empty($decoded['handoff_to_human'])) {
            $whatsappCall->update(['needs_human_followup' => true]);

            return ['action' => 'terminate', 'prompt' => $decoded['say']];
        }

        if (! empty($decoded['done'])) {
            return ['action' => 'terminate', 'prompt' => $decoded['say']];
        }

        return ['action' => 'prompt', 'prompt' => $decoded['say']];
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function buildAiConversationMessages(WhatsappCallFlow $flow, array $transcript): array
    {
        $systemPrompt = 'You are an AI voice assistant conducting a phone call on behalf of a business. '
            .'Your goal for this call: '.($flow->ai_conversation_goal ?: 'Have a helpful, natural conversation with the caller.').' '
            .'Respond with ONLY a JSON object of the exact shape {"say": string, "done": boolean, "handoff_to_human": boolean}. '
            .'"say" is exactly what you will speak next. Set "done": true once you have accomplished the goal or the caller wants to end the call, '
            .'and set "handoff_to_human": true if the caller needs a human instead of you — in both cases "say" should be a short closing remark.';

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach ($transcript as $turn) {
            $role = match ($turn['role'] ?? null) {
                'lead', 'caller' => 'user',
                'ai' => 'assistant',
                default => null,
            };

            if ($role !== null && isset($turn['text'])) {
                $messages[] = ['role' => $role, 'content' => $turn['text']];
            }
        }

        return $messages;
    }
}
