<?php

namespace App\Jobs;

use App\Events\ConversationUpdated;
use App\Events\MessageReceived;
use App\Events\MessageStatusUpdated;
use App\Models\ApiConnection;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WebhookEvent;
use App\Jobs\Concerns\NotifiesOnFailure;
use App\Scopes\CompanyScope;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessInboundWhatsAppMessage implements ShouldQueue
{
    use Queueable, NotifiesOnFailure;

    public function __construct(public int $webhookEventId) {}

    public function handle(): void
    {
        $event = WebhookEvent::query()->find($this->webhookEventId);

        if (! $event) {
            return;
        }

        foreach (data_get($event->payload, 'entry', []) as $entry) {
            foreach (data_get($entry, 'changes', []) as $change) {
                $value = data_get($change, 'value', []);

                foreach (data_get($value, 'messages', []) as $inboundMessage) {
                    $this->storeInboundMessage($value, $inboundMessage);
                }

                foreach (data_get($value, 'statuses', []) as $status) {
                    $this->applyStatusUpdate($status);
                }
            }
        }

        $event->update(['processed_at' => now(), 'status' => 'processed']);
    }

    private function storeInboundMessage(array $value, array $inboundMessage): void
    {
        $waId = $inboundMessage['from'] ?? null;

        if (! $waId) {
            return;
        }

        $profileName = collect(data_get($value, 'contacts', []))
            ->firstWhere('wa_id', $waId)['profile']['name'] ?? $waId;

        $companyId = ApiConnection::query()->where('channel', 'whatsapp')->value('company_id');

        [$conversation, $message, $isNewContact] = DB::transaction(function () use ($waId, $profileName, $inboundMessage, $companyId) {
            $contact = Contact::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $companyId)
                ->where('handle', $waId)
                ->where('channel', 'whatsapp')
                ->first();

            if (! $contact) {
                $contact = new Contact([
                    'name' => $profileName,
                    'channel' => 'whatsapp',
                    'handle' => $waId,
                    'phone' => $waId,
                    'pipeline_stage_id' => 'new-lead',
                    'last_interaction_at' => now(),
                    'notes' => [],
                    'purchases' => [],
                ]);
                $contact->company_id = $companyId;
                $contact->save();
            }

            $isNewContact = $contact->wasRecentlyCreated;

            $conversation = Conversation::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $companyId)
                ->where('contact_id', $contact->id)
                ->where('channel', 'whatsapp')
                ->first();

            if (! $conversation) {
                $conversation = new Conversation([
                    'contact_id' => $contact->id,
                    'channel' => 'whatsapp',
                    'status' => 'open',
                    'unread_count' => 0,
                ]);
                $conversation->company_id = $companyId;
                $conversation->save();
            }

            $text = data_get($inboundMessage, 'text.body', '');
            $sentAt = isset($inboundMessage['timestamp'])
                ? now()->createFromTimestamp((int) $inboundMessage['timestamp'])
                : now();

            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'text' => $text,
                'status' => 'delivered',
                'external_message_id' => $inboundMessage['id'] ?? null,
                'sent_at' => $sentAt,
            ]);

            $conversation->update([
                'last_message_at' => $sentAt,
                'unread_count' => $conversation->unread_count + 1,
                'no_reply_notified_at' => null,
            ]);

            $contact->update(['last_interaction_at' => $sentAt]);

            return [$conversation, $message, $isNewContact];
        });

        MessageReceived::dispatch($message->load('conversation'));
        ConversationUpdated::dispatch($conversation);

        if ($conversation->assigned_to && $agent = $conversation->assignedTo()->first()) {
            app(NotificationDispatchService::class)->notify(
                $agent,
                'new_message',
                'New WhatsApp message',
                "New message from {$profileName}",
                ['conversationId' => $conversation->uuid],
            );
        }

        EvaluateAutomationFlows::dispatch($conversation->id, $message->id, $isNewContact);
        GenerateChatbotWhatsAppReply::dispatch($conversation->id, $message->id);
    }

    private function applyStatusUpdate(array $status): void
    {
        $externalId = $status['id'] ?? null;
        $newStatus = $status['status'] ?? null;

        if (! $externalId || ! in_array($newStatus, ['sent', 'delivered', 'read', 'failed'], true)) {
            return;
        }

        $message = Message::query()->where('external_message_id', $externalId)->first();

        if (! $message) {
            return;
        }

        $message->update(['status' => $newStatus]);
        MessageStatusUpdated::dispatch($message->load('conversation'));
    }

    public function failed(Throwable $e): void
    {
        $companyId = ApiConnection::query()->where('channel', 'whatsapp')->value('company_id');

        $this->recordFailure($e, $companyId, $this->webhookEventId);
    }
}
