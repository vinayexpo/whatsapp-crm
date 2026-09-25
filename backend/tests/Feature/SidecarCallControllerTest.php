<?php

use App\Events\WhatsappCallSdpAnswerReceived;
use App\Models\ApiConnection;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\WhatsappCall;
use App\Models\WhatsappCallFlow;
use Database\Seeders\PipelineStagesSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
    config(['services.voice_sidecar.shared_secret' => 'test-sidecar-secret']);
});

function internalHeaders(): array
{
    return ['X-Internal-Secret' => 'test-sidecar-secret'];
}

it('rejects internal requests without a valid shared secret', function () {
    $whatsappCall = WhatsappCall::factory()->create();

    $this->postJson("/api/internal/whatsapp-calls/{$whatsappCall->uuid}/sdp-answer", [
        'sdp' => 'v=0...',
    ], ['X-Internal-Secret' => 'wrong-secret'])->assertNotFound();
});

it('rejects internal requests when no shared secret is configured', function () {
    config(['services.voice_sidecar.shared_secret' => null]);

    $whatsappCall = WhatsappCall::factory()->create();

    $this->postJson("/api/internal/whatsapp-calls/{$whatsappCall->uuid}/sdp-answer", [
        'sdp' => 'v=0...',
    ], internalHeaders())->assertNotFound();
});

it('stores the sdp answer, dispatches the event, and sends the accept action', function () {
    Event::fake([WhatsappCallSdpAnswerReceived::class]);

    $company = Company::factory()->create();
    $connection = ApiConnection::factory()->create([
        'company_id' => $company->id,
        'channel' => 'whatsapp',
        'calling_enabled' => false,
    ]);
    $contact = Contact::factory()->create(['company_id' => $company->id]);
    $conversation = Conversation::factory()->create([
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'api_connection_id' => $connection->id,
    ]);
    $whatsappCall = WhatsappCall::factory()->create([
        'company_id' => $company->id,
        'conversation_id' => $conversation->id,
        'sdp_exchange_status' => 'offer_sent',
    ]);

    $response = $this->postJson("/api/internal/whatsapp-calls/{$whatsappCall->uuid}/sdp-answer", [
        'sdp' => 'v=0...fake-sidecar-answer',
    ], internalHeaders());

    $response->assertOk()->assertJson(['status' => 'ok']);

    $whatsappCall->refresh();
    expect($whatsappCall->remote_sdp_answer)->toBe('v=0...fake-sidecar-answer');
    expect($whatsappCall->sdp_exchange_status)->toBe('answer_received');

    Event::assertDispatched(WhatsappCallSdpAnswerReceived::class, fn ($event) => $event->whatsappCall->id === $whatsappCall->id);
});

it('returns the next prompt via the shared flow step resolver', function () {
    $flow = WhatsappCallFlow::factory()->create();
    $whatsappCall = WhatsappCall::factory()->create([
        'whatsapp_call_flow_id' => $flow->id,
        'status' => 'in_progress',
        'collected_variables' => [],
    ]);

    $response = $this->postJson("/api/internal/whatsapp-calls/{$whatsappCall->uuid}/next-prompt", [
        'speech' => '$5,000 to $10,000',
    ], internalHeaders());

    $response->assertOk()->assertJsonPath('action', 'prompt');
    expect($whatsappCall->fresh()->collected_variables)->toBe(['budget' => '$5,000 to $10,000']);
});

it('records the sidecar session id via the session-event endpoint', function () {
    $whatsappCall = WhatsappCall::factory()->create();

    $this->postJson("/api/internal/whatsapp-calls/{$whatsappCall->uuid}/session-event", [
        'sidecar_session_id' => 'sidecar-session-abc',
    ], internalHeaders())->assertOk();

    expect($whatsappCall->fresh()->sidecar_session_id)->toBe('sidecar-session-abc');
});
