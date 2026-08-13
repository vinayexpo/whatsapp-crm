<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyMetric extends Model
{
    /** @use HasFactory<\Database\Factories\DailyMetricFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'date', 'whatsapp_sent', 'instagram_sent', 'delivered', 'read', 'replied', 'avg_response_minutes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'whatsapp_sent' => 'integer',
            'instagram_sent' => 'integer',
            'delivered' => 'integer',
            'read' => 'integer',
            'replied' => 'integer',
            'avg_response_minutes' => 'integer',
        ];
    }
}
