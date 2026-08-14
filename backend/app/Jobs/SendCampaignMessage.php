<?php

namespace App\Jobs;

use App\Events\ConversationUpdated;
use App\Models\ApiConnection;
use App\Models\CampaignRecipient;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappTemplate;
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

        if ($outsideWindow && (! $template || $template->status !== 'approved')) {
            $recipient->update([
                'status' => 'failed',
                'failure_reason' => $template
                    ? "Outside the 24-hour customer messaging window; the attached template is \"{$template->status}\", not approved by Meta."
                    : 'Outside the 24-hour customer messaging window; requires a pre-approved template.',
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

            // Leave the recipient in 'pending' until the provider call below
            // actually succeeds. Marking it 'sent' here meant a failed Graph
            // API call still looked "handled" to the pending-status guard
            // above, so a queue retry would just no-op instead of resending.
            $recipient->update(['message_id' => $message->id]);

            return $message;
        });

        $connection = ApiConnection::query()->where('channel', $conversation->channel)->first();

        $templatePayload = $template ? $this->buildTemplatePayload($template, $campaign->template_variables ?? []) : null;

        try {
            $externalId = $resolver->forConnection($connection)->send($message, $connection ?? new ApiConnection, $templatePayload);
        } catch (Throwable $e) {
            $message->update(['status' => 'failed']);
            $recipient->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);

            throw $e;
        }

        $message->update(['external_message_id' => $externalId]);
        $recipient->update(['status' => 'sent', 'sent_at' => $message->sent_at]);

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

    /**
     * @param  array<string, string>  $variables
     * @return array{name: string, language: string, components: array}
     */
    private function buildTemplatePayload(WhatsappTemplate $template, array $variables): array
    {
        $bodyComponent = collect($template->components ?? [])->firstWhere('type', 'BODY');
        $placeholders = $bodyComponent['text'] ?? $template->body;

        preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $placeholders, $matches);
        $orderedKeys = $matches[1] ?? [];

        $components = [];

        if ($orderedKeys) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(
                    fn ($key) => ['type' => 'text', 'text' => $variables[$key] ?? ''],
                    $orderedKeys
                ),
            ];
        }

        return [
            'name' => $template->name,
            'language' => $template->language,
            'components' => $components,
        ];
    }

    public function failed(Throwable $e): void
    {
        $companyId = CampaignRecipient::query()->find($this->campaignRecipientId)?->campaign?->company_id;

        $this->recordFailure($e, $companyId);
    }
}
