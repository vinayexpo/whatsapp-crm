<?php

namespace App\Jobs;

use App\Events\ConversationCreated;
use App\Events\ConversationUpdated;
use App\Models\ApiConnection;
use App\Models\Campaign;
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

        // A brand-new conversation (first-ever message to this contact) has no
        // agent subscribed to its per-conversation broadcast channel yet -- the
        // inbox only learns about conversations it already knows about. Without
        // this, the conversation silently doesn't appear until the page is
        // manually reloaded.
        if ($conversation->wasRecentlyCreated) {
            ConversationCreated::dispatch($conversation);
        }

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

        if ($template && $this->templateNeedsMediaHeader($template) && ! $campaign->attachment_url) {
            $recipient->update([
                'status' => 'failed',
                'failure_reason' => "The template \"{$template->name}\" requires an image/video header, but this campaign has no attachment. Add one and resend.",
            ]);

            return;
        }

        // Templates are scoped to a single WABA. Sending a template through a
        // different connection makes Meta answer 132001 ("Template name does
        // not exist in the translation"), so the connection is resolved from
        // the template first and a mismatch is reported up front.
        $connection = $this->resolveConnection($conversation, $campaign, $template);

        if ($template && $connection && $template->api_connection_id && (int) $template->api_connection_id !== (int) $connection->id) {
            $recipient->update([
                'status' => 'failed',
                'failure_reason' => "The template \"{$template->name}\" belongs to a different WhatsApp number than this conversation. Create the campaign for the number the template was approved on, or use a template from this conversation's number.",
            ]);

            return;
        }

        if (! $connection) {
            // No connection row (e.g. unit tests / a company without a number
            // yet). Keep the previous behaviour: let the driver produce the
            // error rather than pre-failing here.
            $connection = new ApiConnection;
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

        $templatePayload = $template ? $this->buildTemplatePayload($template, $campaign->template_variables ?? [], $message->attachment_url) : null;

        try {
            $externalId = $resolver->forConnection($connection)->send($message, $connection, $templatePayload);
        } catch (Throwable $e) {
            $message->update(['status' => 'failed']);
            $recipient->update([
                'status' => 'failed',
                'failure_reason' => $this->describeSendFailure($e, $template),
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
    private function buildTemplatePayload(WhatsappTemplate $template, array $variables, ?string $attachmentUrl = null): array
    {
        $bodyComponent = collect($template->components ?? [])->firstWhere('type', 'BODY');
        $placeholders = $bodyComponent['text'] ?? $template->body;

        preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $placeholders, $matches);
        $orderedKeys = $matches[1] ?? [];

        $components = [];

        $headerComponent = collect($template->components ?? [])->firstWhere('type', 'HEADER');
        $headerFormat = strtolower($headerComponent['format'] ?? '');

        if ($headerComponent && in_array($headerFormat, ['image', 'video', 'document'], true) && $attachmentUrl) {
            // Meta rejects the send with error 132012 ("Parameter format does
            // not match format in the created template") if a template
            // declares a media header but the request omits the matching
            // header component. The template's own "example.header_handle"
            // is a Meta-internal, expiring scontent.whatsapp.net preview
            // asset -- Meta's servers 403 when trying to re-fetch it for an
            // actual send, so it can't be reused here; the campaign must
            // supply its own attachment. This still builds a "link" here --
            // GraphApiMessagingService::send() uploads the bytes and swaps
            // it for a media id before the request reaches Meta, so this
            // doesn't depend on the URL staying reachable.
            $components[] = [
                'type' => 'header',
                'parameters' => [
                    ['type' => $headerFormat, $headerFormat => ['link' => $attachmentUrl]],
                ],
            ];
        }

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

    private function templateNeedsMediaHeader(WhatsappTemplate $template): bool
    {
        $headerComponent = collect($template->components ?? [])->firstWhere('type', 'HEADER');

        return $headerComponent
            && in_array(strtolower($headerComponent['format'] ?? ''), ['image', 'video', 'document'], true);
    }

    private function resolveConnection(Conversation $conversation, Campaign $campaign, ?WhatsappTemplate $template): ?ApiConnection
    {
        if ($conversation->api_connection_id) {
            return ApiConnection::query()->find($conversation->api_connection_id);
        }

        if ($template?->api_connection_id) {
            $connection = ApiConnection::query()->find($template->api_connection_id);

            if ($connection) {
                return $connection;
            }
        }

        return ApiConnection::query()
            ->where('company_id', $campaign->company_id)
            ->where('channel', $conversation->channel)
            ->first()
            ?? ApiConnection::query()->where('channel', $conversation->channel)->first();
    }

    private function describeSendFailure(Throwable $exception, ?WhatsappTemplate $template): string
    {
        $reason = $exception->getMessage();
        $templateName = $template?->name ?? 'this template';

        if (str_contains($reason, '132001')) {
            return "Meta could not find \"{$templateName}\" (language: ".($template?->language ?? 'unknown').") on the sending WhatsApp number yet — 132001. This usually means the template is still under review or was created moments ago; open Templates, run Sync and retry once Meta shows it as approved.";
        }

        return $reason;
    }

    public function failed(Throwable $e): void
    {
        $companyId = CampaignRecipient::query()->find($this->campaignRecipientId)?->campaign?->company_id;

        $this->recordFailure($e, $companyId);
    }
}
