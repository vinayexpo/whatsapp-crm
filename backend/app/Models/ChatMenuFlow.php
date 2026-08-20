<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMenuFlow extends Model
{
    /** @use HasFactory<\Database\Factories\ChatMenuFlowFactory> */
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'name',
        'channel',
        'status',
        'trigger_keyword',
        'entry_node_id',
        'nodes',
    ];

    protected function casts(): array
    {
        return [
            'nodes' => 'array',
        ];
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'current_chat_flow_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
