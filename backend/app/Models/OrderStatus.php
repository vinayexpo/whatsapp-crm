<?php

namespace App\Models;

use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatus extends Model
{
    /** @use HasFactory<\Database\Factories\OrderStatusFactory> */
    use HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'business_type_id',
        'slug',
        'label',
        'sort_order',
        'is_terminal',
        'is_cancellable_from',
        'notify_customer',
        'notification_template_id',
        'allowed_next_status_ids',
    ];

    protected function casts(): array
    {
        return [
            'is_terminal' => 'boolean',
            'is_cancellable_from' => 'boolean',
            'notify_customer' => 'boolean',
            'allowed_next_status_ids' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function notificationTemplate(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplate::class, 'notification_template_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
