<?php

namespace App\Services\Commerce;

use App\Events\ConversationUpdated;
use App\Events\MessageReceived;
use App\Models\ApiConnection;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Messaging\MessagingDriverResolver;

/**
 * Sends an outbound WhatsApp message on behalf of the commerce engine,
 * following the exact same persist-then-send pattern as
 * ChatMenuFlowEngine::enterNode() so commerce conversations show up in the
 * inbox and deliver identically to chat-menu-flow replies.
 */
class CommerceMessageSender
{
    public function __construct(private MessagingDriverResolver $resolver) {}

    /**
     * @param  array<int, array{id: string, label: string}>|null  $buttons
     */
    public function send(Conversation $conversation, string $text, ?array $buttons = null): Message
    {
        $outboundMessage = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'text' => $text,
            'status' => 'sent',
            'sent_at' => now(),
            'buttons' => $buttons ?: null,
        ]);

        $conversation->update(['last_message_at' => $outboundMessage->sent_at]);

        if ($conversation->channel === 'whatsapp') {
            $connection = $conversation->api_connection_id
                ? ApiConnection::find($conversation->api_connection_id)
                : ApiConnection::query()->where('channel', 'whatsapp')->first();
            $externalId = $this->resolver->forConnection($connection)->send($outboundMessage, $connection ?? new ApiConnection);
            $outboundMessage->update(['external_message_id' => $externalId]);
        }

        MessageReceived::dispatch($outboundMessage->load('conversation'));
        ConversationUpdated::dispatch($conversation);

        return $outboundMessage;
    }
}
