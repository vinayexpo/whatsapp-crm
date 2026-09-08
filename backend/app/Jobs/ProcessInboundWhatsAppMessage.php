<?php

namespace App\Jobs;

use App\Events\ConversationCreated;
use App\Events\ConversationUpdated;
use App\Events\MessageReceived;
use App\Events\MessageStatusUpdated;
use App\Models\ActivityLog;
use App\Models\ApiConnection;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Models\WhatsappCall;
use App\Events\WhatsappCallStatusUpdated;
use App\Jobs\Concerns\NotifiesOnFailure;
use App\Scopes\CompanyScope;
use App\Services\ChatFlow\ChatMenuFlowEngine;
use App\Services\Messaging\WhatsAppMediaDownloader;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        $connection = ApiConnection::query()->where('channel', 'whatsapp')->first();
        $companyId = $connection?->company_id;

        $attachment = $this->resolveInboundAttachment($inboundMessage, $connection);

        [$conversation, $message, $isNewContact, $isNewConversation] = DB::transaction(function () use ($waId, $profileName, $inboundMessage, $companyId, $attachment) {
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

            $isNewConversation = ! $conversation;

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

            $type = $inboundMessage['type'] ?? 'text';
            $text = data_get($inboundMessage, 'text.body') ?? data_get($inboundMessage, "{$type}.caption", '');
            $sentAt = isset($inboundMessage['timestamp'])
                ? now()->createFromTimestamp((int) $inboundMessage['timestamp'])
                : now();

            $interactiveReplyId = null;

            if ($type === 'interactive') {
                $interactiveType = data_get($inboundMessage, 'interactive.type');
                $reply = data_get($inboundMessage, "interactive.{$interactiveType}");

                if ($reply) {
                    $interactiveReplyId = $reply['id'] ?? null;
                    $text = $reply['title'] ?? $text;
                }
            }

            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'text' => $text,
                'status' => 'delivered',
                'external_message_id' => $inboundMessage['id'] ?? null,
                'sent_at' => $sentAt,
                'attachment_url' => $attachment['url'] ?? null,
                'attachment_type' => $attachment['type'] ?? null,
                'interactive_reply_id' => $interactiveReplyId,
            ]);

            $conversation->update([
                'last_message_at' => $sentAt,
                'unread_count' => $conversation->unread_count + 1,
                'no_reply_notified_at' => null,
            ]);

            $contact->update(['last_interaction_at' => $sentAt]);

            return [$conversation, $message, $isNewContact, $isNewConversation];
        });

        CampaignRecipient::markMostRecentAsRepliedForContact($conversation->contact_id);

        if ($isNewContact) {
            $this->logActivity($companyId, 'contact', "New contact added: {$profileName}", 'whatsapp');
        }

        $this->logActivity($companyId, 'message', "New WhatsApp message from {$profileName}", 'whatsapp');

        MessageReceived::dispatch($message->load('conversation'));

        // A brand-new conversation has no agent subscribed to its per-conversation
        // broadcast channel yet -- the inbox only learns about conversations it
        // already knows about, so announce it on the company-wide channel instead.
        if ($isNewConversation) {
            ConversationCreated::dispatch($conversation);
        } else {
            ConversationUpdated::dispatch($conversation);
        }

        $dispatchService = app(NotificationDispatchService::class);

        User::query()
            ->where('company_id', $companyId)
            ->permission('conversations.reply')
            ->each(fn (User $user) => $dispatchService->notify(
                $user,
                'new_message',
                'New WhatsApp message',
                "New message from {$profileName}",
                ['conversationId' => $conversation->uuid],
            ));

        $handledByChatFlow = app(ChatMenuFlowEngine::class)->handle($conversation, $message);

        if (! $handledByChatFlow) {
            EvaluateAutomationFlows::dispatch($conversation->id, $message->id, $isNewContact);
            GenerateChatbotWhatsAppReply::dispatch($conversation->id, $message->id);
        }
    }

    private function logActivity(?int $companyId, string $type, string $description, string $channel): void
    {
        $activityLog = new ActivityLog([
            'type' => $type,
            'description' => $description,
            'channel' => $channel,
            'occurred_at' => now(),
        ]);
        $activityLog->company_id = $companyId;
        $activityLog->save();
    }

    /**
     * @return array{url: string, type: string}|null
     */
    private function resolveInboundAttachment(array $inboundMessage, ?ApiConnection $connection): ?array
    {
        $type = $inboundMessage['type'] ?? 'text';
        $media = $inboundMessage[$type] ?? null;

        if (! $connection || ! $media || ! isset($media['id']) || ! in_array($type, ['image', 'video', 'audio', 'document'], true)) {
            return null;
        }

        return app(WhatsAppMediaDownloader::class)->download(
            $media['id'],
            $media['mime_type'] ?? 'application/octet-stream',
            $connection,
        );
    }

    private function applyStatusUpdate(array $status): void
    {
        $externalId = $status['id'] ?? null;
        $newStatus = $status['status'] ?? null;

        Log::info('Received WhatsApp status webhook', ['status' => $status]);

        if (! $externalId || ! in_array($newStatus, ['sent', 'delivered', 'read', 'failed'], true)) {
            return;
        }

        $message = Message::query()->where('external_message_id', $externalId)->first();

        if (! $message) {
            $this->applyPermissionRequestStatusUpdate($externalId, $newStatus, $status);

            return;
        }

        $message->update(['status' => $newStatus]);

        // A message can be accepted by the initial API call (getting a real
        // external_message_id) and still be rejected asynchronously via this
        // status webhook -- e.g. error 131047 when the recipient falls
        // outside the 24-hour window. Propagate that failure back onto the
        // campaign recipient so it doesn't keep showing a stale "sent".
        if ($newStatus === 'failed') {
            $errors = $status['errors'] ?? [];
            $reason = $errors[0]['message'] ?? $errors[0]['title'] ?? null;
            $details = $errors[0]['error_data']['details'] ?? null;

            CampaignRecipient::query()->where('message_id', $message->id)->update([
                'status' => 'failed',
                'failure_reason' => trim(($reason ?? 'Message delivery failed.').($details ? " {$details}" : '')),
            ]);
        } elseif (in_array($newStatus, ['delivered', 'read'], true)) {
            $this->applyCampaignRecipientStatus($message->id, $newStatus);
        }

        MessageStatusUpdated::dispatch($message->load('conversation'));
    }

    private function applyCampaignRecipientStatus(int $messageId, string $newStatus): void
    {
        $recipient = CampaignRecipient::query()->where('message_id', $messageId)->first();

        if (! $recipient || $recipient->status === 'failed') {
            return;
        }

        // Meta redelivers status webhooks, and delivered can arrive after read
        // out of order -- only move the recipient forward, and only increment
        // each campaign counter the first time that status is reached.
        $rank = ['pending' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'replied' => 4];

        if (($rank[$newStatus] ?? 0) <= ($rank[$recipient->status] ?? 0)) {
            return;
        }

        $previousRank = $rank[$recipient->status] ?? 0;

        $recipient->update([
            'status' => $newStatus,
            'delivered_at' => $newStatus === 'delivered' ? now() : $recipient->delivered_at,
            'read_at' => $newStatus === 'read' ? now() : $recipient->read_at,
        ]);

        // If "read" arrives without a prior "delivered" webhook (Meta doesn't
        // guarantee ordering), the recipient skips the delivered rank -- credit
        // delivered_count too, since a read message was necessarily delivered.
        if ($newStatus === 'read' && $previousRank < $rank['delivered']) {
            $recipient->campaign?->increment('delivered_count');
        }

        if ($newStatus === 'delivered') {
            $recipient->campaign?->increment('delivered_count');
        } elseif ($newStatus === 'read') {
            $recipient->campaign?->increment('read_count');
        }
    }

    private function applyPermissionRequestStatusUpdate(string $externalId, string $newStatus, array $status): void
    {
        $whatsappCall = WhatsappCall::withoutGlobalScope(CompanyScope::class)
            ->where('permission_request_message_id', $externalId)
            ->first();

        if (! $whatsappCall) {
            if ($newStatus === 'failed') {
                Log::warning('Received a failed status webhook for an unmatched message', [
                    'external_id' => $externalId,
                    'status' => $status,
                ]);
            }

            return;
        }

        $update = ['permission_request_status' => $newStatus];

        if ($newStatus === 'failed') {
            $errors = $status['errors'] ?? [];
            $reason = $errors[0]['message'] ?? $errors[0]['title'] ?? null;
            $details = $errors[0]['error_data']['details'] ?? null;
            $update['permission_request_failure_reason'] = trim(($reason ?? 'Message delivery failed.').($details ? " {$details}" : ''));
        }

        Log::info('Matched permission-request status webhook to a WhatsappCall', [
            'whatsapp_call_id' => $whatsappCall->id,
            'external_id' => $externalId,
            'new_status' => $newStatus,
            'update' => $update,
        ]);

        $whatsappCall->update($update);

        WhatsappCallStatusUpdated::dispatch($whatsappCall->fresh());
    }

    public function failed(Throwable $e): void
    {
        $companyId = ApiConnection::query()->where('channel', 'whatsapp')->value('company_id');

        $this->recordFailure($e, $companyId, $this->webhookEventId);
    }
}
