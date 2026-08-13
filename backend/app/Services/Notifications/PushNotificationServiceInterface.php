<?php

namespace App\Services\Notifications;

use App\Models\PushSubscription;

interface PushNotificationServiceInterface
{
    /**
     * Send a push payload to a single subscribed device.
     */
    public function send(PushSubscription $subscription, string $title, string $body, array $data = []): void;
}
