<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappFlow extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsappFlowFactory> */
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'api_connection_id', 'meta_flow_id', 'name', 'status', 'categories', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function apiConnection(): BelongsTo
    {
        return $this->belongsTo(ApiConnection::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
