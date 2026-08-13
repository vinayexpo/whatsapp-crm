<?php

namespace App\Jobs;

use App\Events\ConversationUpdated;
use App\Models\ApiConnection;
use App\Models\CampaignRecipient;
use App\Models\Conversation;
use App\Models\VoiceCall;
use App\Jobs\Concerns\NotifiesOnFailure;
use App\Services\Voice\FakeVoiceCallService;
use App\Services\Voice\VoiceCallDriverResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class InitiateOutboundVoiceCall implements ShouldQueue
{
    use Queueable, NotifiesOnFailure;

    public function __construct(public int $campaignRecipientId, public int $voiceAgentId) {}

    public function handle(VoiceCallDriverResolver $resolver): void
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

        [$conversation, $voiceCall] = DB::transaction(function () use ($contact, $campaign, $recipient) {
            $conversation = Conversation::withoutGlobalScopes()
                ->where('contact_id', $contact->id)->where('channel', 'voice')->first();

            if (! $conversation) {
                $conversation = new Conversation([
                    'contact_id' => $contact->id,
                    'channel' => 'voice',
                    'status' => 'open',
                    'unread_count' => 0,
                ]);
                $conversation->company_id = $campaign->company_id;
                $conversation->save();
            }

            $voiceCall = new VoiceCall([
                'voice_agent_id' => $this->voiceAgentId,
                'contact_id' => $contact->id,
                'campaign_recipient_id' => $recipient->id,
                'conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'medium' => 'telephony',
                'status' => 'queued',
            ]);
            $voiceCall->company_id = $campaign->company_id;
            $voiceCall->save();

            return [$conversation, $voiceCall];
        });

        $connection = ApiConnection::query()->where('channel', 'voice')->where('company_id', $campaign->company_id)->first();
        $service = $resolver->forConnection($connection);

        if ($service instanceof FakeVoiceCallService && ! $connection) {
            $voiceCall->update([
                'status' => 'failed',
                'needs_human_followup' => true,
                'qualification_summary' => 'No Twilio connection configured — outbound telephony unavailable, browser-only mode.',
            ]);

            $recipient->update(['status' => 'failed', 'failure_reason' => 'No Twilio voice connection configured for this company.']);

            Log::info('InitiateOutboundVoiceCall: no Twilio connection configured, skipping real call', [
                'voice_call_id' => $voiceCall->id,
            ]);

            ConversationUpdated::dispatch($conversation);

            return;
        }

        $callSid = $service->placeCall($voiceCall, $connection ?? new ApiConnection);

        $voiceCall->update(['provider_call_sid' => $callSid, 'status' => 'ringing']);
        $recipient->update(['status' => 'sent', 'sent_at' => now()]);

        ConversationUpdated::dispatch($conversation);
    }

    public function failed(Throwable $e): void
    {
        $companyId = CampaignRecipient::query()->find($this->campaignRecipientId)?->campaign?->company_id;

        $this->recordFailure($e, $companyId);
    }
}
