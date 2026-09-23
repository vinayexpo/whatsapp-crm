<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AddonDefinition extends Model
{
    /** @use HasFactory<\Database\Factories\AddonDefinitionFactory> */
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'name',
        'price',
        'max_quantity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'addon_definition_product');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
