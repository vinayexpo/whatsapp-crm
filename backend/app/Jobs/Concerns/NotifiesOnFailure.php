<?php

namespace App\Jobs\Concerns;

use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Notifications\NotificationDispatchService;
use Spatie\Permission\Models\Permission;
use Throwable;

trait NotifiesOnFailure
{
    protected function recordFailure(Throwable $e, ?int $companyId, ?int $webhookEventId = null): void
    {
        if ($webhookEventId) {
            $event = WebhookEvent::query()->find($webhookEventId);

            if ($event) {
                $event->update([
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'attempts' => $event->attempts + 1,
                ]);
            }
        }

        if (! $companyId || ! Permission::where('name', 'team.manage')->exists()) {
            return;
        }

        $dispatchService = app(NotificationDispatchService::class);
        $jobClass = class_basename(static::class);

        User::query()
            ->where('company_id', $companyId)
            ->permission('team.manage')
            ->each(fn (User $user) => $dispatchService->notify(
                $user,
                'job_failed',
                'Background job failed',
                "{$jobClass} failed: {$e->getMessage()}",
                ['jobClass' => static::class, 'webhookEventId' => $webhookEventId],
            ));
    }
}
