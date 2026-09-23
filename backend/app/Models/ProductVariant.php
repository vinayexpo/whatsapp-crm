<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    /** @use HasFactory<\Database\Factories\ProductVariantFactory> */
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'product_id',
        'name',
        'attributes',
        'sku',
        'price_delta',
        'stock_tracked',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'stock_tracked' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
