<?php

namespace App\Services\Ads;

use App\Models\PaidAdCampaign;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MetaAdsSync
{
    public const CAMPAIGN_FIELDS = 'id,name,objective,status,effective_status,special_ad_categories';

    public const ADSET_FIELDS = 'id,campaign_id,name,status,effective_status,daily_budget,lifetime_budget,start_time,end_time,targeting,optimization_goal,destination_type';

    public const AD_FIELDS = 'id,campaign_id,adset_id,name,status,effective_status,creative{id,title,body,image_url,thumbnail_url},issues_info';

    public function __construct(private MetaAdsClient $client) {}

    public function revision(PaidAdCampaign $c): string
    {
        return hash('sha256', json_encode(['meta' => $c->meta_snapshot ?? [], 'pending' => $c->pending_changes ?? []], JSON_THROW_ON_ERROR));
    }

    public function approval(PaidAdCampaign $c): string
    {
        return hash('sha256', json_encode($c->pending_changes ?? [], JSON_THROW_ON_ERROR));
    }

    public function objects(array $snapshot): array
    {
        $objects = [];
        if (! empty($snapshot['campaign']['id'])) {
            $objects['campaign:'.$snapshot['campaign']['id']] = $snapshot['campaign'];
        }
        foreach (['adset' => 'adsets', 'ad' => 'ads'] as $kind => $key) {
            foreach ($snapshot[$key] ?? [] as $row) {
                $objects[$kind.':'.$row['id']] = $row;
            }
        }

        return $objects;
    }

    private function equal(mixed $a, mixed $b, string $field): bool
    {
        if (in_array($field, ['start_time', 'end_time'])) {
            return ($a ? strtotime($a) : null) === ($b ? strtotime($b) : null);
        }
        if ($field === 'targeting') {
            return $this->sorted($a) === $this->sorted($b);
        }

        return (string) $a === (string) $b;
    }

    private function sorted(mixed $v): mixed
    {
        if (! is_array($v)) {
            return $v;
        }
        if (array_is_list($v)) {
            sort($v);

            return $v;
        }ksort($v);
        foreach ($v as &$item) {
            $item = $this->sorted($item);
        }

        return $v;
    }

    public function receive(PaidAdCampaign $c, array $snapshot): void
    {
        $pending = $c->pending_changes ?? [];
        $conflicts = [];
        $objects = $this->objects($snapshot);
        foreach ($pending as $key => &$fields) {
            foreach ($fields as $field => $change) {
                if (! isset($objects[$key])) {
                    $conflicts[] = ['key' => $key, 'field' => $field, 'base' => $change['base'], 'meta' => null, 'local' => $change['value'], 'missing' => true];

                    continue;
                }
                $meta = $objects[$key][$field] ?? null;
                if ($this->equal($meta, $change['value'], $field)) {
                    unset($fields[$field]);

                    continue;
                }
                if (! $this->equal($meta, $change['base'], $field)) {
                    $conflicts[] = ['key' => $key, 'field' => $field, 'base' => $change['base'], 'meta' => $meta, 'local' => $change['value'], 'missing' => false];
                }
            }
        }unset($fields);
        $pending = array_filter($pending);
        $data = ['meta_snapshot' => $snapshot, 'pending_changes' => $pending, 'sync_conflicts' => $conflicts, 'sync_state' => $conflicts ? 'CONFLICT' : ($pending ? 'PENDING' : 'SYNCED'), 'sync_error' => null, 'synced_at' => now()];
        if (isset($snapshot['campaign'])) {
            $remote = $snapshot['campaign'];
            $data += ['name' => $remote['name'], 'objective' => $remote['objective'], 'remote_status' => $remote['effective_status'] ?? $remote['status']];
        }
        $c->update($data);
        // Keep the original one-ad-set builder summary current, without inventing a spec for imported campaigns.
        if ($c->specification) {
            $row = collect($snapshot['adsets'] ?? [])->firstWhere('id', $c->adset_id);
            if ($row) {
                $spec = $c->specification;
                if (isset($row['daily_budget'])) {
                    $spec['dailyBudget'] = (int) $row['daily_budget'];
                }
                if (isset($row['end_time'])) {
                    $spec['endAt'] = $row['end_time'];
                }
                $c->update(['specification' => $spec]);
            }
        }
    }

    public function refresh(PaidAdCampaign $c): void
    {
        $account = $c->account;
        $campaign = $this->client->request($account, 'GET', $c->remote_id, ['fields' => self::CAMPAIGN_FIELDS]);
        if ((string) ($campaign['id'] ?? '') !== $c->remote_id) {
            $this->invalid('Meta did not return this campaign. Refresh the account before editing.');
        }
        $adsets = $this->client->pages($account, $c->remote_id.'/adsets', ['fields' => self::ADSET_FIELDS]);
        $ads = $this->client->pages($account, $c->remote_id.'/ads', ['fields' => self::AD_FIELDS]);
        $this->receive($c, ['campaign' => $campaign, 'adsets' => $adsets, 'ads' => $ads]);
    }

    public function stage(PaidAdCampaign $c, string $revision, string $kind, string $objectId, array $changes): void
    {
        if (in_array($c->state, ['DRAFT', 'QUEUED', 'PUBLISHING', 'NEEDS_REVIEW']) || ! $c->meta_snapshot) {
            $this->invalid('Sync a fully published campaign from Meta before editing.');
        }
        if ($c->sync_state === 'MISSING') {
            $this->invalid('Pull this campaign successfully from Meta before editing it.');
        }
        if (in_array($c->sync_state, ['QUEUED', 'APPLYING'])) {
            $this->invalid('Wait for the current push to finish before editing.');
        }
        if (! hash_equals($this->revision($c), $revision)) {
            $this->invalid('Meta data changed since you opened this editor. Refresh and review it first.');
        }
        $key = $kind.':'.$objectId;
        $row = $this->objects($c->meta_snapshot)[$key] ?? null;
        if (! $row) {
            abort(404);
        }
        $rules = ['name' => ['sometimes', 'required', 'string', 'max:150']];
        if ($kind === 'adset') {
            $rules += ['daily_budget' => ['sometimes', 'required', 'integer', 'min:100', 'max:100000000'], 'end_time' => ['sometimes', 'required', 'date', 'after:now'], 'targeting' => ['sometimes', 'array:geo_locations,age_min,age_max'], 'targeting.geo_locations' => ['required_with:targeting', 'array:countries'], 'targeting.geo_locations.countries' => ['required_with:targeting', 'array', 'min:1', 'max:10'], 'targeting.geo_locations.countries.*' => ['string', 'regex:/^[A-Z]{2}$/', 'distinct'], 'targeting.age_min' => ['required_with:targeting', 'integer', 'min:18', 'max:65'], 'targeting.age_max' => ['required_with:targeting', 'integer', 'gte:targeting.age_min', 'max:65']];
        }
        if (array_diff(array_keys($changes), array_keys($rules))) {
            $this->invalid('This field cannot be edited here. Other Meta settings are preserved.');
        }
        $valid = Validator::make($changes, $rules)->validate();
        if (! $valid) {
            $this->invalid('Choose at least one field to change.');
        }
        if (isset($valid['daily_budget']) && (empty($row['daily_budget']) || ! empty($row['lifetime_budget']))) {
            $this->invalid('Lifetime or campaign-level budgets must be edited in Meta.');
        }
        if (isset($valid['end_time']) && ! empty($row['start_time']) && strtotime($valid['end_time']) <= strtotime($row['start_time'])) {
            $this->invalid('The end date must follow the ad set start date.');
        }
        if (isset($valid['targeting'])) {
            $raw = $row['targeting'] ?? [];
            if (array_diff(array_keys($raw['geo_locations'] ?? []), ['countries', 'location_types'])) {
                $this->invalid('Edit region or city targeting in Meta. This editor supports country targeting.');
            }
            if (! empty($c->meta_snapshot['campaign']['special_ad_categories']) && ((int) $valid['targeting']['age_min'] !== 18 || (int) $valid['targeting']['age_max'] !== 65)) {
                $this->invalid('Special-category campaigns must use broad adult age targeting (18–65+).');
            }
            $valid['targeting'] = array_replace($raw, $valid['targeting']);
            $valid['targeting']['geo_locations'] = array_replace($raw['geo_locations'] ?? [], $valid['targeting']['geo_locations']);
        }
        $pending = $c->pending_changes ?? [];
        foreach ($valid as $field => $value) {
            if ($this->equal($row[$field] ?? null, $value, $field)) {
                unset($pending[$key][$field]);

                continue;
            }
            $base = array_key_exists($field, $pending[$key] ?? []) ? $pending[$key][$field]['base'] : ($row[$field] ?? null);
            $pending[$key][$field] = ['base' => $base, 'value' => $value];
        }
        $c->update(['pending_changes' => array_filter($pending)]);
        $this->receive($c, $c->meta_snapshot);
    }

    public function resolve(PaidAdCampaign $c, string $key, string $field, string $source, string $revision): void
    {
        if (! hash_equals($this->revision($c), $revision)) {
            $this->invalid('Refresh the conflict before choosing a version.');
        }
        if (in_array($c->sync_state, ['QUEUED', 'APPLYING'])) {
            $this->invalid('Wait for the current push to finish.');
        }
        $pending = $c->pending_changes ?? [];
        if (! isset($pending[$key][$field])) {
            $this->invalid('This change is no longer pending.');
        }
        $row = $this->objects($c->meta_snapshot ?? [])[$key] ?? null;
        if ($source === 'meta') {
            unset($pending[$key][$field]);
        } else {
            if (! $row) {
                $this->invalid('The Meta object is missing. Discard the app change or review it in Meta.');
            }
            $pending[$key][$field]['base'] = $row[$field] ?? null;
        }
        $c->update(['pending_changes' => array_filter($pending)]);
        $this->receive($c, $c->meta_snapshot);
    }

    public function push(PaidAdCampaign $c, string $approval): void
    {
        Cache::lock('ads-campaign:'.$c->id, 180)->block(1, function () use ($c, $approval) {
            $c->refresh();
            if (! hash_equals($this->approval($c), $approval)) {
                $this->invalid('Pending edits changed after approval. Review and submit them again.');
            }
            $c->update(['sync_state' => 'APPLYING', 'sync_error' => null]);
            $this->refresh($c);
            if ($c->sync_conflicts) {
                $this->invalid('Meta changed the same fields. Resolve the conflicts before pushing.');
            }
            foreach ($c->pending_changes ?? [] as $key => $fields) {
                [, $id] = explode(':', $key, 2);
                $data = [];
                foreach ($fields as $field => $change) {
                    $data[$field] = $change['value'];
                }
                $this->client->mutation($c->account, $id, $data);
            }
            $this->refresh($c);
            if ($c->pending_changes) {
                $c->update(['sync_state' => 'PENDING', 'sync_error' => 'Meta accepted the request. Sync again to confirm the applied values.']);
            }
        });
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['sync' => $message]);
    }
}
