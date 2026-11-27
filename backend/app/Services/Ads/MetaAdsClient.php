<?php

namespace App\Services\Ads;

use App\Models\PaidAdAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class MetaAdsClient
{
    public function request(PaidAdAccount $account, string $method, string $path, array $data = []): array
    {
        $version = config('services.meta.ads_graph_version', 'v27.0');
        $http = Http::withToken($account->access_token)->acceptJson()->connectTimeout(5)->timeout(20);
        $url = "https://graph.facebook.com/{$version}/{$path}";
        try {
            $response = $method === 'GET' ? $http->get($url, $data) : $http->asForm()->post($url, collect($data)->map(fn ($v) => is_array($v) ? json_encode($v, JSON_THROW_ON_ERROR) : $v)->all());
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['meta' => 'Meta did not respond. Check Ads Manager before retrying a publishing request.']);
        }
        if (! $response->successful() || $response->json('error')) {
            $message = match ((int) $response->json('error.code')) {
                190 => 'The Meta token expired or is invalid. Update the account connection.',
                10, 200, 294 => 'Meta denied access. Check ads_read, ads_management and access to the ad account and Page.',
                4, 17, 32, 613, 80004 => 'Meta is rate limiting this account. Wait before syncing again.',
                default => $response->json('error.error_user_msg') ?: 'Meta rejected the request. Review the account, budget, targeting and creative in Ads Manager.',
            };
            throw ValidationException::withMessages(['meta' => mb_substr(str_replace($account->access_token, '[redacted]', (string) $message), 0, 500)]);
        }

        return $response->json() ?? [];
    }

    public function mutation(PaidAdAccount $account, string $path, array $data): void
    {
        $result = $this->request($account, 'POST', $path, $data);
        if (($result['success'] ?? null) !== true) {
            throw ValidationException::withMessages(['meta' => 'Meta did not confirm this change. Refresh from Meta before trying again.']);
        }
    }

    public function pages(PaidAdAccount $account, string $path, array $params): array
    {
        $rows = [];
        for ($page = 0; $page < 100; $page++) {
            $result = $this->request($account, 'GET', $path, $params + ['limit' => 100]);
            $rows = [...$rows, ...($result['data'] ?? [])];
            if (empty($result['paging']['next'])) {
                return $rows;
            }
            $cursor = $result['paging']['cursors']['after'] ?? null;
            if (! $cursor || $cursor === ($params['after'] ?? null)) {
                throw ValidationException::withMessages(['meta' => 'Meta returned incomplete pagination. No complete sync was recorded.']);
            }
            $params['after'] = $cursor;
        }
        throw ValidationException::withMessages(['meta' => 'This account exceeds the sync limit. Open Ads Manager for its full report.']);
    }
}
