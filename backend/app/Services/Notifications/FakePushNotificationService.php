<?php

namespace App\Services\Notifications;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;

class FakePushNotificationService implements PushNotificationServiceInterface
{
    public function send(PushSubscription $subscription, string $title, string $body, array $data = []): void
    {
        Log::info('FakePushNotificationService: simulated push send', [
            'push_subscription_id' => $subscription->id,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }
}
