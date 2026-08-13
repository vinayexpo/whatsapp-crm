<?php

namespace App\Jobs;

use App\Models\WhatsappCall;
use App\Jobs\Concerns\NotifiesOnFailure;
use App\Services\Calling\WhatsappCallFinalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessWhatsappCallCompletion implements ShouldQueue
{
    use Queueable, NotifiesOnFailure;

    public function __construct(public int $whatsappCallId) {}

    public function handle(WhatsappCallFinalizer $finalizer): void
    {
        $whatsappCall = WhatsappCall::query()->find($this->whatsappCallId);

        if (! $whatsappCall) {
            return;
        }

        $finalizer->finalize($whatsappCall);
    }

    public function failed(Throwable $e): void
    {
        $companyId = WhatsappCall::withoutGlobalScopes()->find($this->whatsappCallId)?->company_id;

        $this->recordFailure($e, $companyId);
    }
}
