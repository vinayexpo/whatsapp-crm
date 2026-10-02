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

    public int $tries = 5;

    // A deploy restarts the voice-sidecar container, which takes ~20s to
    // reload its STT model and start accepting connections. Laravel's
    // default retry has no delay, so an inbound call landing in that
    // window burned through all 3 attempts in under a second and was lost
    // -- the call rang but never connected. Backoff gives the container
    // time to finish starting before the next attempt.
    public array $backoff = [2, 5, 10, 15];

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
                'language' => $flow?->language ?? 'en',
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
