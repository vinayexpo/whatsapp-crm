<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use BelongsToCompany, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'category_id',
        'brand',
        'name',
        'sku',
        'meta_retailer_id',
        'meta_synced_at',
        'description',
        'images',
        'base_price',
        'sale_price',
        'tax_rate_bp',
        'weight_grams',
        'dimensions',
        'attributes',
        'delivery_available',
        'pickup_available',
        'is_service',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'dimensions' => 'array',
            'attributes' => 'array',
            'delivery_available' => 'boolean',
            'pickup_available' => 'boolean',
            'is_service' => 'boolean',
            'is_active' => 'boolean',
            'meta_synced_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function addonDefinitions(): BelongsToMany
    {
        return $this->belongsToMany(AddonDefinition::class, 'addon_definition_product');
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

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
