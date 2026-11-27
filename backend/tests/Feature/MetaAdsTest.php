<?php

use App\Jobs\RunMetaAdsTask;
use App\Models\Company;
use App\Models\PaidAdAccount;
use App\Models\PaidAdCampaign;
use App\Models\User;
use App\Services\Ads\MetaAdsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

it('does not mark delivery changed without a positive provider acknowledgement', function () {
    $c = savedAdsDraft($this->account);
    $c->update(['state' => 'IMPORTED', 'remote_id' => '111', 'remote_status' => 'PAUSED']);
    Http::fake(['*' => Http::response([])]);
    $this->patchJson('/api/v1/ads/campaigns/'.$c->uuid.'/status', ['status' => 'ACTIVE', 'confirm' => true])->assertUnprocessable();
    expect($c->fresh()->remote_status)->toBe('PAUSED');
});
it('uses fixed host cursor pagination and retains reports after a failed read', function () {
    $c = savedAdsDraft($this->account);
    $c->update(['remote_id' => '111', 'insights' => ['spend' => '95.00']]);
    Http::fake(function ($r) {
        if (str_contains($r->url(), '/campaigns')) {
            if (($r['after'] ?? null) === 'cursor2') {
                return Http::response(['data' => []]);
            }

            return Http::response(['data' => [], 'paging' => ['next' => 'https://untrusted.example/private', 'cursors' => ['after' => 'cursor2']]]);
        }

        return Http::response(['error' => ['code' => 190]], 400);
    });
    expect(fn () => app(MetaAdsService::class)->sync($this->account))->toThrow(ValidationException::class);
    expect($c->fresh()->insights['spend'])->toBe('95.00');
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'untrusted.example'));
    Http::assertSent(fn ($r) => ($r['after'] ?? null) === 'cursor2');
});
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
    $this->account = PaidAdAccount::create(['company_id' => $this->company->id, 'name' => 'Studio Ads', 'meta_account_id' => '12345', 'page_id' => '23456', 'access_token' => str_repeat('secret-', 8), 'instagram_id' => '34567', 'whatsapp_number' => '919876543210', 'currency' => 'INR', 'verified_at' => now()]);
    Http::swap(new Factory);
    Http::preventStrayRequests();
});
function adsSpec(PaidAdAccount $account): array
{
    return ['accountId' => $account->uuid, 'name' => 'Summer enquiries', 'destination' => 'website', 'dailyBudget' => 50000, 'countries' => ['IN'], 'ageMin' => 18, 'ageMax' => 65, 'placements' => ['facebook', 'instagram'], 'startAt' => null, 'endAt' => now()->addDays(7)->toIso8601String(), 'headline' => 'Meet your next team', 'message' => 'Get in touch to learn more.', 'imageUrl' => 'https://example.com/ad.jpg', 'websiteUrl' => 'https://example.com/shop', 'cta' => 'LEARN_MORE', 'specialCategories' => []];
}
function adsFake(?callable $extra = null): void
{
    Http::fake(function ($r) use ($extra) {
        if ($extra && ($result = $extra($r))) {
            return $result;
        }if (str_contains($r->url(), '/act_12345') && ! str_contains($r->url(), '/act_12345/')) {
            return Http::response(['id' => 'act_12345', 'name' => 'Studio Ads', 'currency' => 'INR', 'account_status' => 1, 'timezone_name' => 'Asia/Kolkata', 'user_tasks' => ['ADVERTISE']]);
        }if (str_contains($r->url(), '/23456')) {
            return Http::response(['id' => '23456', 'name' => 'Studio Page', 'instagram_business_account' => ['id' => '34567'], 'whatsapp_number' => '+91 98765 43210']);
        }
        if ($r->method() === 'GET' && preg_match('~/act_12345/(adsets|ads)(?:\?|$)~', $r->url())) {
            return Http::response(['data' => []]);
        }

        return Http::response(['error' => ['code' => 100]], 400);
    });
}
function savedAdsDraft(PaidAdAccount $a): PaidAdCampaign
{
    return PaidAdCampaign::create(['company_id' => $a->company_id, 'paid_ad_account_id' => $a->id, 'name' => 'Summer enquiries', 'objective' => 'OUTCOME_TRAFFIC', 'state' => 'DRAFT', 'specification' => adsSpec($a)]);
}

it('encrypts account tokens and never exposes them through connection responses', function () {
    expect(DB::table('paid_ad_accounts')->value('access_token'))->not->toBe($this->account->access_token);
    $r = $this->getJson('/api/v1/ads/accounts')->assertOk()->assertJsonPath('data.0.id', $this->account->uuid);
    expect($r->getContent())->not->toContain('access_token')->not->toContain($this->account->access_token);
    $this->patchJson('/api/v1/ads/accounts/'.$this->account->uuid, ['name' => 'Renamed', 'metaAccountId' => '12345', 'pageId' => '23456', 'instagramId' => '34567', 'whatsappNumber' => '919876543210'])->assertOk();
    expect($this->account->fresh()->verified_at)->toBeNull();
    expect($this->account->fresh()->access_token)->toBe(str_repeat('secret-', 8));
});
it('isolates accounts campaigns and delivery changes by company', function () {
    $other = Company::factory()->create();
    $account = PaidAdAccount::create(['company_id' => $other->id, 'name' => 'Other', 'meta_account_id' => '98765', 'page_id' => '87654', 'access_token' => str_repeat('private', 8)]);
    $c = savedAdsDraft($account);
    $this->postJson('/api/v1/ads/accounts/'.$account->uuid.'/verify')->assertNotFound();
    $this->getJson('/api/v1/ads/campaigns?accountId='.$account->uuid)->assertNotFound();
    $this->patchJson('/api/v1/ads/campaigns/'.$c->uuid.'/status', ['status' => 'ACTIVE', 'confirm' => true])->assertNotFound();
    Http::assertNothingSent();
});
it('denies agents and companyless users access to paid advertising', function () {
    $this->user->syncRoles(['agent']);
    $this->getJson('/api/v1/ads/accounts')->assertForbidden();
    $this->postJson('/api/v1/ads/campaigns', adsSpec($this->account))->assertForbidden();
});
it('verifies ad account access and page linked identities before publishing', function () {
    adsFake();
    $this->postJson('/api/v1/ads/accounts/'.$this->account->uuid.'/verify')->assertOk()->assertJsonPath('data.page', 'Studio Page');
    expect($this->account->fresh()->verified_at)->not->toBeNull();
});
it('rejects a mismatched whatsapp identity and invalid targeting', function () {
    $this->account->update(['whatsapp_number' => '919000000000']);
    adsFake();
    $this->postJson('/api/v1/ads/accounts/'.$this->account->uuid.'/verify')->assertUnprocessable();
    $this->postJson('/api/v1/ads/campaigns', array_replace(adsSpec($this->account), ['ageMin' => 16, 'placements' => []]))->assertUnprocessable();
});
it('saves drafts without any provider write and rejects editing published objects', function () {
    $response = $this->postJson('/api/v1/ads/campaigns', adsSpec($this->account))->assertOk()->assertJsonPath('data.state', 'DRAFT');
    Http::assertNothingSent();
    $c = PaidAdCampaign::where('uuid', $response->json('data.id'))->firstOrFail();
    $c->update(['state' => 'PUBLISHED', 'remote_id' => '111']);
    $this->patchJson('/api/v1/ads/campaigns/'.$c->uuid, adsSpec($this->account))->assertUnprocessable();
    $this->deleteJson('/api/v1/ads/campaigns/'.$c->uuid)->assertUnprocessable();
});
it('queues publishing once and does not publish live ads in the request', function () {
    Queue::fake();
    $c = savedAdsDraft($this->account);
    $url = '/api/v1/ads/campaigns/'.$c->uuid.'/publish';
    $this->postJson($url)->assertAccepted();
    $this->postJson($url)->assertUnprocessable();
    Queue::assertPushed(RunMetaAdsTask::class, 1);
    Http::assertNothingSent();
});
it('publishes campaign ad set and ad paused with account currency minor units', function () {
    $c = savedAdsDraft($this->account);
    $c->update(['state' => 'QUEUED']);
    $n = 100;
    adsFake(function ($r) use (&$n) {
        if ($r->method() === 'POST') {
            return Http::response(['id' => (string) ++$n]);
        }

        return null;
    });
    app(MetaAdsService::class)->publish($c);
    expect($c->fresh()->state)->toBe('PUBLISHED');
    expect($c->fresh()->remote_status)->toBe('PAUSED');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/adsets') && json_decode($r['targeting'], true)['publisher_platforms'] === ['facebook', 'instagram'] && (int) $r['daily_budget'] === 50000 && $r['status'] === 'PAUSED');
    $writes = Http::recorded(fn ($r) => $r->method() === 'POST');
    expect($writes)->toHaveCount(4);
    foreach ($writes as [$request]) {
        if (! str_ends_with($request->url(), '/adcreatives')) {
            expect($request['status'])->toBe('PAUSED');
        }
    }
    app(MetaAdsService::class)->publish($c);
    expect(Http::recorded(fn ($r) => $r->method() === 'POST'))->toHaveCount(4);
});
it('builds click to whatsapp engagement payloads', function () {
    $c = savedAdsDraft($this->account);
    $spec = $c->specification;
    $spec['destination'] = 'whatsapp';
    $c->update(['state' => 'QUEUED', 'objective' => 'OUTCOME_ENGAGEMENT', 'specification' => $spec]);
    $n = 200;
    adsFake(function ($r) use (&$n) {
        if ($r->method() === 'POST') {
            return Http::response(['id' => (string) ++$n]);
        }

        return null;
    });
    app(MetaAdsService::class)->publish($c);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/adsets') && $r['destination_type'] === 'WHATSAPP' && $r['optimization_goal'] === 'CONVERSATIONS');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/adcreatives') && json_decode($r['object_story_spec'], true)['link_data']['link'] === 'https://wa.me/919876543210');
});
it('preserves partial provider ids and blocks blind retry after publishing failure', function () {
    $c = savedAdsDraft($this->account);
    $c->update(['state' => 'QUEUED']);
    adsFake(function ($r) {
        if (str_ends_with($r->url(), '/campaigns')) {
            return Http::response(['id' => '999']);
        }if ($r->method() === 'POST') {
            return Http::response(['error' => ['code' => 100]], 400);
        }

        return null;
    });
    expect(fn () => app(MetaAdsService::class)->publish($c))->toThrow(ValidationException::class);
    expect($c->fresh()->state)->toBe('NEEDS_REVIEW');
    expect($c->fresh()->remote_id)->toBe('999');
    $this->postJson('/api/v1/ads/campaigns/'.$c->uuid.'/publish')->assertUnprocessable();
});
it('requires explicit confirmation for activation and budget changes', function () {
    $c = savedAdsDraft($this->account);
    $c->update(['state' => 'PUBLISHED', 'remote_id' => '111', 'adset_id' => '222', 'ad_id' => '333']);
    $base = '/api/v1/ads/campaigns/'.$c->uuid;
    $this->patchJson($base.'/status', ['status' => 'ACTIVE'])->assertUnprocessable();
    $this->patchJson($base.'/budget', ['dailyBudget' => 60000])->assertUnprocessable();
    Http::assertNothingSent();
    adsFake(fn ($r) => $r->method() === 'POST' ? Http::response(['success' => true]) : null);
    $this->patchJson($base.'/status', ['status' => 'ACTIVE', 'confirm' => true])->assertOk();
    expect($c->fresh()->remote_status)->toBe('ACTIVE');
    $this->patchJson($base.'/budget', ['dailyBudget' => 60000, 'confirm' => true])->assertOk();
    expect($c->fresh()->specification['dailyBudget'])->toBe(60000);
});
it('does not allow ad ids outside the selected campaign to be mutated', function () {
    $c = savedAdsDraft($this->account);
    $c->update(['state' => 'PUBLISHED', 'remote_id' => '111']);
    adsFake(function ($r) {
        if (str_contains($r->url(), '/111/')) {
            return Http::response(['data' => []]);
        }

        return null;
    });
    $this->patchJson('/api/v1/ads/campaigns/'.$c->uuid.'/objects/status', ['kind' => 'ad', 'objectId' => '999', 'status' => 'ACTIVE', 'confirm' => true])->assertNotFound();
    expect(Http::recorded(fn ($r) => $r->method() === 'POST'))->toHaveCount(0);
});
it('imports real campaign results and reports across pagination without fabricated performance', function () {
    adsFake(function ($r) {
        if (str_contains($r->url(), '/insights')) {
            return Http::response(['data' => [['campaign_id' => '777', 'spend' => '123.45', 'clicks' => '10', 'impressions' => '1000', 'date_start' => '2026-11-20', 'date_stop' => '2026-11-26']]]);
        }if (str_contains($r->url(), '/campaigns')) {
            return Http::response(['data' => [['id' => '777', 'name' => 'Existing Meta ad', 'objective' => 'OUTCOME_TRAFFIC', 'status' => 'ACTIVE']]]);
        }

        return null;
    });
    app(MetaAdsService::class)->sync($this->account);
    $r = $this->getJson('/api/v1/ads/campaigns?accountId='.$this->account->uuid)->assertOk()->assertJsonPath('summary.spend', 123.45)->assertJsonPath('summary.impressions', 1000)->assertJsonPath('data.0.state', 'IMPORTED');
    expect($this->account->fresh()->synced_at)->not->toBeNull();
});
