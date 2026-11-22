<?php

namespace App\Services\ChatFlow;

use App\Events\ConversationUpdated;
use App\Events\MessageReceived;
use App\Models\ApiConnection;
use App\Models\ChatMenuFlow;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Messaging\MessagingDriverResolver;
use Throwable;

class ChatMenuFlowEngine
{
    public function __construct(private MessagingDriverResolver $resolver) {}

    /**
     * Attempt to handle an inbound message as part of a button-menu flow.
     * Returns true if it sent a reply (button click, or a trigger-keyword
     * match started a new flow) — callers should skip AI/automation replies
     * when this returns true. Returns false when the message should fall
     * through to the normal chatbot/automation pipeline (including the
     * "mid-flow free text" AI-fallback case, which still leaves the
     * conversation's flow position untouched).
     */
    public function handle(Conversation $conversation, Message $inboundMessage): bool
    {
        $channel = $conversation->channel === 'whatsapp' ? 'whatsapp' : 'web';

        // A trigger keyword always (re)starts its flow, even when the
        // conversation is already sitting inside a flow. Without this check an
        // active flow swallowed every later "hi"-style keyword, so a customer
        // who abandoned the menu (or whose menu message failed to send) could
        // never get it back -- the message fell through to AI/automation and
        // the menu looked broken.
        $triggerFlow = $this->matchTriggerFlow($conversation, $inboundMessage, $channel);

        if ($triggerFlow) {
            $entryNode = $this->findNode($triggerFlow, $triggerFlow->entry_node_id);

            if ($entryNode) {
                $this->enterNode($conversation, $triggerFlow, $entryNode);

                return true;
            }
        }

        if ($conversation->current_chat_flow_id) {
            return $this->handleWithinActiveFlow($conversation, $inboundMessage);
        }

        return false;
    }

    private function handleWithinActiveFlow(Conversation $conversation, Message $inboundMessage): bool
    {
        $flow = ChatMenuFlow::withoutGlobalScopes()->find($conversation->current_chat_flow_id);

        if (! $flow) {
            $conversation->update(['current_chat_flow_id' => null, 'current_chat_flow_node_id' => null]);

            return false;
        }

        $currentNode = $this->findNode($flow, $conversation->current_chat_flow_node_id);

        if (! $currentNode) {
            $conversation->update(['current_chat_flow_id' => null, 'current_chat_flow_node_id' => null]);

            return false;
        }

        $button = $this->matchButton($currentNode, $inboundMessage);

        if (! $button) {
            // Free text typed mid-flow: fall back to AI, but keep the
            // conversation's flow position so a later button click still
            // resumes correctly.
            return false;
        }

        $nextNode = $this->findNode($flow, $button['nextNodeId'] ?? null);

        if (! $nextNode) {
            $conversation->update(['current_chat_flow_id' => null, 'current_chat_flow_node_id' => null]);

            return false;
        }

        $this->enterNode($conversation, $flow, $nextNode);

        return true;
    }

    private function matchTriggerFlow(Conversation $conversation, Message $inboundMessage, string $channel): ?ChatMenuFlow
    {
        $text = trim($inboundMessage->text ?? '');

        if ($text === '') {
            return null;
        }

        $flows = ChatMenuFlow::query()
            ->where('company_id', $conversation->company_id)
            ->where('status', 'active')
            ->whereIn('channel', [$channel, 'both'])
            ->whereNotNull('trigger_keyword')
            ->get();

        return $flows->first(
            fn (ChatMenuFlow $flow) => strcasecmp(trim($flow->trigger_keyword), $text) === 0
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findNode(ChatMenuFlow $flow, ?string $nodeId): ?array
    {
        if (! $nodeId) {
            return null;
        }

        return collect($flow->nodes)->firstWhere('id', $nodeId);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function matchButton(array $node, Message $inboundMessage): ?array
    {
        $buttons = collect($node['buttons'] ?? []);

        if ($inboundMessage->interactive_reply_id) {
            $match = $buttons->firstWhere('id', $inboundMessage->interactive_reply_id);

            if ($match) {
                return $match;
            }
        }

        // Web widget clicks (and any client that can't send Meta's native
        // interactive payload) submit the button id as plain text instead.
        return $buttons->firstWhere('id', trim($inboundMessage->text ?? ''));
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function enterNode(Conversation $conversation, ChatMenuFlow $flow, array $node): void
    {
        $isLeaf = empty($node['buttons']);

        $interactiveStyle = null;
        $listButtonLabel = null;

        if (! $isLeaf) {
            $interactiveStyle = count($node['buttons']) > 3
                ? 'list'
                : ($node['renderAs'] ?? $node['n'] ?? 'button');

            if ($interactiveStyle === 'list') {
                $listButtonLabel = isset($node['listButtonLabel']) && trim($node['listButtonLabel']) !== ''
                    ? trim($node['listButtonLabel'])
                    : 'Choose an option';
            }
        }

        $conversation->update([
            'current_chat_flow_id' => $isLeaf ? null : $flow->id,
            'current_chat_flow_node_id' => $isLeaf ? null : $node['id'],
        ]);

        $outboundMessage = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'text' => $node['message'] ?? '',
            'status' => 'sent',
            'sent_at' => now(),
            'attachment_url' => $node['mediaUrl'] ?? null,
            'attachment_type' => $node['mediaType'] ?? null,
            'buttons' => empty($node['buttons']) ? null : $node['buttons'],
            'interactive_style' => $interactiveStyle,
            'list_button_label' => $listButtonLabel,
        ]);

        $conversation->update(['last_message_at' => $outboundMessage->sent_at]);

        if ($conversation->channel === 'whatsapp') {
            $connection = $conversation->api_connection_id
                ? ApiConnection::find($conversation->api_connection_id)
                : ApiConnection::query()
                    ->where('company_id', $conversation->company_id)
                    ->where('channel', 'whatsapp')
                    ->first();

            try {
                $externalId = $this->resolver->forConnection($connection)->send($outboundMessage, $connection ?? new ApiConnection);
                $outboundMessage->update(['external_message_id' => $externalId]);
            } catch (Throwable $exception) {
                // Clear the flow position and flag the message so a queue
                // retry can actually re-send the node instead of treating the
                // conversation as "already inside the flow".
                $outboundMessage->update(['status' => 'failed']);
                $conversation->update([
                    'current_chat_flow_id' => null,
                    'current_chat_flow_node_id' => null,
                ]);

                throw $exception;
            }
        }

        MessageReceived::dispatch($outboundMessage->load('conversation'));
        ConversationUpdated::dispatch($conversation);
    }
}
