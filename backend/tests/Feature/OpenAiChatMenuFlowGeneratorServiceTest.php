<?php

use App\Models\AiAssistantSetting;
use App\Models\Company;
use App\Services\ChatFlow\OpenAiChatMenuFlowGeneratorService;
use Illuminate\Support\Facades\Http;

function makeAiConfiguredCompany(): Company
{
    $company = Company::factory()->create();
    $setting = new AiAssistantSetting([
        'base_url' => 'https://api.openai.com/v1',
        'api_key' => 'test-key',
        'model' => 'gpt-4o-mini',
    ]);
    $setting->company_id = $company->id;
    $setting->save();

    return $company;
}

it('reports a clear error when no AI assistant is configured', function () {
    $company = Company::factory()->create();
    $service = new OpenAiChatMenuFlowGeneratorService;

    $draft = $service->generate($company, 'A catering menu');

    expect($draft)->toBeNull();
    expect($service->lastError())->toContain('No AI assistant is configured');
});

it('reports a clear error when the AI response is not valid JSON', function () {
    $company = makeAiConfiguredCompany();
    Http::fake([
        '*/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'not json at all']]],
        ]),
    ]);

    $service = new OpenAiChatMenuFlowGeneratorService;
    $draft = $service->generate($company, 'A catering menu');

    expect($draft)->toBeNull();
    expect($service->lastError())->toContain('not in the expected format');
});

it('unwraps a nested payload when the model wraps nodes under an extra key', function () {
    $company = makeAiConfiguredCompany();
    Http::fake([
        '*/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'flow' => [
                    'entryNodeId' => 'root',
                    'nodes' => [
                        [
                            'id' => 'root',
                            'type' => 'menu',
                            'message' => 'Welcome! Choose an option.',
                            'buttons' => [
                                ['id' => 'b1', 'label' => 'Restaurant', 'nextNodeId' => 'leaf'],
                            ],
                        ],
                        ['id' => 'leaf', 'type' => 'content', 'message' => 'Here is our menu.', 'buttons' => []],
                    ],
                ],
            ])]]],
        ]),
    ]);

    $service = new OpenAiChatMenuFlowGeneratorService;
    $draft = $service->generate($company, 'A restaurant menu');

    expect($draft)->not->toBeNull();
    expect($draft['nodes'])->toHaveCount(2);
});

it('truncates button labels and messages that exceed WhatsApp limits instead of dropping them', function () {
    $company = makeAiConfiguredCompany();
    $longLabel = 'This label is way too long for WhatsApp';
    $longMessage = str_repeat('a', 2000);

    Http::fake([
        '*/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'entryNodeId' => 'root',
                'nodes' => [
                    [
                        'id' => 'root',
                        'type' => 'menu',
                        'message' => $longMessage,
                        'buttons' => [
                            ['id' => 'b1', 'label' => $longLabel, 'nextNodeId' => 'leaf'],
                        ],
                    ],
                    ['id' => 'leaf', 'type' => 'content', 'message' => 'Done.', 'buttons' => []],
                ],
            ])]]],
        ]),
    ]);

    $service = new OpenAiChatMenuFlowGeneratorService;
    $draft = $service->generate($company, 'A menu');

    expect($draft)->not->toBeNull();
    $root = collect($draft['nodes'])->first(fn ($n) => count($n['buttons']) > 0);
    expect(strlen($root['buttons'][0]['label']))->toBeLessThanOrEqual(20);
    expect(strlen($root['message']))->toBeLessThanOrEqual(1024);
});
