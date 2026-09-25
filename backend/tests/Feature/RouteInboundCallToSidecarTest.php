<?php

use App\Jobs\RouteInboundCallToSidecar;
use App\Models\WhatsappCall;
use App\Models\WhatsappCallFlow;
use Database\Seeders\PipelineStagesSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

it('posts the meta sdp offer to the sidecar sessions endpoint when configured', function () {
    config([
        'services.voice_sidecar.base_url' => 'https://sidecar.test',
        'services.voice_sidecar.shared_secret' => 'sidecar-secret',
    ]);

    Http::fake(['sidecar.test/*' => Http::response(['status' => 'ok'], 200)]);

    $flow = WhatsappCallFlow::factory()->create([
        'greeting_message' => 'Hello there',
        'tts_voice_id' => 'alloy',
    ]);
    $whatsappCall = WhatsappCall::factory()->create([
        'whatsapp_call_flow_id' => $flow->id,
        'meta_call_id' => 'wacid.sidecar1',
    ]);

    (new RouteInboundCallToSidecar($whatsappCall->id, 'v=0...fake-meta-offer'))->handle();

    Http::assertSent(function ($request) use ($whatsappCall) {
        return $request->url() === 'https://sidecar.test/sessions'
            && $request->hasHeader('X-Internal-Secret', 'sidecar-secret')
            && $request['whatsapp_call_id'] === $whatsappCall->uuid
            && $request['meta_call_id'] === 'wacid.sidecar1'
            && $request['sdp_offer'] === 'v=0...fake-meta-offer'
            && $request['greeting'] === 'Hello there'
            && $request['tts_voice_id'] === 'alloy';
    });
});

it('skips silently when the sidecar is not configured', function () {
    config([
        'services.voice_sidecar.base_url' => null,
        'services.voice_sidecar.shared_secret' => null,
    ]);

    Http::fake();

    $whatsappCall = WhatsappCall::factory()->create();

    (new RouteInboundCallToSidecar($whatsappCall->id, 'v=0...offer'))->handle();

    Http::assertNothingSent();
});
