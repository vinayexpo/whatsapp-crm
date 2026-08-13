<?php

namespace App\Jobs;

use App\Events\ConversationUpdated;
use App\Models\ApiConnection;
use App\Models\CampaignRecipient;
use App\Models\Conversation;
use App\Models\Message;
use App\Jobs\Concerns\NotifiesOnFailure;
use App\Services\Messaging\MessagingDriverResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendCampaignMessage implements ShouldQueue
{
    use Queueable, NotifiesOnFailure;

    public function __construct(public int $campaignRecipientId) {}

    public function handle(MessagingDriverResolver $resolver): void
    {
        $recipient = CampaignRecipient::query()->with(['campaign', 'contact'])->find($this->campaignRecipientId);

        if (! $recipient || $recipient->status !== 'pending') {
            return;
        }

        $contact = $recipient->contact;
        $campaign = $recipient->campaign;

        if (! $contact || ! $campaign) {
            return;
        }

        $conversation = Conversation::query()->firstOrCreate(
            ['contact_id' => $contact->id],
            [
                'company_id' => $campaign->company_id,
                'channel' => $campaign->channel === 'both' ? $contact->channel : $campaign->channel,
                'status' => 'open',
            ]
        );

        $lastInboundAt = $conversation->messages()->where('direction', 'inbound')->max('sent_at');
        $outsideWindow = ! $lastInboundAt || now()->diffInHours($lastInboundAt, absolute: true) >= 24;
        $template = $campaign->whatsappTemplate;

        if ($outsideWindow && ! $template) {
            $recipient->update([
                'status' => 'failed',
                'failure_reason' => 'Outside the 24-hour customer messaging window; requires a pre-approved template.',
            ]);

            return;
        }

        $text = $template ? $this->renderTemplate($template->body, $campaign->template_variables ?? []) : $campaign->message;

        $message = DB::transaction(function () use ($recipient, $conversation, $text, $campaign) {
            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'text' => $text,
                'status' => 'sent',
                'sent_at' => now(),
                'attachment_url' => $campaign->attachment_url,
                'attachment_type' => $campaign->attachment_type,
            ]);

            $conversation->update(['last_message_at' => $message->sent_at]);

            $recipient->update([
                'message_id' => $message->id,
                'status' => 'sent',
                'sent_at' => $message->sent_at,
            ]);

            return $message;
        });

        $connection = ApiConnection::query()->where('channel', $conversation->channel)->first();
        $externalId = $resolver->forConnection($connection)->send($message, $connection ?? new ApiConnection);
        $message->update(['external_message_id' => $externalId]);

        ConversationUpdated::dispatch($conversation);
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function renderTemplate(string $body, array $variables): string
    {
        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', function ($matches) use ($variables) {
            return $variables[$matches[1]] ?? $matches[0];
        }, $body);
    }

    public function failed(Throwable $e): void
    {
        $companyId = CampaignRecipient::query()->find($this->campaignRecipientId)?->campaign?->company_id;

        $this->recordFailure($e, $companyId);
    }
}
