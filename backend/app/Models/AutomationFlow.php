<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationFlow extends Model
{
    /** @use HasFactory<\Database\Factories\AutomationFlowFactory> */
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'name', 'description', 'status', 'trigger', 'conditions', 'actions',
        'triggered_count', 'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'trigger' => 'array',
            'conditions' => 'array',
            'actions' => 'array',
            'triggered_count' => 'integer',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
