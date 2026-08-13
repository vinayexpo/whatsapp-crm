<?php

namespace App\Services\Voice;

use App\Events\ConversationUpdated;
use App\Events\MessageReceived;
use App\Events\VoiceCallStatusUpdated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\VoiceCall;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class VoiceCallFinalizer
{
    public function finalize(VoiceCall $voiceCall): void
    {
        $agent = $voiceCall->voiceAgent;

        if ($agent && empty($voiceCall->qualification_outcome)) {
            $judgment = app(VoiceCallQualificationServiceInterface::class)->judge($agent, $voiceCall);

            $voiceCall->update([
                'qualification_fields' => $judgment['fields'],
                'qualification_outcome' => $judgment['outcome'],
                'qualification_summary' => $judgment['summary'],
                'needs_human_followup' => $judgment['outcome'] !== 'qualified',
            ]);
        }

        $voiceCall = $voiceCall->fresh();

        if ($voiceCall->qualification_outcome === 'qualified') {
            $voiceCall->contact()->update(['pipeline_stage_id' => 'qualified']);
        }

        [$conversation, $message] = DB::transaction(function () use ($voiceCall) {
            $conversation = $voiceCall->conversation;

            if (! $conversation) {
                $conversation = Conversation::withoutGlobalScopes()
                    ->where('contact_id', $voiceCall->contact_id)->where('channel', 'voice')->first();
            }

            if (! $conversation) {
                $conversation = new Conversation([
                    'contact_id' => $voiceCall->contact_id,
                    'channel' => 'voice',
                    'status' => 'open',
                    'unread_count' => 0,
                ]);
                $conversation->company_id = $voiceCall->company_id;
                $conversation->save();
            }

            if (! $voiceCall->conversation_id) {
                $voiceCall->update(['conversation_id' => $conversation->id]);
            }

            $summaryLine = $this->summaryLine($voiceCall);

            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'direction' => $voiceCall->direction === 'inbound' ? 'inbound' : 'outbound',
                'text' => $summaryLine,
                'status' => 'delivered',
                'attachment_url' => $voiceCall->recording_url,
                'attachment_type' => $voiceCall->recording_url ? 'audio' : null,
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
        VoiceCallStatusUpdated::dispatch($voiceCall->fresh());

        $this->notifyCallOutcome($voiceCall->fresh());
    }

    private function notifyCallOutcome(VoiceCall $voiceCall): void
    {
        if (! in_array($voiceCall->status, ['completed', 'no_answer'], true)) {
            return;
        }

        if (! Permission::where('name', 'voice-agents.manage')->exists()) {
            return;
        }

        $dispatchService = app(NotificationDispatchService::class);
        $type = $voiceCall->status === 'no_answer' ? 'voice_call_missed' : 'voice_call_completed';
        $title = $voiceCall->status === 'no_answer' ? 'Missed AI voice call' : 'AI voice call completed';
        $contactName = $voiceCall->contact?->name ?? 'a contact';
        $body = $voiceCall->status === 'no_answer'
            ? "An AI voice call to {$contactName} went unanswered."
            : "An AI voice call with {$contactName} has completed.";

        User::query()
            ->where('company_id', $voiceCall->company_id)
            ->permission('voice-agents.manage')
            ->each(fn (User $user) => $dispatchService->notify(
                $user,
                $type,
                $title,
                $body,
                ['voiceCallId' => $voiceCall->uuid],
            ));
    }

    private function summaryLine(VoiceCall $voiceCall): string
    {
        $direction = $voiceCall->direction === 'inbound' ? 'Inbound' : 'Outbound';
        $outcome = match ($voiceCall->qualification_outcome) {
            'qualified' => 'Qualified',
            'not_qualified' => 'Not qualified',
            default => 'Needs review',
        };

        $fieldsSummary = collect($voiceCall->qualification_fields ?? [])
            ->map(fn ($value, $key) => "{$key}: {$value}")
            ->implode(', ');

        $line = "{$direction} call completed — {$outcome}.";

        return $fieldsSummary ? "{$line} {$fieldsSummary}." : $line;
    }
}
