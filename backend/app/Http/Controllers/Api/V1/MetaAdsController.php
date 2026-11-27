<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavePaidAdCampaignRequest;
use App\Http\Resources\PaidAdCampaignResource;
use App\Jobs\RunMetaAdsTask;
use App\Models\ActivityLog;
use App\Models\PaidAdAccount;
use App\Models\PaidAdCampaign;
use App\Services\Ads\MetaAdsClient;
use App\Services\Ads\MetaAdsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MetaAdsController extends Controller
{
    private function access(Request $request, bool $manage = false): void
    {
        abort_unless($request->user()->company_id && $request->user()->can($manage ? 'ads.manage' : 'ads.view'), 403);
    }

    private function account(Request $r, string $id): PaidAdAccount
    {
        return PaidAdAccount::where('company_id', $r->user()->company_id)->where('uuid', $id)->firstOrFail();
    }

    private function campaign(Request $r, string $id): PaidAdCampaign
    {
        return PaidAdCampaign::with('account')->where('company_id', $r->user()->company_id)->where('uuid', $id)->firstOrFail();
    }

    private function error(string $text): never
    {
        throw ValidationException::withMessages(['ads' => $text]);
    }

    private function audit(Request $r, string $text): void
    {
        ActivityLog::create(['company_id' => $r->user()->company_id, 'type' => 'campaign', 'description' => mb_substr('Meta Ads · '.$r->user()->name.': '.$text, 0, 255), 'occurred_at' => now()]);
    }

    private function accountData(PaidAdAccount $a): array
    {
        return ['id' => $a->uuid, 'name' => $a->name, 'metaAccountId' => $a->meta_account_id, 'pageId' => $a->page_id, 'instagramId' => $a->instagram_id, 'whatsappNumber' => $a->whatsapp_number, 'currency' => $a->currency, 'timezone' => $a->timezone, 'verifiedAt' => $a->verified_at, 'syncedAt' => $a->synced_at, 'syncError' => $a->sync_error, 'autoSync' => $a->auto_sync ?? true];
    }

    private function campaignData(PaidAdCampaign $c): array
    {
        return (new PaidAdCampaignResource($c))->resolve();
    }

    public function accounts(Request $r)
    {
        $this->access($r);

        return response()->json(['data' => PaidAdAccount::where('company_id', $r->user()->company_id)->orderBy('name')->get()->map(fn ($a) => $this->accountData($a))]);
    }

    public function saveAccount(Request $r, ?string $id = null)
    {
        $this->access($r, true);
        $a = $id ? $this->account($r, $id) : new PaidAdAccount(['company_id' => $r->user()->company_id]);
        $v = $r->validate(['name' => ['required', 'string', 'max:120'], 'metaAccountId' => ['required', 'regex:/^\d{3,30}$/', Rule::unique('paid_ad_accounts', 'meta_account_id')->where('company_id', $r->user()->company_id)->ignore($a->id)], 'pageId' => ['required', 'regex:/^\d{3,30}$/'], 'instagramId' => ['nullable', 'regex:/^\d{3,30}$/'], 'whatsappNumber' => ['nullable', 'regex:/^[1-9]\d{7,14}$/'], 'accessToken' => [$id ? 'nullable' : 'required', 'string', 'min:20', 'max:5000']]);
        if ($a->exists && $a->meta_account_id !== $v['metaAccountId'] && PaidAdCampaign::where('paid_ad_account_id', $a->id)->exists()) {
            $this->error('Connect another ad account instead of changing an account with saved campaigns.');
        }
        $a->fill(['name' => $v['name'], 'meta_account_id' => $v['metaAccountId'], 'page_id' => $v['pageId'], 'instagram_id' => $v['instagramId'] ?? null, 'whatsapp_number' => $v['whatsappNumber'] ?? null, 'verified_at' => null]);
        if (! empty($v['accessToken'])) {
            $a->access_token = $v['accessToken'];
        }
        $a->save();
        $this->audit($r, 'Saved ad account '.$a->name);

        return response()->json(['data' => $this->accountData($a)]);
    }

    public function deleteAccount(Request $r, string $id)
    {
        $this->access($r, true);
        $a = $this->account($r, $id);
        if (PaidAdCampaign::where('paid_ad_account_id', $a->id)->exists()) {
            $this->error('Remove local drafts first. Accounts with published campaigns must remain connected for management.');
        }$a->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function verify(Request $r, string $id, MetaAdsService $s)
    {
        $this->access($r, true);
        $a = $this->account($r, $id);
        $a->update(['verified_at' => null]);

        return response()->json(['data' => $s->verify($a)]);
    }

    public function sync(Request $r, string $id)
    {
        $this->access($r, true);
        $a = $this->account($r, $id);
        if (! $a->verified_at) {
            $this->error('Verify the ad account before syncing.');
        }RunMetaAdsTask::dispatch('sync', $a->id, $a->company_id);

        return response()->json(['data' => ['queued' => true]], 202);
    }

    public function index(Request $r)
    {
        $this->access($r);
        $r->validate(['accountId' => ['required', 'uuid'], 'page' => ['nullable', 'integer', 'min:1'], 'search' => ['nullable', 'string', 'max:150']]);
        $a = $this->account($r, $r->input('accountId'));
        $q = PaidAdCampaign::with('account')->where('company_id', $a->company_id)->where('paid_ad_account_id', $a->id);
        if ($r->filled('search')) {
            $q->where('name', 'like', '%'.$r->input('search').'%');
        }
        $summary = ['spend' => 0.0, 'impressions' => 0, 'clicks' => 0, 'campaigns' => (clone $q)->count()];
        foreach ((clone $q)->select(['id', 'insights'])->cursor() as $row) {
            $summary['spend'] += (float) ($row->insights['spend'] ?? 0);
            $summary['impressions'] += (int) ($row->insights['impressions'] ?? 0);
            $summary['clicks'] += (int) ($row->insights['clicks'] ?? 0);
        }
        $p = $q->latest('id')->paginate(20);

        return response()->json(['data' => $p->getCollection()->map(fn ($c) => $this->campaignData($c)), 'summary' => $summary, 'meta' => ['currentPage' => $p->currentPage(), 'lastPage' => $p->lastPage(), 'total' => $p->total()]]);
    }

    public function save(SavePaidAdCampaignRequest $r, ?string $id = null)
    {
        $v = $r->validated();
        $a = $this->account($r, $v['accountId']);
        $c = $id ? $this->campaign($r, $id) : new PaidAdCampaign(['company_id' => $a->company_id, 'paid_ad_account_id' => $a->id]);
        if ($c->state !== 'DRAFT' && $c->exists) {
            $this->error('Only unpublished drafts can be edited. Duplicate a published campaign to create a new draft.');
        }
        if ($c->paid_ad_account_id !== $a->id) {
            $this->error('A draft cannot move between ad accounts.');
        }
        if (! $a->verified_at) {
            $this->error('Verify your ad account before saving campaigns.');
        }
        if (in_array('instagram', $v['placements']) && ! $a->instagram_id) {
            $this->error('Connect the linked Instagram account first.');
        }
        if ($v['destination'] === 'whatsapp' && ! $a->whatsapp_number) {
            $this->error('Connect the Page-linked WhatsApp number first.');
        }
        if (! empty($v['startAt']) && strtotime($v['endAt']) <= strtotime($v['startAt'])) {
            $this->error('The end date must follow the start date.');
        }
        if ($v['specialCategories'] && ((int) $v['ageMin'] !== 18 || (int) $v['ageMax'] !== 65)) {
            $this->error('Special-category campaigns must use broad adult age targeting (18–65+).');
        }
        $save = function () use ($c, $v) {
            if ($c->exists) {
                $c->refresh();
                if ($c->state !== 'DRAFT') {
                    $this->error('This campaign has already been submitted. Refresh before editing.');
                }
            }
            $c->fill(['name' => $v['name'], 'objective' => $v['destination'] === 'whatsapp' ? 'OUTCOME_ENGAGEMENT' : 'OUTCOME_TRAFFIC', 'specification' => $v, 'state' => 'DRAFT']);
            $c->save();
        };
        if ($c->exists) {
            Cache::lock('ads-campaign:'.$c->id, 180)->block(1, $save);
        } else {
            $save();
        }

        return response()->json(['data' => $this->campaignData($c->load('account'))]);
    }

    public function duplicate(Request $r, string $id)
    {
        $this->access($r, true);
        $c = $this->campaign($r, $id);
        if (! $c->specification) {
            $this->error('Imported campaigns must be edited in Meta. Create a new draft here.');
        }$copy = $c->replicate(['uuid', 'remote_id', 'adset_id', 'creative_id', 'ad_id', 'insights', 'synced_at', 'last_error', 'remote_status']);
        $copy->name = mb_substr($c->name.' (copy)', 0, 150);
        $copy->state = 'DRAFT';
        $copy->meta_snapshot = null;
        $copy->pending_changes = null;
        $copy->sync_conflicts = null;
        $copy->sync_state = 'NOT_SYNCED';
        $copy->sync_error = null;
        $copy->save();

        return response()->json(['data' => $this->campaignData($copy->load('account'))]);
    }

    public function delete(Request $r, string $id)
    {
        $this->access($r, true);
        $c = $this->campaign($r, $id);
        Cache::lock('ads-campaign:'.$c->id, 180)->block(1, function () use ($c) {
            $c->refresh();
            if ($c->state !== 'DRAFT') {
                $this->error('Only local drafts can be deleted. Archive published campaigns instead.');
            }
            $c->delete();
        });

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function publish(Request $r, string $id)
    {
        $this->access($r, true);
        $c = $this->campaign($r, $id);

        return Cache::lock('ads-campaign:'.$c->id, 180)->block(1, function () use ($c, $r) {
            $c->refresh();
            if ($c->state !== 'DRAFT') {
                $this->error('This campaign is already submitted. Refresh its delivery status.');
            }if (! $c->account->verified_at) {
                $this->error('Verify the ad account before publishing.');
            }if (strtotime($c->specification['endAt']) <= time()) {
                $this->error('Update the expired schedule before publishing.');
            }$c->update(['state' => 'QUEUED']);
            RunMetaAdsTask::dispatch('publish', $c->id, $c->company_id);
            $this->audit($r, 'Submitted paused campaign '.$c->name);

            return response()->json(['data' => ['queued' => true]], 202);
        });
    }

    public function details(Request $r, string $id, MetaAdsService $s)
    {
        $this->access($r);
        $c = $this->campaign($r, $id);

        return response()->json(['data' => $s->hierarchy($c)]);
    }

    public function status(Request $r, string $id, MetaAdsService $s)
    {
        $this->access($r, true);
        $v = $r->validate(['status' => ['required', Rule::in(['ACTIVE', 'PAUSED', 'ARCHIVED'])], 'confirm' => ['accepted']]);
        $c = $this->campaign($r, $id);
        if (! $c->remote_id || in_array($c->state, ['QUEUED', 'PUBLISHING', 'NEEDS_REVIEW'])) {
            $this->error('Resolve publishing before changing delivery.');
        }
        Cache::lock('ads-campaign:'.$c->id, 180)->block(1, function () use ($s, $c, $v) {
            $s->status($c, $v['status']);
        });
        $this->audit($r, $v['status'].' campaign '.$c->name);

        return response()->json(['data' => $this->campaignData($c)]);
    }

    public function budget(Request $r, string $id, MetaAdsClient $client)
    {
        $this->access($r, true);
        $v = $r->validate(['dailyBudget' => ['required', 'integer', 'min:100', 'max:100000000'], 'confirm' => ['accepted']]);
        $c = $this->campaign($r, $id);
        if ($c->state !== 'PUBLISHED' || ! $c->adset_id) {
            $this->error('Edit imported ad-set budgets in Meta Ads Manager.');
        }
        Cache::lock('ads-campaign:'.$c->id, 180)->block(1, function () use ($client, $c, $v) {
            $client->mutation($c->account, $c->adset_id, ['daily_budget' => $v['dailyBudget']]);
            $spec = $c->specification;
            $spec['dailyBudget'] = $v['dailyBudget'];
            $c->update(['specification' => $spec]);
        });
        $this->audit($r, 'Changed daily budget to '.$c->account->currency.' '.($v['dailyBudget'] / 100).' for '.$c->name);

        return response()->json(['data' => $this->campaignData($c)]);
    }

    public function objectStatus(Request $r, string $id, MetaAdsService $s, MetaAdsClient $client)
    {
        $this->access($r, true);
        $v = $r->validate(['kind' => ['required', Rule::in(['adset', 'ad'])], 'objectId' => ['required', 'regex:/^\d+$/'], 'status' => ['required', Rule::in(['ACTIVE', 'PAUSED'])], 'confirm' => ['accepted']]);
        $c = $this->campaign($r, $id);
        if (! $c->remote_id || in_array($c->state, ['QUEUED', 'PUBLISHING', 'NEEDS_REVIEW'])) {
            $this->error('Resolve publishing before changing delivery.');
        }
        Cache::lock('ads-campaign:'.$c->id, 180)->block(1, function () use ($s, $client, $c, $v) {
            $rows = $s->hierarchy($c)[$v['kind'] === 'adset' ? 'adsets' : 'ads'];
            if (! collect($rows)->contains(fn ($row) => (string) $row['id'] === (string) $v['objectId'])) {
                abort(404);
            }
            $client->mutation($c->account, $v['objectId'], ['status' => $v['status']]);
        });
        $this->audit($r, $v['status'].' '.$v['kind'].' '.$v['objectId'].' in '.$c->name);

        return response()->json(['data' => ['updated' => true]]);
    }
}
