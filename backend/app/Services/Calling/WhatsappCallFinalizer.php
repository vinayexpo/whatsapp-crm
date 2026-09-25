<?php

namespace App\Services\Calling;

use App\Events\ConversationUpdated;
use App\Events\MessageReceived;
use App\Events\WhatsappCallStatusUpdated;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappCall;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class WhatsappCallFinalizer
{
    public function __construct(private CallTurnRecorder $turnRecorder)
    {
    }

    public function finalize(WhatsappCall $whatsappCall): void
    {
        // Multiple concurrent turns (e.g. a burst of spurious STT results)
        // can each independently decide the call is over and dispatch this
        // job. Atomically claim finalization so only the first one runs —
        // otherwise every caller duplicates the "call ended" summary message.
        $claimed = WhatsappCall::query()
            ->whereKey($whatsappCall->id)
            ->whereNull('finalized_at')
            ->update(['finalized_at' => now()]);

        if (! $claimed) {
            return;
        }

        [$conversation, $message] = DB::transaction(function () use ($whatsappCall) {
            $conversation = $this->turnRecorder->resolveConversation($whatsappCall);

            if (! $whatsappCall->conversation_id) {
                $whatsappCall->update(['conversation_id' => $conversation->id]);
            }

            $summaryLine = $this->summaryLine($whatsappCall);

            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'direction' => $whatsappCall->direction === 'inbound' ? 'inbound' : 'outbound',
                'text' => $summaryLine,
                'status' => 'delivered',
                'sent_at' => now(),
            ]);

            $conversation->update([
                'last_message_at' => $message->sent_at,
                'unread_count' => $conversation->unread_count + 1,
            ]);

            return [$conversation, $message];
        });

        MessageReceived::dispatch($message->load('conversation'));
        ConversationUpdated::dispatch($conversation);
        WhatsappCallStatusUpdated::dispatch($whatsappCall->fresh());

        $this->notifyCallOutcome($whatsappCall->fresh());
    }

    private function summaryLine(WhatsappCall $whatsappCall): string
    {
        $direction = $whatsappCall->direction === 'inbound' ? 'Inbound' : 'Outbound';
        $outcome = match ($whatsappCall->status) {
            'completed' => $whatsappCall->needs_human_followup ? 'Completed, needs follow-up' : 'Completed',
            'missed' => 'Missed',
            'failed' => 'Failed',
            default => 'Ended',
        };

        $variablesSummary = collect($whatsappCall->collected_variables ?? [])
            ->map(fn ($value, $key) => "{$key}: {$value}")
            ->implode(', ');

        $line = "{$direction} WhatsApp call — {$outcome}.";

        return $variablesSummary ? "{$line} {$variablesSummary}." : $line;
    }

    private function notifyCallOutcome(WhatsappCall $whatsappCall): void
    {
        if (! in_array($whatsappCall->status, ['completed', 'missed'], true)) {
            return;
        }

        if (! Permission::where('name', 'whatsapp-calling.manage')->exists()) {
            return;
        }

        $dispatchService = app(NotificationDispatchService::class);
        $type = $whatsappCall->status === 'missed' ? 'whatsapp_call_missed' : 'whatsapp_call_completed';
        $title = $whatsappCall->status === 'missed' ? 'Missed WhatsApp call' : 'WhatsApp call completed';
        $contactName = $whatsappCall->contact?->name ?? 'a contact';
        $body = $whatsappCall->status === 'missed'
            ? "A WhatsApp call from {$contactName} was missed."
            : "A WhatsApp call with {$contactName} has completed.";

        User::query()
            ->where('company_id', $whatsappCall->company_id)
            ->permission('whatsapp-calling.manage')
            ->each(fn (User $user) => $dispatchService->notify(
                $user,
                $type,
                $title,
                $body,
                ['whatsappCallId' => $whatsappCall->uuid],
            ));
    }
}
