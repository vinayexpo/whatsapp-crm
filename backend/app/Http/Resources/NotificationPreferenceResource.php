<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationPreferenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'newMessageAlerts' => $this->new_message_alerts,
            'campaignCompleted' => $this->campaign_completed,
            'automationTriggered' => $this->automation_triggered,
            'whatsappCallAlerts' => $this->whatsapp_call_alerts,
            'voiceCallAlerts' => $this->voice_call_alerts,
            'templateStatusAlerts' => $this->template_status_alerts,
            'dailySummaryEmail' => $this->daily_summary_email,
            'weeklyAnalyticsReport' => $this->weekly_analytics_report,
            'soundAlerts' => $this->sound_alerts,
        ];
    }
}
