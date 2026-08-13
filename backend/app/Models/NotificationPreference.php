<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationPreferenceFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'user_id',
        'new_message_alerts',
        'campaign_completed',
        'automation_triggered',
        'whatsapp_call_alerts',
        'voice_call_alerts',
        'template_status_alerts',
        'daily_summary_email',
        'weekly_analytics_report',
        'sound_alerts',
    ];

    protected function casts(): array
    {
        return [
            'new_message_alerts' => 'boolean',
            'campaign_completed' => 'boolean',
            'automation_triggered' => 'boolean',
            'whatsapp_call_alerts' => 'boolean',
            'voice_call_alerts' => 'boolean',
            'template_status_alerts' => 'boolean',
            'daily_summary_email' => 'boolean',
            'weekly_analytics_report' => 'boolean',
            'sound_alerts' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
