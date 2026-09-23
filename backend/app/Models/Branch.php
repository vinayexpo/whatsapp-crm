<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    /** @use HasFactory<\Database\Factories\BranchFactory> */
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'api_connection_id',
        'business_type_id',
        'name',
        'slug',
        'address',
        'latitude',
        'longitude',
        'phone',
        'status',
        'operating_hours',
        'holidays',
        'timezone',
        'min_order_amount',
        'default_delivery_charge',
        'delivery_radius_km',
        'currency',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'operating_hours' => 'array',
            'holidays' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'delivery_radius_km' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }

    public function apiConnection(): BelongsTo
    {
        return $this->belongsTo(ApiConnection::class);
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function branchProducts(): HasMany
    {
        return $this->hasMany(BranchProduct::class);
    }

    public function branchPrices(): HasMany
    {
        return $this->hasMany(BranchPrice::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function deliveryZones(): HasMany
    {
        return $this->hasMany(DeliveryZone::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
