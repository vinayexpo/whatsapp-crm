<?php

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\VoiceAgent;
use App\Models\VoiceCall;
use App\Services\Voice\VoiceCallFinalizer;
use Database\Seeders\PipelineStagesSeeder;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

it('moves the contact to the qualified pipeline stage when all criteria are filled', function () {
    $agent = VoiceAgent::factory()->create([
        'qualification_criteria' => [
            ['key' => 'budget', 'label' => 'Budget', 'question' => 'What is your budget?', 'type' => 'text'],
        ],
    ]);
    $contact = Contact::factory()->create(['pipeline_stage_id' => 'new-lead']);
    $voiceCall = VoiceCall::factory()->create([
        'voice_agent_id' => $agent->id,
        'contact_id' => $contact->id,
        'status' => 'completed',
        'qualification_fields' => ['budget' => '$5,000'],
        'qualification_outcome' => null,
    ]);

    app(VoiceCallFinalizer::class)->finalize($voiceCall);

    expect($contact->fresh()->pipeline_stage_id)->toBe('qualified');
    expect($voiceCall->fresh()->qualification_outcome)->toBe('qualified');
    expect($voiceCall->fresh()->needs_human_followup)->toBeFalse();
});

it('flags needs_human_followup when qualification criteria are incomplete', function () {
    $agent = VoiceAgent::factory()->create([
        'qualification_criteria' => [
            ['key' => 'budget', 'label' => 'Budget', 'question' => 'What is your budget?', 'type' => 'text'],
            ['key' => 'timeline', 'label' => 'Timeline', 'question' => 'When do you need this?', 'type' => 'text'],
        ],
    ]);
    $contact = Contact::factory()->create(['pipeline_stage_id' => 'new-lead']);
    $voiceCall = VoiceCall::factory()->create([
        'voice_agent_id' => $agent->id,
        'contact_id' => $contact->id,
        'status' => 'completed',
        'qualification_fields' => ['budget' => '$5,000'],
        'qualification_outcome' => null,
    ]);

    app(VoiceCallFinalizer::class)->finalize($voiceCall);

    expect($voiceCall->fresh()->qualification_outcome)->toBe('uncertain');
    expect($voiceCall->fresh()->needs_human_followup)->toBeTrue();
    expect($contact->fresh()->pipeline_stage_id)->toBe('new-lead');
});

it('creates a call-summary message in the linked conversation', function () {
    $agent = VoiceAgent::factory()->create([
        'qualification_criteria' => [
            ['key' => 'budget', 'label' => 'Budget', 'question' => 'What is your budget?', 'type' => 'text'],
        ],
    ]);
    $contact = Contact::factory()->create();
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'voice', 'unread_count' => 0]);
    $voiceCall = VoiceCall::factory()->create([
        'voice_agent_id' => $agent->id,
        'contact_id' => $contact->id,
        'conversation_id' => $conversation->id,
        'direction' => 'outbound',
        'status' => 'completed',
        'qualification_fields' => ['budget' => '$5,000'],
        'qualification_outcome' => null,
        'recording_url' => 'https://example.com/recording.mp3',
    ]);

    app(VoiceCallFinalizer::class)->finalize($voiceCall);

    $message = Message::query()->where('conversation_id', $conversation->id)->first();

    expect($message)->not->toBeNull();
    expect($message->direction)->toBe('outbound');
    expect($message->text)->toContain('Qualified');
    expect($message->attachment_url)->toBe('https://example.com/recording.mp3');
    expect($message->attachment_type)->toBe('audio');
    expect($conversation->fresh()->unread_count)->toBe(1);
});

it('creates a conversation for the call when none is linked yet', function () {
    $contact = Contact::factory()->create();
    $voiceCall = VoiceCall::factory()->create([
        'voice_agent_id' => null,
        'contact_id' => $contact->id,
        'conversation_id' => null,
        'status' => 'completed',
        'qualification_outcome' => 'not_qualified',
        'qualification_fields' => [],
    ]);

    app(VoiceCallFinalizer::class)->finalize($voiceCall);

    $conversation = Conversation::query()->where('contact_id', $contact->id)->where('channel', 'voice')->first();

    expect($conversation)->not->toBeNull();
    expect($voiceCall->fresh()->conversation_id)->toBe($conversation->id);
});

it('does not re-run judgment when qualification_outcome is already set', function () {
    $agent = VoiceAgent::factory()->create();
    $contact = Contact::factory()->create();
    $voiceCall = VoiceCall::factory()->create([
        'voice_agent_id' => $agent->id,
        'contact_id' => $contact->id,
        'status' => 'completed',
        'qualification_outcome' => 'qualified',
        'qualification_summary' => 'Already judged.',
        'qualification_fields' => ['budget' => '$5,000'],
    ]);

    app(VoiceCallFinalizer::class)->finalize($voiceCall);

    expect($voiceCall->fresh()->qualification_summary)->toBe('Already judged.');
});
