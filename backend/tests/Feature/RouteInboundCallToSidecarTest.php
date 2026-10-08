<?php

use App\Jobs\RouteInboundCallToSidecar;
use App\Models\AiAssistantSetting;
use App\Models\WhatsappCall;
use App\Models\WhatsappCallFlow;
use App\Scopes\CompanyScope;
use Database\Seeders\PipelineStagesSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

it('posts the meta sdp offer and the company ai voice settings to the sidecar sessions endpoint when configured', function () {
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

    AiAssistantSetting::withoutGlobalScope(CompanyScope::class)->create([
        'company_id' => $whatsappCall->company_id,
        'base_url' => 'https://api.openai.com/v1',
        'api_key' => 'sk-test',
        'model' => 'gpt-4o-mini',
        'stt_model' => 'whisper-1',
        'tts_model' => 'tts-1',
        'tts_voice' => 'alloy',
    ]);

    (new RouteInboundCallToSidecar($whatsappCall->id, 'v=0...fake-meta-offer'))->handle();

    Http::assertSent(function ($request) use ($whatsappCall) {
        return $request->url() === 'https://sidecar.test/sessions'
            && $request->hasHeader('X-Internal-Secret', 'sidecar-secret')
            && $request['whatsapp_call_id'] === $whatsappCall->uuid
            && $request['meta_call_id'] === 'wacid.sidecar1'
            && $request['sdp_offer'] === 'v=0...fake-meta-offer'
            && $request['greeting'] === 'Hello there'
            && $request['tts_voice_id'] === 'alloy'
            && $request['ai_base_url'] === 'https://api.openai.com/v1'
            && $request['ai_api_key'] === 'sk-test'
            && $request['stt_model'] === 'whisper-1'
            && $request['tts_model'] === 'tts-1'
            && $request['tts_voice'] === 'alloy';
    });
});

it('posts null ai voice settings when the company has not configured an AI Assistant', function () {
    config([
        'services.voice_sidecar.base_url' => 'https://sidecar.test',
        'services.voice_sidecar.shared_secret' => 'sidecar-secret',
    ]);

    Http::fake(['sidecar.test/*' => Http::response(['status' => 'ok'], 200)]);

    $whatsappCall = WhatsappCall::factory()->create([
        'meta_call_id' => 'wacid.sidecar2',
    ]);

    (new RouteInboundCallToSidecar($whatsappCall->id, 'v=0...fake-meta-offer'))->handle();

    Http::assertSent(function ($request) {
        return $request['ai_base_url'] === null
            && $request['ai_api_key'] === null
            && $request['stt_model'] === null
            && $request['tts_model'] === null
            && $request['tts_voice'] === null;
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
