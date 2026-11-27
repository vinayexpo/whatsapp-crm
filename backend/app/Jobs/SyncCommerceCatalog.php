<?php

namespace App\Jobs;

use App\Models\CommerceSetting;
use App\Services\Catalog\TwoWayCatalogSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCommerceCatalog implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public function __construct(public int $settingsId) {}

    public function uniqueId(): string
    {
        return (string) $this->settingsId;
    }

    public function handle(TwoWayCatalogSync $sync): void
    {
        $settings = CommerceSetting::find($this->settingsId);
        if ($settings) {
            $sync->run($settings);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        CommerceSetting::whereKey($this->settingsId)->update(['sync_error' => 'Catalog sync failed or timed out. Check the connection and retry.']);
    }
}
