<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderSession extends Model
{
    /** @use HasFactory<\Database\Factories\OrderSessionFactory> */
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'conversation_id',
        'contact_id',
        'branch_id',
        'api_connection_id',
        'status',
        'step',
        'context',
        'order_id',
        'last_interaction_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'last_interaction_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function apiConnection(): BelongsTo
    {
        return $this->belongsTo(ApiConnection::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
