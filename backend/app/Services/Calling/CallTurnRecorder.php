<?php

namespace App\Services\Calling;

use App\Events\ConversationUpdated;
use App\Events\MessageReceived;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappCall;
use Illuminate\Support\Facades\DB;

class CallTurnRecorder
{
    /**
     * Record one spoken turn of a live WhatsApp call (AI prompt or caller
     * speech) as its own Message row, so the inbox shows the conversation
     * as it happens rather than only a one-line summary at call end.
     */
    public function record(WhatsappCall $whatsappCall, string $speaker, string $text): void
    {
        if (trim($text) === '') {
            return;
        }

        [$conversation, $message] = DB::transaction(function () use ($whatsappCall, $speaker, $text) {
            $conversation = $this->resolveConversation($whatsappCall);
            $whatsappCall->setRelation('conversation', $conversation);

            if (! $whatsappCall->conversation_id) {
                $whatsappCall->update(['conversation_id' => $conversation->id]);
            }

            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'direction' => $speaker === 'ai' ? 'outbound' : 'inbound',
                'text' => $text,
                'status' => 'delivered',
                'sent_at' => now(),
            ]);

            $conversation->update([
                'last_message_at' => $message->sent_at,
                'unread_count' => $speaker === 'ai' ? $conversation->unread_count : $conversation->unread_count + 1,
            ]);

            return [$conversation, $message];
        });

        MessageReceived::dispatch($message->load('conversation'));
        ConversationUpdated::dispatch($conversation);
    }

    public function resolveConversation(WhatsappCall $whatsappCall): Conversation
    {
        $conversation = $whatsappCall->conversation;

        if ($conversation) {
            return $conversation;
        }

        $conversation = Conversation::withoutGlobalScopes()
            ->where('contact_id', $whatsappCall->contact_id)
            ->where('channel', 'whatsapp_call')
            ->first();

        if ($conversation) {
            return $conversation;
        }

        $conversation = new Conversation([
            'contact_id' => $whatsappCall->contact_id,
            'channel' => 'whatsapp_call',
            'status' => 'open',
            'unread_count' => 0,
        ]);
        $conversation->company_id = $whatsappCall->company_id;
        $conversation->save();

        return $conversation;
    }
}
