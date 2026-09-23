<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessType extends Model
{
    /** @use HasFactory<\Database\Factories\BusinessTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'catalog_labels',
        'default_order_statuses',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'catalog_labels' => 'array',
            'default_order_statuses' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
