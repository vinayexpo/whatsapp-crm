<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Database\Factories\CommerceSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceSetting extends Model
{
    /** @use HasFactory<CommerceSettingFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'business_type_id',
        'meta_catalog_id',
        'currency',
        'default_tax_rate_bp',
        'order_number_prefix',
        'session_timeout_minutes',
        'settings',
        'api_connection_id', 'sync_enabled', 'last_sync_at', 'sync_error',
        'payment_configuration_name', 'whatsapp_pay_enabled',
        'razorpay_key_id', 'razorpay_key_secret', 'razorpay_webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'sync_enabled' => 'boolean', 'whatsapp_pay_enabled' => 'boolean', 'last_sync_at' => 'datetime',
            'razorpay_key_id' => 'encrypted', 'razorpay_key_secret' => 'encrypted', 'razorpay_webhook_secret' => 'encrypted',
        ];
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }

    protected $hidden = ['razorpay_key_id', 'razorpay_key_secret', 'razorpay_webhook_secret'];

    public function apiConnection(): BelongsTo
    {
        return $this->belongsTo(ApiConnection::class);
    }
}
