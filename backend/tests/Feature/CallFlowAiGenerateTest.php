<?php

use App\Models\AiAssistantSetting;
use App\Models\ApiConnection;
use App\Models\User;
use App\Models\WhatsappCallFlow;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function actingAsCallFlowAiRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function fakeAiGeneratedNodesResponse(array $nodes): void
{
    Http::fake([
        '*/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => json_encode(['nodes' => $nodes])]],
            ],
        ], 200),
    ]);
}

it('generates and returns proposed nodes without persisting by default', function () {
    $manager = actingAsCallFlowAiRole('manager');
    AiAssistantSetting::create([
        'company_id' => $manager->company_id,
        'base_url' => 'https://fake-llm.test/v1',
        'api_key' => 'test-key',
        'model' => 'gpt-test',
    ]);
    $flow = WhatsappCallFlow::factory()->create(['company_id' => $manager->company_id]);

    fakeAiGeneratedNodesResponse([
        ['id' => 'q1', 'type' => 'question', 'prompt' => 'What is your name?'],
        ['id' => 'end', 'type' => 'end_call', 'prompt' => 'Bye'],
    ]);

    $response = $this->actingAs($manager)->postJson("/api/v1/whatsapp-call-flows/{$flow->uuid}/ai-generate", [
        'instruction' => 'Ask for the name then end the call.',
    ]);

    $response->assertOk();
    expect($response->json('data.nodes'))->toHaveCount(2);
    expect($flow->fresh()->nodes)->not->toBe($response->json('data.nodes'));
});

it('applies generated nodes directly when applyDirectly is true', function () {
    $manager = actingAsCallFlowAiRole('manager');
    AiAssistantSetting::create([
        'company_id' => $manager->company_id,
        'base_url' => 'https://fake-llm.test/v1',
        'api_key' => 'test-key',
        'model' => 'gpt-test',
    ]);
    $flow = WhatsappCallFlow::factory()->create(['company_id' => $manager->company_id]);

    fakeAiGeneratedNodesResponse([
        ['id' => 'end', 'type' => 'end_call', 'prompt' => 'Thanks, bye!'],
    ]);

    $response = $this->actingAs($manager)->postJson("/api/v1/whatsapp-call-flows/{$flow->uuid}/ai-generate", [
        'instruction' => 'Just say thanks and end the call.',
        'applyDirectly' => true,
    ]);

    $response->assertOk();
    expect($flow->fresh()->nodes)->toHaveCount(1);
    expect($flow->fresh()->nodes[0]['id'])->toBe('end');
});

it('returns a 422 when the ai response is malformed json', function () {
    $manager = actingAsCallFlowAiRole('manager');
    AiAssistantSetting::create([
        'company_id' => $manager->company_id,
        'base_url' => 'https://fake-llm.test/v1',
        'api_key' => 'test-key',
        'model' => 'gpt-test',
    ]);
    $flow = WhatsappCallFlow::factory()->create(['company_id' => $manager->company_id]);

    Http::fake([
        '*/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => 'not json at all']],
            ],
        ], 200),
    ]);

    $this->actingAs($manager)
        ->postJson("/api/v1/whatsapp-call-flows/{$flow->uuid}/ai-generate", ['instruction' => 'Build something.'])
        ->assertUnprocessable();
});

it('returns a 422 when the ai response fails node validation', function () {
    $manager = actingAsCallFlowAiRole('manager');
    AiAssistantSetting::create([
        'company_id' => $manager->company_id,
        'base_url' => 'https://fake-llm.test/v1',
        'api_key' => 'test-key',
        'model' => 'gpt-test',
    ]);
    $flow = WhatsappCallFlow::factory()->create(['company_id' => $manager->company_id]);

    fakeAiGeneratedNodesResponse([
        ['id' => 'q1', 'type' => 'not_a_real_type', 'prompt' => 'Huh?'],
    ]);

    $this->actingAs($manager)
        ->postJson("/api/v1/whatsapp-call-flows/{$flow->uuid}/ai-generate", ['instruction' => 'Build something.'])
        ->assertUnprocessable();
});

it('returns a 422 when the ai assistant is not configured for the company', function () {
    $manager = actingAsCallFlowAiRole('manager');
    $flow = WhatsappCallFlow::factory()->create(['company_id' => $manager->company_id]);

    Http::fake();

    $this->actingAs($manager)
        ->postJson("/api/v1/whatsapp-call-flows/{$flow->uuid}/ai-generate", ['instruction' => 'Build something.'])
        ->assertUnprocessable();

    Http::assertNothingSent();
});

it('forbids an agent from generating call flow nodes via ai', function () {
    $agent = actingAsCallFlowAiRole('agent');
    $flow = WhatsappCallFlow::factory()->create(['company_id' => $agent->company_id]);

    $this->actingAs($agent)
        ->postJson("/api/v1/whatsapp-call-flows/{$flow->uuid}/ai-generate", ['instruction' => 'Build something.'])
        ->assertForbidden();
});
