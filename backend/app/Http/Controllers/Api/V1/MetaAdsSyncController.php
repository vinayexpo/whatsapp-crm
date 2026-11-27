<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaidAdCampaignResource;
use App\Jobs\RunMetaAdsTask;
use App\Models\ActivityLog;
use App\Models\PaidAdAccount;
use App\Models\PaidAdCampaign;
use App\Services\Ads\AdsAiDraft;
use App\Services\Ads\MetaAdsSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class MetaAdsSyncController extends Controller
{
    private function access(Request $r): void
    {
        abort_unless($r->user()->company_id && $r->user()->can('ads.manage'), 403);
    }

    private function campaign(Request $r, string $id): PaidAdCampaign
    {
        return PaidAdCampaign::with('account')->where('company_id', $r->user()->company_id)->where('uuid', $id)->firstOrFail();
    }

    private function audit(Request $r, string $text): void
    {
        ActivityLog::create(['company_id' => $r->user()->company_id, 'type' => 'campaign', 'description' => mb_substr('Meta Ads · '.$r->user()->name.': '.$text, 0, 255), 'occurred_at' => now()]);
    }

    public function settings(Request $r, string $id)
    {
        $this->access($r);
        $v = $r->validate(['autoSync' => 'required|boolean']);
        $a = PaidAdAccount::where('company_id', $r->user()->company_id)->where('uuid', $id)->firstOrFail();
        $a->update(['auto_sync' => $v['autoSync']]);

        return response()->json(['data' => ['autoSync' => $a->auto_sync]]);
    }

    public function pull(Request $r, string $id, MetaAdsSync $s)
    {
        $this->access($r);
        $c = $this->campaign($r, $id);
        abort_unless($c->remote_id, 422, 'Publish or sync a Meta campaign first.');
        Cache::lock('ads-campaign:'.$c->id, 180)->block(1, function () use ($s, $c) {
            $c->refresh();
            if (in_array($c->sync_state, ['QUEUED', 'APPLYING'])) {
                abort(409, 'Wait for the pending push to finish.');
            }
            $s->refresh($c);
        });

        return new PaidAdCampaignResource($c);
    }

    public function stage(Request $r, string $id, MetaAdsSync $s)
    {
        $this->access($r);
        $v = $r->validate(['revision' => 'required|string|size:64', 'kind' => 'required|in:campaign,adset,ad', 'objectId' => ['required', 'regex:/^\d+$/'], 'changes' => 'required|array|min:1|max:4']);
        $c = $this->campaign($r, $id);
        Cache::lock('ads-campaign:'.$c->id, 180)->block(1, function () use ($s, $c, $v) {
            $c->refresh();
            $s->stage($c, $v['revision'], $v['kind'], $v['objectId'], $v['changes']);
        });
        $this->audit($r, 'Staged edits for '.$c->name);

        return new PaidAdCampaignResource($c);
    }

    public function resolve(Request $r, string $id, MetaAdsSync $s)
    {
        $this->access($r);
        $v = $r->validate(['revision' => 'required|string|size:64', 'key' => 'required|string|max:80', 'field' => 'required|string|max:50', 'source' => 'required|in:app,meta']);
        $c = $this->campaign($r, $id);
        Cache::lock('ads-campaign:'.$c->id, 180)->block(1, function () use ($s, $c, $v) {
            $c->refresh();
            $s->resolve($c, $v['key'], $v['field'], $v['source'], $v['revision']);
        });
        $this->audit($r, 'Chose '.$v['source'].' version for '.$c->name);

        return new PaidAdCampaignResource($c);
    }

    public function push(Request $r, string $id, MetaAdsSync $s)
    {
        $this->access($r);
        $v = $r->validate(['confirm' => 'accepted', 'revision' => 'required|string|size:64']);
        $c = $this->campaign($r, $id);
        Cache::lock('ads-campaign:'.$c->id, 180)->block(1, function () use ($s, $c, $v, $r) {
            $c->refresh();
            if (! $c->remote_id || in_array($c->state, ['DRAFT', 'QUEUED', 'PUBLISHING', 'NEEDS_REVIEW']) || in_array($c->sync_state, ['QUEUED', 'APPLYING', 'MISSING']) || ! $c->pending_changes || $c->sync_conflicts) {
                throw ValidationException::withMessages(['sync' => 'Review pending edits and resolve conflicts before pushing.']);
            }
            if (! hash_equals($s->revision($c), $v['revision'])) {
                abort(409, 'The reviewed edits changed. Refresh before pushing.');
            }
            $c->update(['sync_state' => 'QUEUED', 'sync_error' => null]);
            RunMetaAdsTask::dispatch('push', $c->id, $c->company_id, $s->approval($c));
            $this->audit($r, 'Approved Meta push for '.$c->name);
        });

        return response()->json(['data' => ['queued' => true]], 202);
    }

    public function ai(Request $r, AdsAiDraft $ai)
    {
        $this->access($r);
        $v = $r->validate(['accountId' => 'required|uuid', 'brief' => 'required|string|min:15|max:5000', 'destination' => 'required|in:website,whatsapp', 'dailyBudget' => 'required|integer|min:100|max:100000000', 'durationDays' => 'required|integer|min:1|max:90', 'countries' => 'required|array|min:1|max:10', 'countries.*' => ['required', 'regex:/^[A-Z]{2}
$/', 'distinct'], 'placements' => 'required|array|min:1|max:2', 'placements.*' => 'required|in:facebook,instagram', 'imageUrl' => 'nullable|url:https|max:2000', 'websiteUrl' => 'nullable|required_if:destination,website|url:https|max:2000']);
        $a = PaidAdAccount::where('company_id', $r->user()->company_id)->where('uuid', $v['accountId'])->firstOrFail();
        abort_unless($a->verified_at, 422, 'Verify the ad account first.');
        if (in_array('instagram', $v['placements']) && ! $a->instagram_id) {
            abort(422, 'Connect the linked Instagram account first.');
        }

        return response()->json(['data' => $ai->generate($a, $v)]);
    }
}
