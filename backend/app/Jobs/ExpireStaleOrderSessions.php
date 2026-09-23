<?php

namespace App\Jobs;

use App\Jobs\Concerns\NotifiesOnFailure;
use App\Models\CommerceSetting;
use App\Models\OrderSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Scheduled periodically to mark abandoned commerce sessions expired, based
 * on each company's commerce_settings.session_timeout_minutes (defaulting to
 * 30 when unset). Freeing the active_conversation_id unique slot lets a
 * customer start a fresh session later.
 */
class ExpireStaleOrderSessions implements ShouldQueue
{
    use Queueable, NotifiesOnFailure;

    public function handle(): void
    {
        $timeoutsByCompany = CommerceSetting::withoutGlobalScopes()->pluck('session_timeout_minutes', 'company_id');

        OrderSession::query()
            ->where('status', 'active')
            ->whereNotNull('last_interaction_at')
            ->chunkById(100, function ($sessions) use ($timeoutsByCompany) {
                foreach ($sessions as $session) {
                    $timeoutMinutes = $timeoutsByCompany[$session->company_id] ?? 30;

                    if ($session->last_interaction_at->addMinutes($timeoutMinutes)->isPast()) {
                        $session->update(['status' => 'expired']);
                    }
                }
            });
    }

    public function failed(Throwable $e): void
    {
        $this->recordFailure($e, null);
    }
}
