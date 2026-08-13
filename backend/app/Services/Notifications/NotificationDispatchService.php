<?php

namespace App\Services\Notifications;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Event;

class NotificationDispatchService
{
    /**
     * Maps a notification type to the NotificationPreference boolean column
     * that gates whether the user wants to receive it at all.
     */
    private const PREFERENCE_COLUMNS = [
        'new_message' => 'new_message_alerts',
        'campaign_completed' => 'campaign_completed',
        'campaign_failed' => 'campaign_completed',
        'automation_triggered' => 'automation_triggered',
        'whatsapp_call_missed' => 'whatsapp_call_alerts',
        'whatsapp_call_completed' => 'whatsapp_call_alerts',
        'whatsapp_call_ringing' => 'whatsapp_call_alerts',
        'voice_call_completed' => 'voice_call_alerts',
        'voice_call_missed' => 'voice_call_alerts',
        'voice_call_ringing' => 'voice_call_alerts',
        'template_approved' => 'template_status_alerts',
        'template_rejected' => 'template_status_alerts',
        'chatbot_handoff' => 'automation_triggered',
        'job_failed' => 'automation_triggered',
    ];

    public function __construct(private readonly PushNotificationServiceInterface $pushService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function notify(User $user, string $type, string $title, string $body, array $data = []): ?Notification
    {
        $column = self::PREFERENCE_COLUMNS[$type] ?? null;

        if ($column) {
            $preference = NotificationPreference::query()->where('user_id', $user->id)->first();

            if ($preference && ! $preference->{$column}) {
                return null;
            }
        }

        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        Event::dispatch(new NotificationCreated($notification));

        PushSubscription::query()->where('user_id', $user->id)->each(
            fn (PushSubscription $subscription) => $this->pushService->send($subscription, $title, $body, $data)
        );

        return $notification;
    }
}
