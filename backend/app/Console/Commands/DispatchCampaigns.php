<?php

namespace App\Console\Commands;

use App\Jobs\InitiateOutboundVoiceCall;
use App\Jobs\InitiateOutboundWhatsappCall;
use App\Jobs\SendCampaignMessage;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\User;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

#[Signature('campaigns:dispatch')]
#[Description('Resolves audiences and queues sends for due campaigns.')]
class DispatchCampaigns extends Command
{
    public function handle(): void
    {
        $dueCampaigns = Campaign::query()
            ->whereIn('status', ['scheduled', 'active'])
            ->where('scheduled_at', '<=', now())
            ->whereNull('dispatched_at')
            ->get();

        foreach ($dueCampaigns as $campaign) {
            $this->dispatchCampaign($campaign);
        }

        $this->info("Dispatched {$dueCampaigns->count()} campaign(s).");
    }

    private function dispatchCampaign(Campaign $campaign): void
    {
        $isCallCampaign = $campaign->voice_agent_id || $campaign->whatsapp_call_flow_id;

        $contacts = $campaign->phonebook_folder_id
            ? $campaign->phonebookFolder->contacts()
                ->when(! $isCallCampaign && $campaign->channel !== 'both', fn ($q) => $q->where('channel', $campaign->channel))
                ->get()
            : Contact::query()
                ->whereHas('tags', fn ($q) => $q->where('name', $campaign->audience_tag))
                ->when(! $isCallCampaign && $campaign->channel !== 'both', fn ($q) => $q->where('channel', $campaign->channel))
                ->get();

        $recipientIds = [];

        foreach ($contacts as $contact) {
            $recipient = CampaignRecipient::query()->firstOrCreate(
                ['campaign_id' => $campaign->id, 'contact_id' => $contact->id],
                [
                    'idempotency_key' => "campaign:{$campaign->id}:contact:{$contact->id}:".Str::uuid(),
                    'status' => 'pending',
                ]
            );

            if ($recipient->status === 'pending') {
                $recipientIds[] = $recipient->id;
            }
        }

        $campaign->update([
            'status' => 'active',
            'recipient_count' => $contacts->count(),
            'dispatched_at' => now(),
        ]);

        foreach ($recipientIds as $recipientId) {
            if ($campaign->voice_agent_id) {
                InitiateOutboundVoiceCall::dispatch($recipientId, $campaign->voice_agent_id);
            } elseif ($campaign->whatsapp_call_flow_id) {
                InitiateOutboundWhatsappCall::dispatch($recipientId, $campaign->whatsapp_call_flow_id);
            } else {
                SendCampaignMessage::dispatch($recipientId);
            }
        }

        $campaign->update(['status' => 'completed']);

        $this->notifyCampaignCompleted($campaign);
    }

    private function notifyCampaignCompleted(Campaign $campaign): void
    {
        if (! Permission::where('name', 'campaigns.manage')->exists()) {
            return;
        }

        $dispatchService = app(NotificationDispatchService::class);
        $recipientCount = $campaign->recipient_count;
        $failedCount = CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'failed')
            ->count();

        $allFailed = $recipientCount > 0 && $failedCount === $recipientCount;

        [$type, $title, $body] = $allFailed
            ? [
                'campaign_failed',
                'Campaign failed',
                "\"{$campaign->name}\" failed to send to all {$recipientCount} recipient(s).",
            ]
            : [
                'campaign_completed',
                'Campaign completed',
                "\"{$campaign->name}\" has finished sending to {$recipientCount} recipient(s).",
            ];

        User::query()
            ->where('company_id', $campaign->company_id)
            ->permission('campaigns.manage')
            ->each(fn (User $user) => $dispatchService->notify(
                $user,
                $type,
                $title,
                $body,
                ['campaignId' => $campaign->uuid],
            ));
    }
}
