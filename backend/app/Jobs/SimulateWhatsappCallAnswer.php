<?php

namespace App\Jobs;

use App\Events\WhatsappCallSdpAnswerReceived;
use App\Events\WhatsappCallStatusUpdated;
use App\Models\WhatsappCall;
use App\Jobs\Concerns\NotifiesOnFailure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SimulateWhatsappCallAnswer implements ShouldQueue
{
    use Queueable, NotifiesOnFailure;

    public function __construct(public int $whatsappCallId) {}

    public function handle(): void
    {
        $call = WhatsappCall::query()->find($this->whatsappCallId);

        if (! $call || $call->sdp_exchange_status !== 'offer_sent') {
            return;
        }

        // No real Meta connection is configured, so there's no genuine SDP
        // answer to relay -- this fake answer only exists so the local/demo
        // call UI can still walk through ringing -> connected without a real
        // WABA calling-enabled connection.
        $fakeAnswerSdp = "v=0\r\no=- 0 0 IN IP4 127.0.0.1\r\ns=fake-answer\r\nt=0 0\r\n";

        $call->update([
            'remote_sdp_answer' => $fakeAnswerSdp,
            'sdp_exchange_status' => 'connected',
            'status' => 'in_progress',
            'started_at' => $call->started_at ?? now(),
        ]);

        WhatsappCallSdpAnswerReceived::dispatch($call->fresh());
        WhatsappCallStatusUpdated::dispatch($call->fresh());
    }

    public function failed(Throwable $e): void
    {
        $companyId = WhatsappCall::withoutGlobalScopes()->find($this->whatsappCallId)?->company_id;

        $this->recordFailure($e, $companyId);
    }
}
