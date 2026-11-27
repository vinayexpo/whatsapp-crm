<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class PaidAdAccount extends Model
{
    use BelongsToCompany, HasUuid;

    protected $fillable = ['company_id', 'name', 'meta_account_id', 'access_token', 'page_id', 'instagram_id', 'whatsapp_number', 'currency', 'timezone', 'verified_at', 'synced_at', 'sync_error', 'auto_sync'];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return ['access_token' => 'encrypted', 'verified_at' => 'datetime', 'synced_at' => 'datetime', 'auto_sync' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
