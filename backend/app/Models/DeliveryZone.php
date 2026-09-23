<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryZone extends Model
{
    /** @use HasFactory<\Database\Factories\DeliveryZoneFactory> */
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'type',
        'radius_km',
        'polygon',
        'delivery_charge',
        'free_delivery_threshold',
        'min_order_amount',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'radius_km' => 'decimal:2',
            'polygon' => 'array',
            'delivery_charge' => 'integer',
            'free_delivery_threshold' => 'integer',
            'min_order_amount' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
