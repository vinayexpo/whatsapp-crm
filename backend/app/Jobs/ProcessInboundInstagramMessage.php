<?php

namespace App\Jobs;

use App\Events\ConversationUpdated;
use App\Events\MessageReceived;
use App\Events\MessageStatusUpdated;
use App\Models\ApiConnection;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\InstagramComment;
use App\Models\InstagramStoryMention;
use App\Models\Message;
use App\Models\WebhookEvent;
use App\Jobs\Concerns\NotifiesOnFailure;
use App\Scopes\CompanyScope;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessInboundInstagramMessage implements ShouldQueue
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
            foreach (data_get($entry, 'messaging', []) as $messagingEvent) {
                if (data_get($messagingEvent, 'message.attachments.0.type') === 'story_mention') {
                    $this->storeInboundStoryMention($messagingEvent);
                } elseif (data_get($messagingEvent, 'message.text')) {
                    $this->storeInboundMessage($messagingEvent);
                }

                if (data_get($messagingEvent, 'delivery') || data_get($messagingEvent, 'read')) {
                    $this->applyStatusUpdate($messagingEvent);
                }
            }

            foreach (data_get($entry, 'changes', []) as $change) {
                if (data_get($change, 'field') === 'comments') {
                    $this->storeInboundComment($change);
                }
            }
        }

        $event->update(['processed_at' => now(), 'status' => 'processed']);
    }

    private function storeInboundStoryMention(array $messagingEvent): void
    {
        $mentionId = data_get($messagingEvent, 'message.mid');
        $senderId = data_get($messagingEvent, 'sender.id');

        if (! $mentionId || ! $senderId) {
            return;
        }

        if (InstagramStoryMention::query()->where('mention_id', $mentionId)->exists()) {
            return;
        }

        $handle = '@ig_'.$senderId;
        $mediaId = data_get($messagingEvent, 'message.attachments.0.payload.url');

        $companyId = ApiConnection::query()->where('channel', 'instagram')->value('company_id');

        $contact = Contact::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('handle', $handle)
            ->where('channel', 'instagram')
            ->first();

        if (! $contact) {
            $contact = new Contact([
                'name' => $handle,
                'channel' => 'instagram',
                'handle' => $handle,
                'pipeline_stage_id' => 'new-lead',
                'last_interaction_at' => now(),
                'notes' => [],
                'purchases' => [],
            ]);
            $contact->company_id = $companyId;
            $contact->save();
        }

        $mention = new InstagramStoryMention([
            'mention_id' => $mentionId,
            'contact_id' => $contact->id,
            'media_id' => $mediaId,
        ]);
        $mention->company_id = $companyId;
        $mention->save();

        EvaluateAutomationFlows::dispatch(null, null, false, null, $mention->id);
    }

    private function storeInboundComment(array $change): void
    {
        $commentId = data_get($change, 'value.id');
        $senderId = data_get($change, 'value.from.id');

        if (! $commentId || ! $senderId) {
            return;
        }

        if (InstagramComment::query()->where('comment_id', $commentId)->exists()) {
            return;
        }

        $handle = '@ig_'.$senderId;
        $text = data_get($change, 'value.text', '');
        $mediaId = data_get($change, 'value.media.id');

        $companyId = ApiConnection::query()->where('channel', 'instagram')->value('company_id');

        $contact = Contact::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('handle', $handle)
            ->where('channel', 'instagram')
            ->first();

        if (! $contact) {
            $contact = new Contact([
                'name' => $handle,
                'channel' => 'instagram',
                'handle' => $handle,
                'pipeline_stage_id' => 'new-lead',
                'last_interaction_at' => now(),
                'notes' => [],
                'purchases' => [],
            ]);
            $contact->company_id = $companyId;
            $contact->save();
        }

        $comment = new InstagramComment([
            'comment_id' => $commentId,
            'contact_id' => $contact->id,
            'media_id' => $mediaId,
            'text' => $text,
        ]);
        $comment->company_id = $companyId;
        $comment->save();

        EvaluateAutomationFlows::dispatch(null, null, false, $comment->id);
    }

    private function storeInboundMessage(array $messagingEvent): void
    {
        $senderId = data_get($messagingEvent, 'sender.id');
        if (! $senderId) {
            return;
        }

        $handle = '@ig_'.$senderId;

        $companyId = ApiConnection::query()->where('channel', 'instagram')->value('company_id');

        [$conversation, $message, $isNewContact] = DB::transaction(function () use ($senderId, $handle, $messagingEvent, $companyId) {
            $contact = Contact::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $companyId)
                ->where('handle', $handle)
                ->where('channel', 'instagram')
                ->first();

            if (! $contact) {
                $contact = new Contact([
                    'name' => $handle,
                    'channel' => 'instagram',
                    'handle' => $handle,
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
                ->where('channel', 'instagram')
                ->first();

            if (! $conversation) {
                $conversation = new Conversation([
                    'contact_id' => $contact->id,
                    'channel' => 'instagram',
                    'status' => 'open',
                    'unread_count' => 0,
                ]);
                $conversation->company_id = $companyId;
                $conversation->save();
            }

            $text = data_get($messagingEvent, 'message.text', '');
            $sentAt = isset($messagingEvent['timestamp'])
                ? now()->createFromTimestampMs((int) $messagingEvent['timestamp'])
                : now();

            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'text' => $text,
                'status' => 'delivered',
                'external_message_id' => data_get($messagingEvent, 'message.mid'),
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
                'New Instagram message',
                "New message from {$handle}",
                ['conversationId' => $conversation->uuid],
            );
        }

        EvaluateAutomationFlows::dispatch($conversation->id, $message->id, $isNewContact);
        GenerateChatbotInstagramReply::dispatch($conversation->id, $message->id);
    }

    private function applyStatusUpdate(array $messagingEvent): void
    {
        $externalId = data_get($messagingEvent, 'delivery.mids.0') ?? data_get($messagingEvent, 'read.mid');

        if (! $externalId) {
            return;
        }

        $newStatus = data_get($messagingEvent, 'read') ? 'read' : 'delivered';

        $message = Message::query()->where('external_message_id', $externalId)->first();

        if (! $message) {
            return;
        }

        $message->update(['status' => $newStatus]);
        MessageStatusUpdated::dispatch($message->load('conversation'));
    }

    public function failed(Throwable $e): void
    {
        $companyId = ApiConnection::query()->where('channel', 'instagram')->value('company_id');

        $this->recordFailure($e, $companyId, $this->webhookEventId);
    }
}
