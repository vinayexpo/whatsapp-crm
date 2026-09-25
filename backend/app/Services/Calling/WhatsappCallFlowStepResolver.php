<?php

namespace App\Services\Calling;

use App\Jobs\ProcessWhatsappCallCompletion;
use App\Events\WhatsappCallStatusUpdated;
use App\Models\WhatsappCall;

class WhatsappCallFlowStepResolver
{
    public function __construct(private CallTurnRecorder $turnRecorder)
    {
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
            $whatsappCall->update(['transcript' => $transcript, 'status' => 'in_progress']);
            $this->turnRecorder->record($whatsappCall, 'caller', $speech);
        }

        $flow = $whatsappCall->callFlow;
        $nodes = $flow?->nodes ?? [];
        $currentIndex = count($whatsappCall->collected_variables ?? []);

        if (! $flow || $currentIndex >= count($nodes)) {
            $this->markCompleted($whatsappCall);
            ProcessWhatsappCallCompletion::dispatch($whatsappCall->id);

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
            $this->markCompleted($whatsappCall);
            ProcessWhatsappCallCompletion::dispatch($whatsappCall->id);

            return ['action' => 'terminate', 'prompt' => $node['prompt'] ?? null];
        }

        if ($node['type'] === 'transfer_human') {
            $whatsappCall->update(['needs_human_followup' => true]);
            $this->markCompleted($whatsappCall);
            ProcessWhatsappCallCompletion::dispatch($whatsappCall->id);

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

    // Meta's own status webhook may or may not deliver a terminal status for
    // sidecar-driven calls (it fires independently of the flow reaching its
    // last node), so the resolver marks the call completed itself the moment
    // it decides the conversation is over — otherwise the call sits stuck at
    // status=in_progress forever.
    private function markCompleted(WhatsappCall $whatsappCall): void
    {
        if (in_array($whatsappCall->status, ['completed', 'failed', 'missed'], true)) {
            return;
        }

        $whatsappCall->update(['status' => 'completed', 'ended_at' => now()]);
    }
}
