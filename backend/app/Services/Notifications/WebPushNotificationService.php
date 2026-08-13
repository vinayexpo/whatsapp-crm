<?php

namespace App\Services\Notifications;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushNotificationService implements PushNotificationServiceInterface
{
    public function send(PushSubscription $subscription, string $title, string $body, array $data = []): void
    {
        $publicKey = config('services.web_push.vapid_public_key');
        $privateKey = config('services.web_push.vapid_private_key');

        if (! $publicKey || ! $privateKey) {
            Log::warning('WebPushNotificationService: VAPID keys not configured, skipping push send', [
                'push_subscription_id' => $subscription->id,
            ]);

            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.web_push.vapid_subject'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);

        $webPush->queueNotification(
            Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => $subscription->content_encoding,
            ]),
            json_encode(['title' => $title, 'body' => $body, 'data' => $data]),
        );

        foreach ($webPush->flush() as $report) {
            if (! $report->isSuccess()) {
                Log::warning('WebPushNotificationService: push delivery failed', [
                    'push_subscription_id' => $subscription->id,
                    'reason' => $report->getReason(),
                ]);

                if ($report->isSubscriptionExpired()) {
                    $subscription->delete();
                }
            }
        }
    }
}
