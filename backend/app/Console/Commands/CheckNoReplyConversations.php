<?php

namespace App\Console\Commands;

use App\Jobs\EvaluateAutomationFlows;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('automations:check-no-reply')]
#[Description('Finds conversations with no reply in 24 hours and dispatches matching automation flows.')]
class CheckNoReplyConversations extends Command
{
    public function handle(): void
    {
        $staleConversations = Conversation::query()
            ->where('status', 'open')
            ->whereNull('no_reply_notified_at')
            ->whereNotNull('last_message_at')
            ->where('last_message_at', '<=', now()->subDay())
            ->get()
            ->filter(function (Conversation $conversation) {
                $lastMessage = Message::query()
                    ->where('conversation_id', $conversation->id)
                    ->latest('sent_at')
                    ->first();

                return $lastMessage && $lastMessage->direction === 'inbound';
            });

        foreach ($staleConversations as $conversation) {
            $conversation->update(['no_reply_notified_at' => now()]);

            EvaluateAutomationFlows::dispatch($conversation->id, null, false, null, null, true);
        }

        $this->info("Checked no-reply conversations, dispatched {$staleConversations->count()}.");
    }
}
