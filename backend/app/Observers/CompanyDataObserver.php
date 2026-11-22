<?php

namespace App\Observers;

use App\Events\CompanyDataUpdated;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\WhatsappTemplate;
use Illuminate\Database\Eloquent\Model;

class CompanyDataObserver
{
    public function created(Model $model): void
    {
        $this->publish($model);
    }

    public function updated(Model $model): void
    {
        if ($model instanceof Contact && ! $model->wasChanged(['pipeline_stage_id', 'deal_value', 'deleted_at'])) {
            return;
        }

        $this->publish($model);
    }

    public function deleted(Model $model): void
    {
        $this->publish($model);
    }

    private function publish(Model $model): void
    {
        $companyId = $model instanceof CampaignRecipient ? $model->campaign?->company_id : $model->company_id;

        if ($companyId === null) {
            return;
        }

        $resource = match (true) {
            $model instanceof Campaign, $model instanceof CampaignRecipient => 'campaigns',
            $model instanceof WhatsappTemplate => 'templates',
            default => 'dashboard',
        };

        CompanyDataUpdated::dispatch($companyId, $resource);
    }
}
