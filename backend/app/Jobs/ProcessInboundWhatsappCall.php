<?php

namespace App\Jobs;

use App\Events\ConversationUpdated;
use App\Events\WhatsappCallStatusUpdated;
use App\Models\ApiConnection;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use App\Models\WhatsappCall;
use App\Models\WhatsappCallFlow;
use App\Models\WebhookEvent;
use App\Jobs\Concerns\NotifiesOnFailure;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Throwable;

class ProcessInboundWhatsappCall implements ShouldQueue
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
        $entry = data_get($payload, 'entry.0.changes.0.value');
        $call = data_get($entry, 'calls.0');

        $fromNumber = data_get($call, 'from');
        $phoneNumberId = data_get($entry, 'metadata.phone_number_id');
        $metaCallId = data_get($call, 'id');

        if (! $fromNumber || ! $phoneNumberId || ! $metaCallId) {
            return;
        }

        $connection = ApiConnection::query()
            ->where('channel', 'whatsapp')
            ->where('phone_number_id', $phoneNumberId)
            ->where('calling_enabled', true)
            ->first();

        if (! $connection) {
            return;
        }

        $companyId = $connection->company_id;
        $flow = WhatsappCallFlow::query()->where('company_id', $companyId)
            ->where('api_connection_id', $connection->id)->where('status', 'active')->first();

        [$conversation, $whatsappCall] = DB::transaction(function () use ($fromNumber, $companyId, $flow, $metaCallId) {
            $contact = Contact::withoutGlobalScopes()->where('handle', $fromNumber)->where('channel', 'whatsapp')->first();

            if (! $contact) {
                $contact = new Contact([
                    'handle' => $fromNumber,
                    'channel' => 'whatsapp',
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
                ->where('contact_id', $contact->id)->where('channel', 'whatsapp_call')->first();

            if (! $conversation) {
                $conversation = new Conversation([
                    'contact_id' => $contact->id,
                    'channel' => 'whatsapp_call',
                    'status' => 'open',
                    'unread_count' => 0,
                ]);
                $conversation->company_id = $companyId;
                $conversation->save();
            }

            $whatsappCall = new WhatsappCall([
                'whatsapp_call_flow_id' => $flow?->id,
                'contact_id' => $contact->id,
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'status' => 'ringing',
                'meta_call_id' => $metaCallId,
                'started_at' => now(),
            ]);
            $whatsappCall->company_id = $companyId;
            $whatsappCall->save();

            return [$conversation, $whatsappCall];
        });

        $event->update(['processed_at' => now(), 'status' => 'processed']);

        ConversationUpdated::dispatch($conversation);
        WhatsappCallStatusUpdated::dispatch($whatsappCall);

        $this->notifyRinging($whatsappCall);
    }

    private function notifyRinging(WhatsappCall $whatsappCall): void
    {
        if (! Permission::where('name', 'whatsapp-calling.manage')->exists()) {
            return;
        }

        $dispatchService = app(NotificationDispatchService::class);
        $contactName = $whatsappCall->contact?->name ?? 'a contact';

        User::query()
            ->where('company_id', $whatsappCall->company_id)
            ->permission('whatsapp-calling.manage')
            ->each(fn (User $user) => $dispatchService->notify(
                $user,
                'whatsapp_call_ringing',
                'Incoming WhatsApp call',
                "A WhatsApp call from {$contactName} is ringing.",
                ['whatsappCallId' => $whatsappCall->uuid],
            ));
    }

    public function failed(Throwable $e): void
    {
        $payload = WebhookEvent::query()->find($this->webhookEventId)?->payload;
        $phoneNumberId = data_get($payload, 'entry.0.changes.0.value.metadata.phone_number_id');

        $companyId = ApiConnection::query()
            ->where('channel', 'whatsapp')
            ->when($phoneNumberId, fn ($query) => $query->where('phone_number_id', $phoneNumberId))
            ->where('calling_enabled', true)
            ->value('company_id');

        $this->recordFailure($e, $companyId, $this->webhookEventId);
    }
}
