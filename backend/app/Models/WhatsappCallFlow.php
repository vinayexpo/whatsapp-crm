<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappCallFlow extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsappCallFlowFactory> */
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'api_connection_id',
        'name',
        'status',
        'greeting_message',
        'nodes',
        'fallback_message',
        'max_retries',
    ];

    protected function casts(): array
    {
        return [
            'nodes' => 'array',
            'max_retries' => 'integer',
        ];
    }

    public function apiConnection(): BelongsTo
    {
        return $this->belongsTo(ApiConnection::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(WhatsappCall::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
