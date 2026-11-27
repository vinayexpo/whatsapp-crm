<?php

namespace App\Jobs;

use App\Models\PaidAdAccount;
use App\Models\PaidAdCampaign;
use App\Services\Ads\MetaAdsService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Validation\ValidationException;

class RunMetaAdsTask implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 600;

    public function __construct(public string $task, public int $recordId, public int $companyId) {}

    public function uniqueId(): string
    {
        return $this->task.':'.$this->companyId.':'.$this->recordId;
    }

    public function handle(MetaAdsService $service): void
    {
        if ($this->task === 'publish') {
            $campaign = PaidAdCampaign::where('company_id', $this->companyId)->find($this->recordId);
            if ($campaign) {
                $service->publish($campaign);
            }
        } else {
            $account = PaidAdAccount::where('company_id', $this->companyId)->find($this->recordId);
            if ($account) {
                $service->sync($account);
            }
        }
    }

    public function failed(?\Throwable $e): void
    {
        $reason = $e instanceof ValidationException ? mb_substr(implode(' ', array_merge(...array_values($e->errors()))), 0, 500) : null;
        if ($this->task === 'publish') {
            PaidAdCampaign::where('company_id', $this->companyId)->whereKey($this->recordId)->update(['state' => 'NEEDS_REVIEW', 'last_error' => ($reason ? $reason.' ' : 'Publishing stopped or timed out. ').'Check Ads Manager before creating another copy.']);
        } else {
            PaidAdAccount::where('company_id', $this->companyId)->whereKey($this->recordId)->update(['sync_error' => 'Sync failed. Verify the account permissions and retry. Previous reports were retained.']);
        }
    }
}
