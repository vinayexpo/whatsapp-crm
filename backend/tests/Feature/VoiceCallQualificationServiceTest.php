<?php

use App\Models\VoiceAgent;
use App\Models\VoiceCall;
use App\Services\Voice\FakeVoiceCallQualificationService;
use App\Services\Voice\VoiceCallQualificationServiceInterface;
use Database\Seeders\PipelineStagesSeeder;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

it('resolves the fake qualification service in the testing environment', function () {
    expect(app(VoiceCallQualificationServiceInterface::class))->toBeInstanceOf(FakeVoiceCallQualificationService::class);
});

it('returns the next unanswered qualification question in order', function () {
    $agent = VoiceAgent::factory()->create([
        'qualification_criteria' => [
            ['key' => 'budget', 'label' => 'Budget', 'question' => "What's your budget?", 'type' => 'text'],
            ['key' => 'timeline', 'label' => 'Timeline', 'question' => 'When do you need this?', 'type' => 'text'],
        ],
    ]);
    $call = VoiceCall::factory()->create(['qualification_fields' => null]);

    $service = new FakeVoiceCallQualificationService;

    expect($service->nextQuestion($agent, $call))->toBe("What's your budget?");

    $call->qualification_fields = ['budget' => '$5,000'];

    expect($service->nextQuestion($agent, $call))->toBe('When do you need this?');
});

it('returns null once all qualification criteria are filled', function () {
    $agent = VoiceAgent::factory()->create([
        'qualification_criteria' => [
            ['key' => 'budget', 'label' => 'Budget', 'question' => "What's your budget?", 'type' => 'text'],
        ],
    ]);
    $call = VoiceCall::factory()->create(['qualification_fields' => ['budget' => '$5,000']]);

    $service = new FakeVoiceCallQualificationService;

    expect($service->nextQuestion($agent, $call))->toBeNull();
});

it('judges a call qualified when all criteria fields are filled', function () {
    $agent = VoiceAgent::factory()->create([
        'qualification_criteria' => [
            ['key' => 'budget', 'label' => 'Budget', 'question' => "What's your budget?", 'type' => 'text'],
            ['key' => 'timeline', 'label' => 'Timeline', 'question' => 'When?', 'type' => 'text'],
        ],
    ]);
    $call = VoiceCall::factory()->create([
        'qualification_fields' => ['budget' => '$5,000', 'timeline' => '1 month'],
    ]);

    $judgment = (new FakeVoiceCallQualificationService)->judge($agent, $call);

    expect($judgment['outcome'])->toBe('qualified');
    expect($judgment['fields'])->toBe(['budget' => '$5,000', 'timeline' => '1 month']);
});

it('judges a call uncertain when some criteria fields are missing', function () {
    $agent = VoiceAgent::factory()->create([
        'qualification_criteria' => [
            ['key' => 'budget', 'label' => 'Budget', 'question' => "What's your budget?", 'type' => 'text'],
            ['key' => 'timeline', 'label' => 'Timeline', 'question' => 'When?', 'type' => 'text'],
        ],
    ]);
    $call = VoiceCall::factory()->create([
        'qualification_fields' => ['budget' => '$5,000'],
    ]);

    $judgment = (new FakeVoiceCallQualificationService)->judge($agent, $call);

    expect($judgment['outcome'])->toBe('uncertain');
});

it('judges a call uncertain when there are no qualification criteria at all', function () {
    $agent = VoiceAgent::factory()->create(['qualification_criteria' => []]);
    $call = VoiceCall::factory()->create(['qualification_fields' => null]);

    $judgment = (new FakeVoiceCallQualificationService)->judge($agent, $call);

    expect($judgment['outcome'])->toBe('uncertain');
});
