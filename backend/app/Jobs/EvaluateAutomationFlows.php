<?php

namespace App\Jobs;

use App\Events\ConversationUpdated;
use App\Events\MessageReceived;
use App\Models\ApiConnection;
use App\Models\AutomationFlow;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\InstagramComment;
use App\Models\InstagramStoryMention;
use App\Models\Message;
use App\Models\Tag;
use App\Models\User;
use App\Models\WhatsappFlow;
use App\Jobs\Concerns\NotifiesOnFailure;
use App\Services\Messaging\MessagingDriverResolver;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Throwable;

class EvaluateAutomationFlows implements ShouldQueue
{
    use Queueable, NotifiesOnFailure;

    public function __construct(
        public ?int $conversationId,
        public ?int $messageId,
        public bool $isNewContact,
        public ?int $commentId = null,
        public ?int $storyMentionId = null,
        public bool $isNoReplyCheck = false,
    ) {}

    public function handle(): void
    {
        $flows = AutomationFlow::query()->where('status', 'active')->get();

        if ($this->commentId) {
            $comment = InstagramComment::query()->find($this->commentId);

            if (! $comment) {
                return;
            }

            foreach ($flows as $flow) {
                if ($this->matchesCommentTrigger($flow, $comment)) {
                    $this->runCommentActions($flow, $comment, app(MessagingDriverResolver::class));
                }
            }

            return;
        }

        if ($this->storyMentionId) {
            $mention = InstagramStoryMention::query()->find($this->storyMentionId);

            if (! $mention) {
                return;
            }

            foreach ($flows as $flow) {
                if ($this->matchesStoryMentionTrigger($flow, $mention)) {
                    $this->runStoryMentionActions($flow, $mention, app(MessagingDriverResolver::class));
                }
            }

            return;
        }

        $conversation = Conversation::query()->find($this->conversationId);

        if ($this->isNoReplyCheck) {
            if (! $conversation) {
                return;
            }

            $contact = Contact::query()->find($conversation->contact_id);

            foreach ($flows as $flow) {
                if ($this->matchesNoReplyTrigger($flow, $conversation)) {
                    $this->runActions($flow, $conversation, $contact);
                }
            }

            return;
        }

        $message = Message::query()->find($this->messageId);

        if (! $conversation || ! $message) {
            return;
        }

        $contact = Contact::query()->find($conversation->contact_id);

        foreach ($flows as $flow) {
            if ($this->matchesTrigger($flow, $conversation, $message)) {
                $this->runActions($flow, $conversation, $contact);
            }
        }
    }

    private function matchesTrigger(AutomationFlow $flow, Conversation $conversation, Message $message): bool
    {
        $trigger = $flow->trigger ?? [];
        $type = $trigger['type'] ?? null;
        $channel = $trigger['channel'] ?? 'both';

        if ($channel !== 'both' && $channel !== $conversation->channel) {
            return false;
        }

        return match ($type) {
            'new-message' => true,
            'new-contact' => $this->isNewContact,
            'keyword' => ! empty($trigger['keyword'])
                && str_contains(strtolower($message->text ?? ''), strtolower($trigger['keyword'])),
            default => false,
        };
    }

    private function matchesCommentTrigger(AutomationFlow $flow, InstagramComment $comment): bool
    {
        $trigger = $flow->trigger ?? [];
        $type = $trigger['type'] ?? null;
        $channel = $trigger['channel'] ?? 'both';

        if ($channel !== 'both' && $channel !== 'instagram') {
            return false;
        }

        return match ($type) {
            'comment-received' => empty($trigger['keyword'])
                || str_contains(strtolower($comment->text ?? ''), strtolower($trigger['keyword'])),
            default => false,
        };
    }

    private function matchesNoReplyTrigger(AutomationFlow $flow, Conversation $conversation): bool
    {
        $trigger = $flow->trigger ?? [];
        $type = $trigger['type'] ?? null;
        $channel = $trigger['channel'] ?? 'both';

        if ($channel !== 'both' && $channel !== $conversation->channel) {
            return false;
        }

        return $type === 'no-reply';
    }

    private function matchesStoryMentionTrigger(AutomationFlow $flow, InstagramStoryMention $mention): bool
    {
        $trigger = $flow->trigger ?? [];
        $type = $trigger['type'] ?? null;
        $channel = $trigger['channel'] ?? 'both';

        if ($channel !== 'both' && $channel !== 'instagram') {
            return false;
        }

        return $type === 'story-mention';
    }

    private function runActions(AutomationFlow $flow, Conversation $conversation, ?Contact $contact): void
    {
        foreach ($flow->actions ?? [] as $action) {
            $type = $action['type'] ?? null;
            $value = $action['value'] ?? null;

            match ($type) {
                'send-message' => $this->sendMessage($conversation, (string) $value),
                'add-tag' => $this->addTag($contact, (string) $value),
                'move-pipeline-stage' => $contact?->update(['pipeline_stage_id' => $value]),
                'send-whatsapp-flow' => $this->sendWhatsappFlow($conversation, (string) $value),
                default => null,
            };
        }

        $flow->update([
            'triggered_count' => $flow->triggered_count + 1,
            'last_triggered_at' => now(),
        ]);

        $this->notifyFlowTriggered($flow);
    }

    private function runCommentActions(AutomationFlow $flow, InstagramComment $comment, MessagingDriverResolver $resolver): void
    {
        foreach ($flow->actions ?? [] as $action) {
            $type = $action['type'] ?? null;
            $value = $action['value'] ?? null;

            match ($type) {
                'send-instagram-dm' => $this->sendInstagramDm($comment, (string) $value, $resolver),
                default => null,
            };
        }

        $flow->update([
            'triggered_count' => $flow->triggered_count + 1,
            'last_triggered_at' => now(),
        ]);

        $this->notifyFlowTriggered($flow);
    }

    private function sendInstagramDm(InstagramComment $comment, string $text, MessagingDriverResolver $resolver): void
    {
        if ($text === '') {
            return;
        }

        $contact = Contact::query()->find($comment->contact_id);

        if (! $contact) {
            return;
        }

        $conversation = Conversation::query()->firstOrCreate(
            ['contact_id' => $contact->id, 'channel' => 'instagram'],
            ['company_id' => $comment->company_id, 'status' => 'open', 'unread_count' => 0],
        );

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'text' => $text,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $conversation->update(['last_message_at' => $message->sent_at]);

        $connection = ApiConnection::query()->where('channel', 'instagram')->first();
        $externalId = $resolver->forConnection($connection)->send($message, $connection ?? new ApiConnection);
        $message->update(['external_message_id' => $externalId]);

        $comment->update(['replied_at' => now()]);

        MessageReceived::dispatch($message->load('conversation'));
        ConversationUpdated::dispatch($conversation);
    }

    private function runStoryMentionActions(AutomationFlow $flow, InstagramStoryMention $mention, MessagingDriverResolver $resolver): void
    {
        foreach ($flow->actions ?? [] as $action) {
            $type = $action['type'] ?? null;
            $value = $action['value'] ?? null;

            match ($type) {
                'send-instagram-dm' => $this->sendInstagramDmForMention($mention, (string) $value, $resolver),
                default => null,
            };
        }

        $flow->update([
            'triggered_count' => $flow->triggered_count + 1,
            'last_triggered_at' => now(),
        ]);

        $this->notifyFlowTriggered($flow);
    }

    private function sendInstagramDmForMention(InstagramStoryMention $mention, string $text, MessagingDriverResolver $resolver): void
    {
        if ($text === '') {
            return;
        }

        $contact = Contact::query()->find($mention->contact_id);

        if (! $contact) {
            return;
        }

        $conversation = Conversation::query()->firstOrCreate(
            ['contact_id' => $contact->id, 'channel' => 'instagram'],
            ['company_id' => $mention->company_id, 'status' => 'open', 'unread_count' => 0],
        );

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'text' => $text,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $conversation->update(['last_message_at' => $message->sent_at]);

        $connection = ApiConnection::query()->where('channel', 'instagram')->first();
        $externalId = $resolver->forConnection($connection)->send($message, $connection ?? new ApiConnection);
        $message->update(['external_message_id' => $externalId]);

        $mention->update(['replied_at' => now()]);

        MessageReceived::dispatch($message->load('conversation'));
        ConversationUpdated::dispatch($conversation);
    }

    private function sendMessage(Conversation $conversation, string $text): void
    {
        if ($text === '') {
            return;
        }

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'text' => $text,
            'status' => 'sent',
            'external_message_id' => (string) Str::uuid(),
            'sent_at' => now(),
        ]);

        $conversation->update(['last_message_at' => $message->sent_at]);

        MessageReceived::dispatch($message->load('conversation'));
        ConversationUpdated::dispatch($conversation);
    }

    private function sendWhatsappFlow(Conversation $conversation, string $flowUuid): void
    {
        if ($flowUuid === '') {
            return;
        }

        $flow = WhatsappFlow::query()->where('uuid', $flowUuid)->first();

        if (! $flow) {
            return;
        }

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'text' => "Sent WhatsApp Flow: {$flow->name}",
            'status' => 'sent',
            'external_message_id' => (string) Str::uuid(),
            'sent_at' => now(),
        ]);

        $conversation->update(['last_message_at' => $message->sent_at]);

        MessageReceived::dispatch($message->load('conversation'));
        ConversationUpdated::dispatch($conversation);
    }

    private function notifyFlowTriggered(AutomationFlow $flow): void
    {
        if (! Permission::where('name', 'automations.manage')->exists()) {
            return;
        }

        $dispatchService = app(NotificationDispatchService::class);

        User::query()
            ->where('company_id', $flow->company_id)
            ->permission('automations.manage')
            ->each(fn (User $user) => $dispatchService->notify(
                $user,
                'automation_triggered',
                'Automation triggered',
                "\"{$flow->name}\" ran automatically.",
                ['automationFlowId' => $flow->uuid],
            ));
    }

    private function addTag(?Contact $contact, string $tagName): void
    {
        if (! $contact || $tagName === '') {
            return;
        }

        $tag = Tag::query()->firstOrCreate(
            ['name' => $tagName, 'company_id' => $contact->company_id],
            ['company_id' => $contact->company_id],
        );
        $contact->tags()->syncWithoutDetaching([$tag->id]);
    }

    public function failed(Throwable $e): void
    {
        $companyId = match (true) {
            $this->commentId !== null => InstagramComment::withoutGlobalScopes()->find($this->commentId)?->company_id,
            $this->storyMentionId !== null => InstagramStoryMention::withoutGlobalScopes()->find($this->storyMentionId)?->company_id,
            default => Conversation::withoutGlobalScopes()->find($this->conversationId)?->company_id,
        };

        $this->recordFailure($e, $companyId);
    }
}
