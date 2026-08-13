<?php

namespace App\Jobs;

use App\Events\MessageStatusUpdated;
use App\Models\CampaignRecipient;
use App\Models\Message;
use App\Jobs\Concerns\NotifiesOnFailure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SimulateMessageDeliveryTick implements ShouldQueue
{
    use Queueable, NotifiesOnFailure;

    public function __construct(
        public int $messageId,
        public string $status,
    ) {}

    public function handle(): void
    {
        $message = Message::query()->find($this->messageId);

        if (! $message || $message->status === 'failed') {
            return;
        }

        $message->update(['status' => $this->status]);
        MessageStatusUpdated::dispatch($message->load('conversation'));

        $this->syncCampaignRecipient($message);
    }

    private function syncCampaignRecipient(Message $message): void
    {
        $recipient = CampaignRecipient::query()->where('message_id', $message->id)->first();

        if (! $recipient) {
            return;
        }

        if ($this->status === 'delivered') {
            $recipient->update(['status' => 'delivered', 'delivered_at' => now()]);
            $recipient->campaign?->increment('delivered_count');
        } elseif ($this->status === 'read') {
            $recipient->update(['status' => 'read', 'read_at' => now()]);
            $recipient->campaign?->increment('read_count');
        }
    }

    public function failed(Throwable $e): void
    {
        $companyId = Message::withoutGlobalScopes()->find($this->messageId)?->conversation?->company_id;

        $this->recordFailure($e, $companyId);
    }
}
