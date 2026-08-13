<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsappTemplateFactory> */
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'api_connection_id', 'meta_template_id', 'name', 'language', 'category',
        'status', 'body', 'variables', 'components', 'created_by_user_id',
        'rejection_reason', 'submitted_at', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'components' => 'array',
            'synced_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function apiConnection(): BelongsTo
    {
        return $this->belongsTo(ApiConnection::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
