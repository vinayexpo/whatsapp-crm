<?php

namespace App\Jobs;

use App\Events\ConversationUpdated;
use App\Jobs\Concerns\NotifiesOnFailure;
use App\Models\ApiConnection;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WebhookEvent;
use App\Scopes\CompanyScope;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessWhatsAppHistorySync implements ShouldQueue
{
    use Queueable, NotifiesOnFailure;

    public function __construct(public int $webhookEventId) {}

    public function handle(): void
    {
        $event = WebhookEvent::query()->find($this->webhookEventId);

        if (! $event) {
            return;
        }

        $updatedConversationIds = [];

        foreach (data_get($event->payload, 'entry', []) as $entry) {
            foreach (data_get($entry, 'changes', []) as $change) {
                $value = data_get($change, 'value', []);
                $sync = data_get($value, 'smb_app_state_sync');

                if (! $sync) {
                    continue;
                }

                $phoneNumberId = data_get($value, 'metadata.phone_number_id');
                $connection = ApiConnection::findWhatsAppByPhoneNumberId($phoneNumberId);

                if (! $connection) {
                    continue;
                }

                $updatedConversationIds += $this->importSync($connection, $sync);
            }
        }

        foreach (array_unique($updatedConversationIds) as $conversationId) {
            $conversation = Conversation::withoutGlobalScope(CompanyScope::class)->find($conversationId);

            if ($conversation) {
                ConversationUpdated::dispatch($conversation);
            }
        }

        $event->update(['processed_at' => now(), 'status' => 'processed']);
    }

    /**
     * @return array<int, int> conversation IDs touched by this batch
     */
    private function importSync(ApiConnection $connection, array $sync): array
    {
        $companyId = $connection->company_id;
        $touchedConversationIds = [];

        $contactsByWaId = [];
        foreach (array_chunk(data_get($sync, 'contacts', []), 200) as $chunk) {
            foreach ($chunk as $syncContact) {
                $waId = data_get($syncContact, 'wa_id') ?? data_get($syncContact, 'phone_number');

                if (! $waId) {
                    continue;
                }

                $contact = $this->findOrCreateContact($companyId, $waId, data_get($syncContact, 'full_name') ?? data_get($syncContact, 'first_name') ?? $waId);
                $contactsByWaId[$waId] = $contact;
            }
        }

        foreach (array_chunk(data_get($sync, 'messages', []), 200) as $chunk) {
            foreach ($chunk as $syncMessage) {
                $waId = data_get($syncMessage, 'wa_id') ?? data_get($syncMessage, 'from') ?? data_get($syncMessage, 'to');

                if (! $waId) {
                    continue;
                }

                $contact = $contactsByWaId[$waId] ?? $this->findOrCreateContact($companyId, $waId, $waId);
                $contactsByWaId[$waId] = $contact;

                $conversation = $this->findOrCreateConversation($companyId, $contact->id, $connection->id);

                $externalId = data_get($syncMessage, 'id');
                $existingMessage = $externalId
                    ? Message::query()->where('external_message_id', $externalId)->first()
                    : null;

                if ($existingMessage) {
                    continue;
                }

                $direction = data_get($syncMessage, 'from') === $waId ? 'inbound' : 'outbound';
                $type = data_get($syncMessage, 'type', 'text');
                $text = data_get($syncMessage, 'text.body') ?? data_get($syncMessage, "{$type}.caption", '');
                $sentAt = data_get($syncMessage, 'timestamp')
                    ? now()->createFromTimestamp((int) data_get($syncMessage, 'timestamp'))
                    : now();

                Message::query()->create([
                    'conversation_id' => $conversation->id,
                    'direction' => $direction,
                    'text' => $text,
                    'status' => 'delivered',
                    'external_message_id' => $externalId,
                    'sent_at' => $sentAt,
                    'origin' => $direction === 'outbound' ? 'smb_app' : null,
                ]);

                if (! isset($touchedConversationIds[$conversation->id]) || $sentAt->gt($conversation->last_message_at ?? $sentAt->copy()->subCentury())) {
                    $conversation->update(['last_message_at' => $sentAt]);
                }

                $touchedConversationIds[$conversation->id] = true;
            }
        }

        return array_keys($touchedConversationIds);
    }

    private function findOrCreateContact(?int $companyId, string $waId, string $name): Contact
    {
        $contact = Contact::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('handle', $waId)
            ->where('channel', 'whatsapp')
            ->first();

        if ($contact) {
            return $contact;
        }

        $contact = new Contact([
            'name' => $name,
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

        return $contact;
    }

    private function findOrCreateConversation(?int $companyId, int $contactId, int $connectionId): Conversation
    {
        $conversation = Conversation::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('contact_id', $contactId)
            ->where('channel', 'whatsapp')
            ->first();

        if ($conversation) {
            return $conversation;
        }

        $conversation = new Conversation([
            'contact_id' => $contactId,
            'channel' => 'whatsapp',
            'status' => 'open',
            'unread_count' => 0,
            'api_connection_id' => $connectionId,
        ]);
        $conversation->company_id = $companyId;
        $conversation->save();

        return $conversation;
    }

    public function failed(Throwable $e): void
    {
        $payload = WebhookEvent::query()->find($this->webhookEventId)?->payload;
        $phoneNumberId = data_get($payload, 'entry.0.changes.0.value.metadata.phone_number_id');
        $companyId = ApiConnection::findWhatsAppByPhoneNumberId($phoneNumberId)?->company_id;

        $this->recordFailure($e, $companyId, $this->webhookEventId);
    }
}
