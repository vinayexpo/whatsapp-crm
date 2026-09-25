<?php

namespace App\Jobs;

use App\Jobs\Concerns\NotifiesOnFailure;
use App\Models\WhatsappCall;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class RouteInboundCallToSidecar implements ShouldQueue
{
    use Queueable, NotifiesOnFailure;

    public function __construct(public int $whatsappCallId, public string $metaSdpOffer) {}

    public function handle(): void
    {
        $whatsappCall = WhatsappCall::query()->find($this->whatsappCallId);

        if (! $whatsappCall) {
            return;
        }

        $baseUrl = config('services.voice_sidecar.base_url');
        $secret = config('services.voice_sidecar.shared_secret');

        if (empty($baseUrl) || empty($secret)) {
            Log::warning('RouteInboundCallToSidecar: voice_sidecar not configured, skipping', [
                'whatsapp_call_id' => $whatsappCall->id,
            ]);

            return;
        }

        $flow = $whatsappCall->callFlow;

        Http::withHeaders(['X-Internal-Secret' => $secret])
            ->post(rtrim($baseUrl, '/').'/sessions', [
                'whatsapp_call_id' => $whatsappCall->uuid,
                'meta_call_id' => $whatsappCall->meta_call_id,
                'sdp_offer' => $this->metaSdpOffer,
                'greeting' => $flow?->greeting_message,
                'tts_voice_id' => $flow?->tts_voice_id,
                'callback_base_url' => rtrim(config('app.url'), '/').'/api/internal',
            ])
            ->throw();
    }

    public function failed(Throwable $e): void
    {
        $whatsappCall = WhatsappCall::query()->find($this->whatsappCallId);

        $this->recordFailure($e, $whatsappCall?->company_id);
    }
}
