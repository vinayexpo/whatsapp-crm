<?php

namespace App\Jobs;

use App\Events\ConversationUpdated;
use App\Events\MessageReceived;
use App\Models\ApiConnection;
use App\Models\Chatbot;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Jobs\Concerns\NotifiesOnFailure;
use App\Services\Chatbot\ChatbotReplyServiceInterface;
use App\Services\Messaging\MessagingDriverResolver;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Spatie\Permission\Models\Permission;
use Throwable;

class GenerateChatbotWhatsAppReply implements ShouldQueue
{
    use Queueable, NotifiesOnFailure;

    public function __construct(public int $conversationId, public int $messageId) {}

    public function handle(ChatbotReplyServiceInterface $replyService, MessagingDriverResolver $resolver): void
    {
        $conversation = Conversation::query()->find($this->conversationId);
        $inboundMessage = Message::query()->find($this->messageId);

        if (! $conversation || ! $inboundMessage || $conversation->channel !== 'whatsapp') {
            return;
        }

        // Skip if another automation action already replied after this inbound message arrived.
        $alreadyReplied = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('id', '>', $inboundMessage->id)
            ->exists();

        if ($alreadyReplied) {
            return;
        }

        $chatbot = Chatbot::query()
            ->where('status', 'active')
            ->whereJsonContains('channels', 'whatsapp')
            ->first();

        if (! $chatbot) {
            return;
        }

        if (! $conversation->chatbot_id) {
            $conversation->update(['chatbot_id' => $chatbot->id]);
        }

        $replyText = $replyService->reply($chatbot, $conversation, $inboundMessage->text);

        $outboundMessage = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'text' => $replyText,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $conversation->update(['last_message_at' => $outboundMessage->sent_at]);

        $connection = ApiConnection::query()->where('channel', 'whatsapp')->first();
        $externalId = $resolver->forConnection($connection)->send($outboundMessage, $connection ?? new ApiConnection);
        $outboundMessage->update(['external_message_id' => $externalId]);

        MessageReceived::dispatch($outboundMessage->load('conversation'));
        ConversationUpdated::dispatch($conversation);

        if ($replyService->isHandoff($replyText)) {
            $this->notifyHandoff($chatbot, $conversation);
        }
    }

    private function notifyHandoff(Chatbot $chatbot, Conversation $conversation): void
    {
        if (! Permission::where('name', 'chatbots.manage')->exists()) {
            return;
        }

        $dispatchService = app(NotificationDispatchService::class);
        $contactName = $conversation->contact?->name ?? 'a contact';

        User::query()
            ->where('company_id', $chatbot->company_id)
            ->permission('chatbots.manage')
            ->each(fn (User $user) => $dispatchService->notify(
                $user,
                'chatbot_handoff',
                'Chatbot needs human help',
                "\"{$chatbot->name}\" couldn't answer {$contactName} and may need a human follow-up.",
                ['conversationId' => $conversation->uuid],
            ));
    }

    public function failed(Throwable $e): void
    {
        $companyId = Conversation::withoutGlobalScopes()->find($this->conversationId)?->company_id;

        $this->recordFailure($e, $companyId);
    }
}
