<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaidAdCampaign extends Model
{
    use BelongsToCompany, HasUuid;

    protected $fillable = ['company_id', 'paid_ad_account_id', 'name', 'objective', 'state', 'remote_id', 'adset_id', 'creative_id', 'ad_id', 'remote_status', 'specification', 'insights', 'last_error', 'synced_at'];

    protected function casts(): array
    {
        return ['specification' => 'array', 'insights' => 'array', 'synced_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaidAdAccount::class, 'paid_ad_account_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
