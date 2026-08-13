<?php

use App\Jobs\InitiateOutboundVoiceCall;
use App\Jobs\SendCampaignMessage;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\VoiceAgent;
use Database\Seeders\PipelineStagesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

it('dispatches InitiateOutboundVoiceCall instead of SendCampaignMessage for a voice-agent-linked campaign', function () {
    Queue::fake();

    $tag = Tag::query()->create(['name' => 'VIP']);
    $contact = Contact::factory()->create(['channel' => 'voice']);
    $contact->tags()->attach($tag);

    $voiceAgent = VoiceAgent::factory()->create(['status' => 'active']);

    $campaign = Campaign::factory()->create([
        'channel' => 'voice',
        'audience_tag' => 'VIP',
        'status' => 'scheduled',
        'scheduled_at' => now()->subMinute(),
        'dispatched_at' => null,
        'voice_agent_id' => $voiceAgent->id,
    ]);

    Artisan::call('campaigns:dispatch');

    expect(CampaignRecipient::query()->where('campaign_id', $campaign->id)->count())->toBe(1);
    expect($campaign->fresh()->status)->toBe('completed');

    Queue::assertPushed(InitiateOutboundVoiceCall::class, function ($job) use ($voiceAgent) {
        return $job->voiceAgentId === $voiceAgent->id;
    });
    Queue::assertNotPushed(SendCampaignMessage::class);
});

it('dispatches SendCampaignMessage as usual for a campaign with no voice agent', function () {
    Queue::fake();

    $tag = Tag::query()->create(['name' => 'VIP']);
    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $contact->tags()->attach($tag);

    $campaign = Campaign::factory()->create([
        'channel' => 'whatsapp',
        'audience_tag' => 'VIP',
        'status' => 'scheduled',
        'scheduled_at' => now()->subMinute(),
        'dispatched_at' => null,
    ]);

    Artisan::call('campaigns:dispatch');

    Queue::assertPushed(SendCampaignMessage::class, 1);
    Queue::assertNotPushed(InitiateOutboundVoiceCall::class);
});
