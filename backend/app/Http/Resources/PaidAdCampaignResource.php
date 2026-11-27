<?php

namespace App\Http\Resources;

use App\Services\Ads\MetaAdsSync;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaidAdCampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $c = $this->resource;

        return ['id' => $c->uuid, 'accountId' => $c->account->uuid, 'name' => $c->name, 'objective' => $c->objective, 'state' => $c->state, 'remoteId' => $c->remote_id, 'adsetId' => $c->adset_id, 'adId' => $c->ad_id, 'creativeId' => $c->creative_id, 'status' => $c->remote_status, 'specification' => $c->specification, 'insights' => $c->insights, 'lastError' => $c->last_error, 'syncedAt' => $c->synced_at, 'metaSnapshot' => $c->meta_snapshot, 'pendingChanges' => $c->pending_changes ?: new \stdClass, 'syncConflicts' => $c->sync_conflicts ?? [], 'syncState' => $c->sync_state, 'syncError' => $c->sync_error, 'syncRevision' => app(MetaAdsSync::class)->revision($c)];
    }
}
