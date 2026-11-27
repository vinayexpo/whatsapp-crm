<?php

use App\Models\AiAssistantSetting;
use App\Models\Company;
use App\Models\PaidAdAccount;
use App\Models\PaidAdCampaign;
use App\Models\User;
use App\Services\Ads\MetaAdsSync;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
    $this->account = PaidAdAccount::create(['company_id' => $this->company->id, 'name' => 'Studio', 'meta_account_id' => '12345', 'page_id' => '23456', 'access_token' => str_repeat('meta-private-', 3), 'currency' => 'INR', 'verified_at' => now(), 'whatsapp_number' => '919876543210']);
    $this->campaign = PaidAdCampaign::create(['company_id' => $this->company->id, 'paid_ad_account_id' => $this->account->id, 'name' => 'Meta campaign', 'objective' => 'OUTCOME_TRAFFIC', 'state' => 'IMPORTED', 'remote_id' => '111']);
    $this->snapshot = ['campaign' => ['id' => '111', 'name' => 'Meta campaign', 'objective' => 'OUTCOME_TRAFFIC', 'status' => 'PAUSED', 'special_ad_categories' => []], 'adsets' => [['id' => '222', 'campaign_id' => '111', 'name' => 'India audience', 'status' => 'PAUSED', 'daily_budget' => '50000', 'end_time' => null, 'targeting' => ['geo_locations' => ['countries' => ['IN'], 'location_types' => ['home', 'recent']], 'age_min' => 18, 'age_max' => 65, 'custom_audiences' => [['id' => '999']]]]], 'ads' => [['id' => '333', 'name' => 'Image ad', 'status' => 'PAUSED', 'campaign_id' => '111', 'adset_id' => '222', 'creative' => ['id' => '444', 'title' => 'Original creative']]]];
    $this->sync = app(MetaAdsSync::class);
    $this->sync->receive($this->campaign, $this->snapshot);
    Http::swap(new Factory);
    Http::preventStrayRequests();
});
function stagedAds(PaidAdCampaign $c, MetaAdsSync $sync, array $changes): void
{
    $sync->stage($c, $sync->revision($c), 'adset', '222', $changes);
}
function mockAdsSnapshot(array &$snapshot): void
{
    Http::fake(function ($r) use (&$snapshot) {
        if ($r->method() === 'POST') {
            if (str_contains($r->url(), '/222')) {
                foreach ($r->data() as $k => $v) {
                    $snapshot['adsets'][0][$k] = $k === 'targeting' ? json_decode($v, true) : (string) $v;
                }
            }

return Http::response(['success' => true]);
        }if (str_contains($r->url(), '/111/adsets')) {
            return Http::response(['data' => $snapshot['adsets']]);
        }if (str_contains($r->url(), '/111/ads')) {
            return Http::response(['data' => $snapshot['ads']]);
        }

return Http::response($snapshot['campaign']);
    });
}
function adsAiInput(PaidAdAccount $a): array
{
    return ['accountId' => $a->uuid, 'brief' => 'A studio selling everyday cotton tote bags. Invite visitors to view the collection.', 'destination' => 'website', 'dailyBudget' => 25000, 'countries' => ['IN'], 'durationDays' => 7, 'placements' => ['facebook'], 'websiteUrl' => 'https://example.com/shop', 'imageUrl' => null];
}
function adsAiReply(): array
{
    return ['name' => 'Studio collection', 'headline' => 'Your everyday tote', 'message' => 'Explore our cotton tote collection.', 'cta' => 'SHOP_NOW', 'ageMin' => 18, 'ageMax' => 65, 'specialCategories' => [], 'rationale' => 'A clear product-led introduction for adult shoppers.'];
}

it('pulls names budgets targeting and ad creative data for imported campaigns', function () {
    $this->snapshot['campaign']['name'] = 'Renamed in Meta';
    $this->snapshot['adsets'][0]['daily_budget'] = '65000';
    $this->snapshot['ads'][0]['name'] = 'New ad name';
    mockAdsSnapshot($this->snapshot);
    $this->postJson('/api/v1/ads/campaigns/'.$this->campaign->uuid.'/pull')->assertOk()->assertJsonPath('data.name', 'Renamed in Meta')->assertJsonPath('data.metaSnapshot.adsets.0.daily_budget', '65000')->assertJsonPath('data.metaSnapshot.ads.0.name', 'New ad name');
    expect(Http::recorded(fn ($r) => $r->method() === 'POST'))->toHaveCount(0);
});
it('stages imported adset edits without touching Meta and preserves advanced targeting', function () {
    $target = ['geo_locations' => ['countries' => ['AE']], 'age_min' => 21, 'age_max' => 60];
    $this->patchJson('/api/v1/ads/campaigns/'.$this->campaign->uuid.'/changes', ['revision' => $this->sync->revision($this->campaign), 'kind' => 'adset', 'objectId' => '222', 'changes' => ['daily_budget' => 60000, 'targeting' => $target]])->assertOk()->assertJsonPath('data.syncState', 'PENDING');
    $c = $this->campaign->fresh();
    expect($c->pending_changes['adset:222']['targeting']['value']['custom_audiences'])->toBe([['id' => '999']]);
    expect($c->meta_snapshot['adsets'][0]['daily_budget'])->toBe('50000');
    Http::assertNothingSent();
});
it('rejects stale local edits and objects from another campaign', function () {
    $revision = $this->sync->revision($this->campaign);
    stagedAds($this->campaign, $this->sync, ['name' => 'App name']);
    $base = '/api/v1/ads/campaigns/'.$this->campaign->uuid.'/changes';
    $this->patchJson($base, ['revision' => $revision, 'kind' => 'adset', 'objectId' => '222', 'changes' => ['daily_budget' => 65000]])->assertUnprocessable();
    $this->patchJson($base, ['revision' => $this->sync->revision($this->campaign), 'kind' => 'ad', 'objectId' => '999999', 'changes' => ['name' => 'Other']])->assertNotFound();
    Http::assertNothingSent();
});
it('flags concurrent Meta edits and prevents a conflicted push', function () {
    Queue::fake();
    stagedAds($this->campaign, $this->sync, ['daily_budget' => 60000]);
    $this->snapshot['adsets'][0]['daily_budget'] = '70000';
    $this->sync->receive($this->campaign, $this->snapshot);
    expect($this->campaign->sync_conflicts[0]['base'])->toBe('50000');
    expect($this->campaign->sync_state)->toBe('CONFLICT');
    $this->postJson('/api/v1/ads/campaigns/'.$this->campaign->uuid.'/push', ['revision' => $this->sync->revision($this->campaign), 'confirm' => true])->assertUnprocessable();
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});
it('supports explicit conflict resolution without changing delivery status', function () {
    stagedAds($this->campaign, $this->sync, ['daily_budget' => 60000]);
    $this->snapshot['adsets'][0]['daily_budget'] = '70000';
    $this->sync->receive($this->campaign, $this->snapshot);
    $this->postJson('/api/v1/ads/campaigns/'.$this->campaign->uuid.'/resolve', ['revision' => $this->sync->revision($this->campaign), 'key' => 'adset:222', 'field' => 'daily_budget', 'source' => 'app'])->assertOk()->assertJsonPath('data.syncState', 'PENDING');
    $this->campaign->refresh();
    mockAdsSnapshot($this->snapshot);
    $this->sync->push($this->campaign, $this->sync->approval($this->campaign));
    expect($this->campaign->fresh()->pending_changes)->toBe([]);
    expect($this->campaign->fresh()->sync_state)->toBe('SYNCED');
    expect($this->campaign->fresh()->remote_status)->toBe('PAUSED');
    Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), '/222') && ! isset($r['status']) && (int) $r['daily_budget'] === 60000);
});
it('rechecks Meta immediately before pushing and refuses lost updates', function () {
    stagedAds($this->campaign, $this->sync, ['daily_budget' => 60000]);
    $this->snapshot['adsets'][0]['daily_budget'] = '80000';
    mockAdsSnapshot($this->snapshot);
    expect(fn () => $this->sync->push($this->campaign, $this->sync->approval($this->campaign)))->toThrow(ValidationException::class);
    expect($this->campaign->fresh()->sync_state)->toBe('CONFLICT');
    expect(Http::recorded(fn ($r) => $r->method() === 'POST'))->toHaveCount(0);
});
it('does not apply edits that were added after push approval', function () {
    stagedAds($this->campaign, $this->sync, ['daily_budget' => 60000]);
    $approval = $this->sync->approval($this->campaign);
    stagedAds($this->campaign, $this->sync, ['name' => 'Unapproved addition']);
    expect(fn () => $this->sync->push($this->campaign, $approval))->toThrow(ValidationException::class);
    Http::assertNothingSent();
});
it('preserves null conflict baselines and can discard app edits', function () {
    stagedAds($this->campaign, $this->sync, ['end_time' => now()->addDays(5)->toIso8601String()]);
    $this->snapshot['adsets'][0]['end_time'] = now()->addDays(3)->toIso8601String();
    $this->sync->receive($this->campaign, $this->snapshot);
    stagedAds($this->campaign, $this->sync, ['end_time' => now()->addDays(8)->toIso8601String()]);
    expect($this->campaign->pending_changes['adset:222']['end_time']['base'])->toBeNull();
    $this->sync->resolve($this->campaign, 'adset:222', 'end_time', 'meta', $this->sync->revision($this->campaign));
    expect($this->campaign->pending_changes)->toBe([]);
    expect($this->campaign->sync_state)->toBe('SYNCED');
});
it('blocks read only fields lifetime budgets and advanced geographic rewrites', function () {
    expect(fn () => stagedAds($this->campaign, $this->sync, ['status' => 'ACTIVE']))->toThrow(ValidationException::class);
    $this->snapshot['adsets'][0]['lifetime_budget'] = '100000';
    $this->sync->receive($this->campaign, $this->snapshot);
    expect(fn () => stagedAds($this->campaign, $this->sync, ['daily_budget' => 20000]))->toThrow(ValidationException::class);
});
it('allows automatic pulling to be disabled without losing verification', function () {
    $this->patchJson('/api/v1/ads/accounts/'.$this->account->uuid.'/sync-settings', ['autoSync' => false])->assertOk()->assertJsonPath('data.autoSync', false);
    expect($this->account->fresh()->verified_at)->not->toBeNull();
});
it('isolates sync staging and AI generation by company and role', function () {
    $other = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $other->id]);
    $user->assignRole('admin');
    $this->actingAs($user);
    $this->postJson('/api/v1/ads/campaigns/'.$this->campaign->uuid.'/pull')->assertNotFound();
    $this->postJson('/api/v1/ads/ai/draft', adsAiInput($this->account))->assertNotFound();
    $this->user->syncRoles(['agent']);
    $this->actingAs($this->user)->postJson('/api/v1/ads/ai/draft', adsAiInput($this->account))->assertForbidden();
    Http::assertNothingSent();
});
it('requires a configured company AI provider and never returns simulated copy', function () {
    $this->postJson('/api/v1/ads/ai/draft', adsAiInput($this->account))->assertUnprocessable()->assertJsonValidationErrors('ai');
    Http::assertNothingSent();
});
it('generates an editable AI proposal with authoritative user budget countries and destination', function () {
    AiAssistantSetting::create(['base_url' => 'https://ai.example.test/v1', 'api_key' => 'ai-private-key', 'model' => 'configured-model']);
    $copy = adsAiReply() + ['dailyBudget' => 999999999, 'countries' => ['US'], 'destination' => 'whatsapp', 'status' => 'ACTIVE', 'imageUrl' => 'https://invented.example/image.jpg'];
    Http::fake(['ai.example.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode($copy)]]]])]);
    $this->postJson('/api/v1/ads/ai/draft', adsAiInput($this->account))->assertOk()->assertJsonPath('data.proposal.dailyBudget', 25000)->assertJsonPath('data.proposal.countries', ['IN'])->assertJsonPath('data.proposal.destination', 'website')->assertJsonPath('data.proposal.imageUrl', '');
    expect(PaidAdCampaign::count())->toBe(1);
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'graph.facebook.com'));
    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer ai-private-key') && ! str_contains(json_encode($r->data()), $this->account->access_token));
});
it('rejects invalid AI proposals instead of saving or publishing them', function () {
    AiAssistantSetting::create(['base_url' => 'https://ai.example.test/v1', 'api_key' => 'ai-private-key', 'model' => 'configured-model']);
    Http::fake(['ai.example.test/*' => Http::response(['choices' => [['message' => ['content' => '{"name":"incomplete"}']]]])]);
    $this->postJson('/api/v1/ads/ai/draft',adsAiInput($this->account))->assertUnprocessable();
    expect(PaidAdCampaign::count())->toBe(1);
});
