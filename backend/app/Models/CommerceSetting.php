<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceSetting extends Model
{
    /** @use HasFactory<\Database\Factories\CommerceSettingFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'business_type_id',
        'meta_catalog_id',
        'currency',
        'default_tax_rate_bp',
        'order_number_prefix',
        'session_timeout_minutes',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
}
