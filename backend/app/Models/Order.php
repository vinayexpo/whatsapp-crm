<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'branch_id',
        'contact_id',
        'conversation_id',
        'order_number',
        'status',
        'status_id',
        'fulfillment_type',
        'delivery_address',
        'delivery_lat',
        'delivery_lng',
        'subtotal',
        'tax_total',
        'delivery_charge',
        'discount_total',
        'grand_total',
        'currency',
        'payment_method',
        'payment_status',
        'assigned_staff_user_id',
        'notes',
        'placed_at',
    ];

    protected function casts(): array
    {
        return [
            'delivery_lat' => 'decimal:7',
            'delivery_lng' => 'decimal:7',
            'subtotal' => 'integer',
            'tax_total' => 'integer',
            'delivery_charge' => 'integer',
            'discount_total' => 'integer',
            'grand_total' => 'integer',
            'placed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function orderStatus(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'status_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function orderSessions(): HasMany
    {
        return $this->hasMany(OrderSession::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
