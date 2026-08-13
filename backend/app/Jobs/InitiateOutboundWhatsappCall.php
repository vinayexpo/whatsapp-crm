<?php

namespace App\Jobs;

use App\Events\ConversationUpdated;
use App\Models\ApiConnection;
use App\Models\CampaignRecipient;
use App\Models\Conversation;
use App\Models\WhatsappCall;
use App\Jobs\Concerns\NotifiesOnFailure;
use App\Services\Calling\FakeWhatsappCallService;
use App\Services\Calling\WhatsappCallDriverResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class InitiateOutboundWhatsappCall implements ShouldQueue
{
    use Queueable, NotifiesOnFailure;

    public function __construct(public int $campaignRecipientId, public int $whatsappCallFlowId) {}

    public function handle(WhatsappCallDriverResolver $resolver): void
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

        [$conversation, $call] = DB::transaction(function () use ($contact, $campaign, $recipient) {
            $conversation = Conversation::withoutGlobalScopes()
                ->where('contact_id', $contact->id)->where('channel', 'whatsapp_call')->first();

            if (! $conversation) {
                $conversation = new Conversation([
                    'contact_id' => $contact->id,
                    'channel' => 'whatsapp_call',
                    'status' => 'open',
                    'unread_count' => 0,
                ]);
                $conversation->company_id = $campaign->company_id;
                $conversation->save();
            }

            $call = new WhatsappCall([
                'whatsapp_call_flow_id' => $this->whatsappCallFlowId,
                'contact_id' => $contact->id,
                'campaign_recipient_id' => $recipient->id,
                'conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'status' => 'ringing',
            ]);
            $call->company_id = $campaign->company_id;
            $call->save();

            return [$conversation, $call];
        });

        $connection = ApiConnection::query()
            ->where('channel', 'whatsapp')
            ->where('company_id', $campaign->company_id)
            ->where('calling_enabled', true)
            ->first();

        $service = $resolver->forConnection($connection);

        if ($service instanceof FakeWhatsappCallService && ! $connection) {
            $call->update(['status' => 'failed']);

            $recipient->update([
                'status' => 'failed',
                'failure_reason' => 'No WhatsApp connection has calling enabled for this company.',
            ]);

            Log::info('InitiateOutboundWhatsappCall: no calling-enabled WhatsApp connection, skipping real call', [
                'whatsapp_call_id' => $call->id,
            ]);

            ConversationUpdated::dispatch($conversation);

            return;
        }

        $metaCallId = $service->placeCall($call, $connection ?? new ApiConnection);

        $call->update(['meta_call_id' => $metaCallId]);
        $recipient->update(['status' => 'sent', 'sent_at' => now()]);

        ConversationUpdated::dispatch($conversation);
    }

    public function failed(Throwable $e): void
    {
        $companyId = CampaignRecipient::query()->find($this->campaignRecipientId)?->campaign?->company_id;

        $this->recordFailure($e, $companyId);
    }
}
