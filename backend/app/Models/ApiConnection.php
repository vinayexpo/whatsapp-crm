<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiConnection extends Model
{
    /** @use HasFactory<\Database\Factories\ApiConnectionFactory> */
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'company_id', 'channel', 'label', 'account_name', 'identifier', 'status', 'access_token', 'verify_token', 'connected_at',
        'waba_id', 'phone_number_id', 'instagram_account_id', 'twilio_account_sid', 'twilio_phone_number',
        'calling_enabled', 'calling_status', 'calling_verified_at',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'connected_at' => 'datetime',
            'calling_enabled' => 'boolean',
            'calling_verified_at' => 'datetime',
        ];
    }

    public function templates(): HasMany
    {
        return $this->hasMany(WhatsappTemplate::class);
    }

    public function flows(): HasMany
    {
        return $this->hasMany(WhatsappFlow::class);
    }

    public function callFlows(): HasMany
    {
        return $this->hasMany(WhatsappCallFlow::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
