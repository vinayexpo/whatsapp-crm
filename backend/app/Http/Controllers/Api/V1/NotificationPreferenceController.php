<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationPreferenceResource;
use App\Models\NotificationPreference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $this->authorizeSettingsNotifications($request);

        $preference = $this->currentPreference($request);

        return response()->json(['data' => new NotificationPreferenceResource($preference)]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeSettingsNotifications($request);

        $data = $request->validate([
            'newMessageAlerts' => ['boolean'],
            'campaignCompleted' => ['boolean'],
            'automationTriggered' => ['boolean'],
            'whatsappCallAlerts' => ['boolean'],
            'voiceCallAlerts' => ['boolean'],
            'templateStatusAlerts' => ['boolean'],
            'dailySummaryEmail' => ['boolean'],
            'weeklyAnalyticsReport' => ['boolean'],
            'soundAlerts' => ['boolean'],
        ]);

        $preference = $this->currentPreference($request);

        $preference->update([
            'new_message_alerts' => $data['newMessageAlerts'] ?? $preference->new_message_alerts,
            'campaign_completed' => $data['campaignCompleted'] ?? $preference->campaign_completed,
            'automation_triggered' => $data['automationTriggered'] ?? $preference->automation_triggered,
            'whatsapp_call_alerts' => $data['whatsappCallAlerts'] ?? $preference->whatsapp_call_alerts,
            'voice_call_alerts' => $data['voiceCallAlerts'] ?? $preference->voice_call_alerts,
            'template_status_alerts' => $data['templateStatusAlerts'] ?? $preference->template_status_alerts,
            'daily_summary_email' => $data['dailySummaryEmail'] ?? $preference->daily_summary_email,
            'weekly_analytics_report' => $data['weeklyAnalyticsReport'] ?? $preference->weekly_analytics_report,
            'sound_alerts' => $data['soundAlerts'] ?? $preference->sound_alerts,
        ]);

        return response()->json(['data' => new NotificationPreferenceResource($preference)]);
    }

    private function authorizeSettingsNotifications(Request $request): void
    {
        if (! $request->user()->can('settings.notifications')) {
            throw new AuthorizationException;
        }
    }

    private function currentPreference(Request $request): NotificationPreference
    {
        return NotificationPreference::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'new_message_alerts' => true,
                'campaign_completed' => true,
                'automation_triggered' => false,
                'whatsapp_call_alerts' => true,
                'voice_call_alerts' => true,
                'template_status_alerts' => true,
                'daily_summary_email' => true,
                'weekly_analytics_report' => true,
                'sound_alerts' => false,
            ],
        );
    }
}
