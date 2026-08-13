<?php

namespace App\Jobs;

use App\Models\VoiceCall;
use App\Jobs\Concerns\NotifiesOnFailure;
use App\Services\Voice\VoiceCallFinalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessVoiceCallCompletion implements ShouldQueue
{
    use Queueable, NotifiesOnFailure;

    public function __construct(public int $voiceCallId) {}

    public function handle(VoiceCallFinalizer $finalizer): void
    {
        $voiceCall = VoiceCall::query()->find($this->voiceCallId);

        if (! $voiceCall) {
            return;
        }

        $finalizer->finalize($voiceCall);
    }

    public function failed(Throwable $e): void
    {
        $companyId = VoiceCall::withoutGlobalScopes()->find($this->voiceCallId)?->company_id;

        $this->recordFailure($e, $companyId);
    }
}
