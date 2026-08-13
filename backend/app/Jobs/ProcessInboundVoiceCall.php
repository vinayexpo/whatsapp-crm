<?php

namespace App\Jobs;

use App\Events\ConversationUpdated;
use App\Events\VoiceCallStatusUpdated;
use App\Models\ApiConnection;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use App\Models\VoiceAgent;
use App\Models\VoiceCall;
use App\Models\WebhookEvent;
use App\Jobs\Concerns\NotifiesOnFailure;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Throwable;

class ProcessInboundVoiceCall implements ShouldQueue
{
    use Queueable, NotifiesOnFailure;

    public function __construct(public int $webhookEventId) {}

    public function handle(): void
    {
        $event = WebhookEvent::query()->find($this->webhookEventId);

        if (! $event) {
            return;
        }

        $payload = $event->payload;
        $fromNumber = data_get($payload, 'From');
        $toNumber = data_get($payload, 'To');
        $callSid = data_get($payload, 'CallSid');

        if (! $fromNumber || ! $toNumber || ! $callSid) {
            return;
        }

        $connection = ApiConnection::query()->where('channel', 'voice')->where('twilio_phone_number', $toNumber)->first();

        if (! $connection) {
            return;
        }

        $companyId = $connection->company_id;
        $agent = VoiceAgent::query()->where('company_id', $companyId)->where('status', 'active')->first();

        [$conversation, $voiceCall] = DB::transaction(function () use ($fromNumber, $companyId, $agent, $callSid) {
            $contact = Contact::withoutGlobalScopes()->where('handle', $fromNumber)->where('channel', 'voice')->first();

            if (! $contact) {
                $contact = new Contact([
                    'handle' => $fromNumber,
                    'channel' => 'voice',
                    'name' => $fromNumber,
                    'phone' => $fromNumber,
                    'pipeline_stage_id' => 'new-lead',
                    'last_interaction_at' => now(),
                    'notes' => [],
                    'purchases' => [],
                ]);
                $contact->company_id = $companyId;
                $contact->save();
            }

            $conversation = Conversation::withoutGlobalScopes()
                ->where('contact_id', $contact->id)->where('channel', 'voice')->first();

            if (! $conversation) {
                $conversation = new Conversation([
                    'contact_id' => $contact->id,
                    'channel' => 'voice',
                    'status' => 'open',
                    'unread_count' => 0,
                ]);
                $conversation->company_id = $companyId;
                $conversation->save();
            }

            $voiceCall = new VoiceCall([
                'voice_agent_id' => $agent?->id,
                'contact_id' => $contact->id,
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'medium' => 'telephony',
                'status' => 'ringing',
                'provider_call_sid' => $callSid,
                'started_at' => now(),
            ]);
            $voiceCall->company_id = $companyId;
            $voiceCall->save();

            return [$conversation, $voiceCall];
        });

        $event->update(['processed_at' => now(), 'status' => 'processed']);

        ConversationUpdated::dispatch($conversation);
        VoiceCallStatusUpdated::dispatch($voiceCall);

        $this->notifyRinging($voiceCall);
    }

    private function notifyRinging(VoiceCall $voiceCall): void
    {
        if (! Permission::where('name', 'voice-agents.manage')->exists()) {
            return;
        }

        $dispatchService = app(NotificationDispatchService::class);
        $contactName = $voiceCall->contact?->name ?? 'a contact';

        User::query()
            ->where('company_id', $voiceCall->company_id)
            ->permission('voice-agents.manage')
            ->each(fn (User $user) => $dispatchService->notify(
                $user,
                'voice_call_ringing',
                'Incoming AI voice call',
                "An AI voice call from {$contactName} is ringing.",
                ['voiceCallId' => $voiceCall->uuid],
            ));
    }

    public function failed(Throwable $e): void
    {
        $payload = WebhookEvent::query()->find($this->webhookEventId)?->payload;
        $toNumber = data_get($payload, 'To');

        $companyId = ApiConnection::query()
            ->where('channel', 'voice')
            ->when($toNumber, fn ($query) => $query->where('twilio_phone_number', $toNumber))
            ->value('company_id');

        $this->recordFailure($e, $companyId, $this->webhookEventId);
    }
}
