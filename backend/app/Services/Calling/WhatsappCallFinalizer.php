<?php

namespace App\Services\Calling;

use App\Events\ConversationUpdated;
use App\Events\MessageReceived;
use App\Events\WhatsappCallStatusUpdated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappCall;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class WhatsappCallFinalizer
{
    public function finalize(WhatsappCall $whatsappCall): void
    {
        [$conversation, $message] = DB::transaction(function () use ($whatsappCall) {
            $conversation = $whatsappCall->conversation;

            if (! $conversation) {
                $conversation = Conversation::withoutGlobalScopes()
                    ->where('contact_id', $whatsappCall->contact_id)->where('channel', 'whatsapp_call')->first();
            }

            if (! $conversation) {
                $conversation = new Conversation([
                    'contact_id' => $whatsappCall->contact_id,
                    'channel' => 'whatsapp_call',
                    'status' => 'open',
                    'unread_count' => 0,
                ]);
                $conversation->company_id = $whatsappCall->company_id;
                $conversation->save();
            }

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
