<?php

namespace App\Services\Ads;

use App\Models\PaidAdAccount;
use App\Models\PaidAdCampaign;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MetaAdsService
{
    public function __construct(private MetaAdsClient $client) {}

    public function verify(PaidAdAccount $account): array
    {
        $remote = $this->client->request($account, 'GET', 'act_'.$account->meta_account_id, ['fields' => 'id,name,currency,timezone_name,account_status,user_tasks']);
        if (($remote['id'] ?? '') !== 'act_'.$account->meta_account_id || (int) ($remote['account_status'] ?? 0) !== 1) {
            $this->invalid('The ad account is unavailable or inactive. Resolve billing or account restrictions in Meta.');
        }
        if (! in_array($remote['currency'] ?? '', ['INR', 'USD', 'EUR', 'GBP', 'AED', 'CAD', 'AUD', 'SGD'], true)) {
            $this->invalid('This release supports INR, USD, EUR, GBP, AED, CAD, AUD and SGD accounts.');
        }
        if (! array_intersect($remote['user_tasks'] ?? [], ['ADVERTISE', 'MANAGE'])) {
            $this->invalid('This token has no advertiser access to the ad account.');
        }
        $page = $this->client->request($account, 'GET', $account->page_id, ['fields' => 'id,name,whatsapp_number,instagram_business_account']);
        if (($page['id'] ?? '') !== $account->page_id) {
            $this->invalid('The Facebook Page could not be verified.');
        }
        if ($account->instagram_id && ($page['instagram_business_account']['id'] ?? null) !== $account->instagram_id) {
            $this->invalid('The Instagram identity is not linked to the selected Facebook Page.');
        }
        if ($account->whatsapp_number && preg_replace('/\D/', '', $page['whatsapp_number'] ?? '') !== $account->whatsapp_number) {
            $this->invalid('Link this WhatsApp number to the Facebook Page in Meta before verifying.');
        }
        $account->update(['currency' => $remote['currency'], 'timezone' => $remote['timezone_name'] ?? 'UTC', 'verified_at' => now(), 'sync_error' => null]);

        return ['account' => $remote['name'] ?? $account->name, 'page' => $page['name'] ?? $account->page_id];
    }

    public function sync(PaidAdAccount $account): void
    {
        Cache::lock('ads-account:'.$account->id, 600)->block(1, function () use ($account) {
            $campaigns = $this->client->pages($account, 'act_'.$account->meta_account_id.'/campaigns', ['fields' => MetaAdsSync::CAMPAIGN_FIELDS]);
            $adsets = collect($this->client->pages($account, 'act_'.$account->meta_account_id.'/adsets', ['fields' => MetaAdsSync::ADSET_FIELDS]))->groupBy('campaign_id');
            $ads = collect($this->client->pages($account, 'act_'.$account->meta_account_id.'/ads', ['fields' => MetaAdsSync::AD_FIELDS]))->groupBy('campaign_id');
            $insights = collect($this->client->pages($account, 'act_'.$account->meta_account_id.'/insights', ['level' => 'campaign', 'date_preset' => 'last_7d', 'fields' => 'campaign_id,spend,impressions,clicks,reach,ctr,cpc,actions,date_start,date_stop']))->keyBy('campaign_id');
            foreach ($campaigns as $remote) {
                $local = PaidAdCampaign::firstOrCreate(['company_id' => $account->company_id, 'paid_ad_account_id' => $account->id, 'remote_id' => $remote['id']], ['name' => $remote['name'], 'objective' => $remote['objective'], 'state' => 'IMPORTED']);
                Cache::lock('ads-campaign:'.$local->id, 180)->get(function () use ($local, $remote, $insights, $adsets, $ads) {
                    // Commit before releasing the campaign lock so staged edits see this snapshot.
                    DB::transaction(function () use ($local, $remote, $insights, $adsets, $ads) {
                        $local->refresh();
                        if (in_array($local->sync_state, ['QUEUED', 'APPLYING'])) {
                            return;
                        }
                        app(MetaAdsSync::class)->receive($local, ['campaign' => $remote, 'adsets' => $adsets->get($remote['id'], collect())->values()->all(), 'ads' => $ads->get($remote['id'], collect())->values()->all()]);
                        $local->update(['insights' => $insights->get($remote['id']) ?? ['spend' => '0', 'impressions' => '0', 'clicks' => '0', 'reach' => '0', 'date_start' => now()->subDays(7)->toDateString(), 'date_stop' => now()->subDay()->toDateString()]]);
                    });
                });
            }
            PaidAdCampaign::where('company_id', $account->company_id)->where('paid_ad_account_id', $account->id)->whereNotNull('remote_id')->whereNotIn('remote_id', array_column($campaigns, 'id'))->whereNotIn('sync_state', ['QUEUED', 'APPLYING'])->update(['insights' => null, 'sync_state' => 'MISSING', 'sync_error' => 'This campaign was not returned by Meta. It may be archived, deleted or inaccessible. Review it in Ads Manager before editing.']);
            $account->update(['synced_at' => now(), 'sync_error' => null]);
        });
    }

    public function publish(PaidAdCampaign $campaign): void
    {
        Cache::lock('ads-account:'.$campaign->paid_ad_account_id, 600)->block(1, fn () => $this->publishLocked($campaign));
    }

    private function publishLocked(PaidAdCampaign $campaign): void
    {
        Cache::lock('ads-campaign:'.$campaign->id, 180)->block(1, function () use ($campaign) {
            $campaign->refresh();
            if ($campaign->state !== 'QUEUED') {
                return;
            }
            $account = $campaign->account;
            $spec = $campaign->specification;
            // Read checks happen before any write; a retry never repeats a successful create.
            $this->verify($account);
            $campaign->update(['state' => 'PUBLISHING', 'last_error' => null]);
            try {
                if (! $campaign->remote_id) {
                    $this->createStep($campaign, 'remote_id', 'campaigns', ['name' => $campaign->name, 'objective' => $campaign->objective, 'status' => 'PAUSED', 'special_ad_categories' => $spec['specialCategories'], 'is_adset_budget_sharing_enabled' => false]);
                }
                $targeting = ['geo_locations' => ['countries' => $spec['countries']], 'age_min' => $spec['ageMin'], 'age_max' => $spec['ageMax'], 'publisher_platforms' => $spec['placements'], 'targeting_automation' => ['advantage_audience' => 0]];
                if (in_array('facebook', $spec['placements'])) {
                    $targeting['facebook_positions'] = ['feed'];
                }
                if (in_array('instagram', $spec['placements'])) {
                    $targeting['instagram_positions'] = ['stream'];
                }
                $adset = ['name' => $campaign->name.' · Audience', 'campaign_id' => $campaign->remote_id, 'daily_budget' => $spec['dailyBudget'], 'billing_event' => 'IMPRESSIONS', 'bid_strategy' => 'LOWEST_COST_WITHOUT_CAP', 'optimization_goal' => $spec['destination'] === 'whatsapp' ? 'CONVERSATIONS' : 'LINK_CLICKS', 'targeting' => $targeting, 'end_time' => $spec['endAt'], 'status' => 'PAUSED'];
                if (! empty($spec['startAt'])) {
                    $adset['start_time'] = $spec['startAt'];
                }
                if ($spec['destination'] === 'whatsapp') {
                    $adset['destination_type'] = 'WHATSAPP';
                    $adset['promoted_object'] = ['page_id' => $account->page_id];
                }
                if (! $campaign->adset_id) {
                    $this->createStep($campaign, 'adset_id', 'adsets', $adset);
                }
                $link = $spec['destination'] === 'whatsapp' ? 'https://wa.me/'.$account->whatsapp_number : $spec['websiteUrl'];
                $cta = $spec['destination'] === 'whatsapp' ? ['type' => 'WHATSAPP_MESSAGE', 'value' => ['app_destination' => 'WHATSAPP', 'link' => $link]] : ['type' => $spec['cta'], 'value' => ['link' => $link]];
                $story = ['page_id' => $account->page_id, 'link_data' => ['message' => $spec['message'], 'name' => $spec['headline'], 'link' => $link, 'picture' => $spec['imageUrl'], 'call_to_action' => $cta]];
                if ($account->instagram_id) {
                    $story['instagram_user_id'] = $account->instagram_id;
                }
                if (! $campaign->creative_id) {
                    $this->createStep($campaign, 'creative_id', 'adcreatives', ['name' => $campaign->name.' · Creative', 'object_story_spec' => $story]);
                }
                if (! $campaign->ad_id) {
                    $this->createStep($campaign, 'ad_id', 'ads', ['name' => $campaign->name.' · Ad', 'adset_id' => $campaign->adset_id, 'creative' => ['creative_id' => $campaign->creative_id], 'status' => 'PAUSED']);
                }
                $campaign->update(['state' => 'PUBLISHED', 'remote_status' => 'PAUSED']);
            } catch (\Throwable $e) {
                $campaign->update(['state' => 'NEEDS_REVIEW', 'last_error' => 'Publishing stopped. Check the saved Meta IDs in Ads Manager before creating another copy.']);
                throw $e;
            }
        });
    }

    private function createStep(PaidAdCampaign $campaign, string $column, string $edge, array $payload): void
    {
        $result = $this->client->request($campaign->account, 'POST', 'act_'.$campaign->account->meta_account_id.'/'.$edge, $payload);
        if (! preg_match('/^\d+$/', (string) ($result['id'] ?? ''))) {
            $this->invalid('Meta did not return an object ID. Check Ads Manager before trying again.');
        }
        $campaign->update([$column => $result['id']]);
    }

    public function status(PaidAdCampaign $campaign, string $status): void
    {
        $account = $campaign->account;
        // Imported campaigns are managed at campaign level; existing child statuses are preserved.
        if ($status === 'ACTIVE' && $campaign->state === 'PUBLISHED') {
            $this->client->mutation($account, $campaign->ad_id, ['status' => 'ACTIVE']);
            $this->client->mutation($account, $campaign->adset_id, ['status' => 'ACTIVE']);
        }
        $this->client->mutation($account, $campaign->remote_id, ['status' => $status]);
        $campaign->update(['remote_status' => $status, 'last_error' => null]);
    }

    public function hierarchy(PaidAdCampaign $campaign): array
    {
        if (! $campaign->remote_id) {
            return ['adsets' => [], 'ads' => []];
        }

        return ['adsets' => $this->client->pages($campaign->account, $campaign->remote_id.'/adsets', ['fields' => 'id,name,status,effective_status,daily_budget,start_time,end_time,targeting']), 'ads' => $this->client->pages($campaign->account, $campaign->remote_id.'/ads', ['fields' => 'id,name,status,effective_status,adset_id,creative{id,title,body,image_url,thumbnail_url},issues_info'])];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['meta' => $message]);
    }
}
