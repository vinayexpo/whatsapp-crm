<?php

use App\Jobs\ProcessWhatsappCallCompletion;
use App\Models\AiAssistantSetting;
use App\Models\Company;
use App\Models\WhatsappCall;
use App\Models\WhatsappCallFlow;
use Database\Seeders\PipelineStagesSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

function createAiAssistantSettingForCompany(int $companyId): AiAssistantSetting
{
    $setting = new AiAssistantSetting([
        'base_url' => 'https://fake-llm.test/v1',
        'api_key' => 'test-key',
        'model' => 'gpt-test',
    ]);
    $setting->company_id = $companyId;
    $setting->save();

    return $setting;
}

function fakeAiConversationCompletion(array $payload): void
{
    Http::fake([
        '*/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => json_encode($payload)]],
            ],
        ], 200),
    ]);
}

it('prompts with the ai conversation response when not done', function () {
    $company = Company::factory()->create();
    $flow = WhatsappCallFlow::factory()->create([
        'company_id' => $company->id,
        'conversation_mode' => 'ai_conversation',
        'ai_conversation_goal' => 'Collect the caller name.',
    ]);
    createAiAssistantSettingForCompany($flow->company_id);

    $whatsappCall = WhatsappCall::factory()->create([
        'whatsapp_call_flow_id' => $flow->id,
        'meta_call_id' => 'wacid.ai.1',
        'status' => 'in_progress',
    ]);

    fakeAiConversationCompletion(['say' => 'Sure, what is your name?', 'done' => false, 'handoff_to_human' => false]);

    $response = $this->postJson('/api/webhooks/whatsapp-call/action', [
        'call_id' => 'wacid.ai.1',
        'speech' => 'Hi, I need help.',
    ]);

    $response->assertOk()
        ->assertJsonPath('action', 'prompt')
        ->assertJsonPath('prompt', 'Sure, what is your name?');

    expect($whatsappCall->fresh()->status)->toBe('in_progress');
});

it('terminates the call when the ai marks the conversation done', function () {
    Queue::fake();

    $company = Company::factory()->create();
    $flow = WhatsappCallFlow::factory()->create([
        'company_id' => $company->id,
        'conversation_mode' => 'ai_conversation',
        'ai_conversation_goal' => 'Collect the caller name.',
    ]);
    createAiAssistantSettingForCompany($flow->company_id);

    $whatsappCall = WhatsappCall::factory()->create([
        'whatsapp_call_flow_id' => $flow->id,
        'meta_call_id' => 'wacid.ai.2',
        'status' => 'in_progress',
    ]);

    fakeAiConversationCompletion(['say' => 'Great, thanks! Goodbye.', 'done' => true, 'handoff_to_human' => false]);

    $response = $this->postJson('/api/webhooks/whatsapp-call/action', [
        'call_id' => 'wacid.ai.2',
        'speech' => 'My name is Praveen.',
    ]);

    $response->assertOk()
        ->assertJsonPath('action', 'terminate')
        ->assertJsonPath('prompt', 'Great, thanks! Goodbye.');

    // The resolver only signals "nothing more to say" -- the sidecar still
    // has to speak the closing line and tear down the peer connection
    // before the call is actually marked completed (see
    // SidecarCallController::ended()).
    expect($whatsappCall->fresh()->status)->toBe('in_progress');
    Queue::assertNotPushed(ProcessWhatsappCallCompletion::class);
});

it('terminates and flags human followup when the ai requests handoff', function () {
    Queue::fake();

    $company = Company::factory()->create();
    $flow = WhatsappCallFlow::factory()->create([
        'company_id' => $company->id,
        'conversation_mode' => 'ai_conversation',
        'ai_conversation_goal' => 'Help with billing issues.',
    ]);
    createAiAssistantSettingForCompany($flow->company_id);

    $whatsappCall = WhatsappCall::factory()->create([
        'whatsapp_call_flow_id' => $flow->id,
        'meta_call_id' => 'wacid.ai.3',
        'status' => 'in_progress',
    ]);

    fakeAiConversationCompletion(['say' => "I'll connect you to a specialist.", 'done' => false, 'handoff_to_human' => true]);

    $response = $this->postJson('/api/webhooks/whatsapp-call/action', [
        'call_id' => 'wacid.ai.3',
        'speech' => 'I need a refund dispute resolved.',
    ]);

    $response->assertOk()->assertJsonPath('action', 'terminate');

    expect($whatsappCall->fresh()->needs_human_followup)->toBeTrue();
    expect($whatsappCall->fresh()->status)->toBe('in_progress');
    Queue::assertNotPushed(ProcessWhatsappCallCompletion::class);
});

it('falls back to the flow fallback message when the ai response is malformed', function () {
    Queue::fake();

    $company = Company::factory()->create();
    $flow = WhatsappCallFlow::factory()->create([
        'company_id' => $company->id,
        'conversation_mode' => 'ai_conversation',
        'ai_conversation_goal' => 'Help the caller.',
        'fallback_message' => 'Sorry, something went wrong. Goodbye.',
    ]);
    createAiAssistantSettingForCompany($flow->company_id);

    $whatsappCall = WhatsappCall::factory()->create([
        'whatsapp_call_flow_id' => $flow->id,
        'meta_call_id' => 'wacid.ai.4',
        'status' => 'in_progress',
    ]);

    Http::fake([
        '*/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => 'not valid json']],
            ],
        ], 200),
    ]);

    $response = $this->postJson('/api/webhooks/whatsapp-call/action', [
        'call_id' => 'wacid.ai.4',
        'speech' => 'Hello?',
    ]);

    $response->assertOk()
        ->assertJsonPath('action', 'terminate')
        ->assertJsonPath('prompt', 'Sorry, something went wrong. Goodbye.');

    expect($whatsappCall->fresh()->status)->toBe('in_progress');
});

it('falls back to the flow fallback message when no ai assistant is configured', function () {
    Queue::fake();

    $company = Company::factory()->create();
    $flow = WhatsappCallFlow::factory()->create([
        'company_id' => $company->id,
        'conversation_mode' => 'ai_conversation',
        'fallback_message' => 'Sorry, something went wrong. Goodbye.',
    ]);

    $whatsappCall = WhatsappCall::factory()->create([
        'whatsapp_call_flow_id' => $flow->id,
        'meta_call_id' => 'wacid.ai.5',
        'status' => 'in_progress',
    ]);

    Http::fake();

    $response = $this->postJson('/api/webhooks/whatsapp-call/action', [
        'call_id' => 'wacid.ai.5',
        'speech' => 'Hello?',
    ]);

    $response->assertOk()
        ->assertJsonPath('action', 'terminate')
        ->assertJsonPath('prompt', 'Sorry, something went wrong. Goodbye.');

    Http::assertNothingSent();
});

it('force-terminates after hitting the hard turn ceiling regardless of the ai response', function () {
    Queue::fake();

    $company = Company::factory()->create();
    $flow = WhatsappCallFlow::factory()->create([
        'company_id' => $company->id,
        'conversation_mode' => 'ai_conversation',
        'fallback_message' => 'We have to end the call now. Goodbye.',
    ]);
    createAiAssistantSettingForCompany($flow->company_id);

    $longTranscript = [];
    for ($i = 0; $i < 20; $i++) {
        $longTranscript[] = ['role' => $i % 2 === 0 ? 'ai' : 'lead', 'text' => "turn {$i}", 'at' => now()->toIso8601String()];
    }

    $whatsappCall = WhatsappCall::factory()->create([
        'whatsapp_call_flow_id' => $flow->id,
        'meta_call_id' => 'wacid.ai.6',
        'status' => 'in_progress',
        'transcript' => $longTranscript,
    ]);

    fakeAiConversationCompletion(['say' => 'Still going...', 'done' => false, 'handoff_to_human' => false]);

    $response = $this->postJson('/api/webhooks/whatsapp-call/action', [
        'call_id' => 'wacid.ai.6',
        'speech' => 'One more thing...',
    ]);

    $response->assertOk()
        ->assertJsonPath('action', 'terminate')
        ->assertJsonPath('prompt', 'We have to end the call now. Goodbye.');

    Http::assertNothingSent();
    expect($whatsappCall->fresh()->status)->toBe('in_progress');
});

it('still walks fixed nodes unaffected when conversation_mode is scripted', function () {
    $flow = WhatsappCallFlow::factory()->create(['conversation_mode' => 'scripted']);
    $whatsappCall = WhatsappCall::factory()->create([
        'whatsapp_call_flow_id' => $flow->id,
        'meta_call_id' => 'wacid.ai.7',
        'status' => 'in_progress',
        'collected_variables' => [],
    ]);

    Http::fake();

    $response = $this->postJson('/api/webhooks/whatsapp-call/action', [
        'call_id' => 'wacid.ai.7',
        'speech' => '$5,000 to $10,000',
    ]);

    $response->assertOk()->assertJsonPath('action', 'prompt');
    Http::assertNothingSent();
});
