<?php

use App\Jobs\InitiateOutboundWhatsappCall;
use App\Jobs\SendCampaignMessage;
use App\Models\ApiConnection;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;
use App\Models\VoiceAgent;
use App\Models\WhatsappCall;
use App\Models\WhatsappCallFlow;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PipelineStagesSeeder::class);
});

function actingAsCampaignManager(): User
{
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('manager');

    return $user;
}

it('dispatches InitiateOutboundWhatsappCall instead of SendCampaignMessage for a call-flow-linked campaign', function () {
    Queue::fake();

    $tag = Tag::query()->create(['name' => 'VIP']);
    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $contact->tags()->attach($tag);

    $callFlow = WhatsappCallFlow::factory()->create(['status' => 'active']);

    $campaign = Campaign::factory()->create([
        'audience_tag' => 'VIP',
        'status' => 'scheduled',
        'scheduled_at' => now()->subMinute(),
        'dispatched_at' => null,
        'whatsapp_call_flow_id' => $callFlow->id,
    ]);

    Artisan::call('campaigns:dispatch');

    expect(CampaignRecipient::query()->where('campaign_id', $campaign->id)->count())->toBe(1);
    expect($campaign->fresh()->status)->toBe('completed');

    Queue::assertPushed(InitiateOutboundWhatsappCall::class, function ($job) use ($callFlow) {
        return $job->whatsappCallFlowId === $callFlow->id;
    });
    Queue::assertNotPushed(SendCampaignMessage::class);
});

it('creates a WhatsappCall row with campaign_recipient_id set when the job runs', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['calls' => [['id' => 'fake-meta-call-id']]], 200),
    ]);

    $tag = Tag::query()->create(['name' => 'VIP']);
    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $contact->tags()->attach($tag);

    $connection = ApiConnection::factory()->connected()->create([
        'channel' => 'whatsapp',
        'calling_enabled' => true,
    ]);

    $callFlow = WhatsappCallFlow::factory()->create([
        'status' => 'active',
        'api_connection_id' => $connection->id,
    ]);

    $campaign = Campaign::factory()->create([
        'audience_tag' => 'VIP',
        'whatsapp_call_flow_id' => $callFlow->id,
    ]);

    $recipient = CampaignRecipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'status' => 'pending',
    ]);

    (new InitiateOutboundWhatsappCall($recipient->id, $callFlow->id))
        ->handle(app(\App\Services\Calling\WhatsappCallDriverResolver::class));

    $call = WhatsappCall::query()->where('campaign_recipient_id', $recipient->id)->first();
    expect($call)->not->toBeNull();
    expect($call->contact_id)->toBe($contact->id);
    expect($recipient->fresh()->status)->toBe('sent');
});

it('marks the recipient failed when no calling-enabled WhatsApp connection exists', function () {
    $tag = Tag::query()->create(['name' => 'VIP']);
    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $contact->tags()->attach($tag);

    $callFlow = WhatsappCallFlow::factory()->create(['status' => 'active']);

    $campaign = Campaign::factory()->create([
        'audience_tag' => 'VIP',
        'whatsapp_call_flow_id' => $callFlow->id,
    ]);

    $recipient = CampaignRecipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'status' => 'pending',
    ]);

    (new InitiateOutboundWhatsappCall($recipient->id, $callFlow->id))
        ->handle(app(\App\Services\Calling\WhatsappCallDriverResolver::class));

    expect($recipient->fresh()->status)->toBe('failed');
    expect($recipient->fresh()->failure_reason)->not->toBeNull();

    $call = WhatsappCall::query()->where('campaign_recipient_id', $recipient->id)->first();
    expect($call->status)->toBe('failed');
});

it('rejects creating a campaign with both voiceAgentId and whatsappCallFlowId', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = actingAsCampaignManager();

    $voiceAgent = VoiceAgent::factory()->create(['company_id' => $user->company_id]);
    $callFlow = WhatsappCallFlow::factory()->create(['company_id' => $user->company_id]);

    $response = $this->actingAs($user)->postJson('/api/v1/campaigns', [
        'name' => 'Bad campaign',
        'audienceTag' => 'VIP',
        'voiceAgentId' => $voiceAgent->uuid,
        'whatsappCallFlowId' => $callFlow->uuid,
    ]);

    $response->assertUnprocessable();
});

it('creates a voice-agent campaign without requiring a message or channel', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = actingAsCampaignManager();

    $voiceAgent = VoiceAgent::factory()->create(['company_id' => $user->company_id]);

    $response = $this->actingAs($user)->postJson('/api/v1/campaigns', [
        'name' => 'Voice outreach',
        'audienceTag' => 'VIP',
        'voiceAgentId' => $voiceAgent->uuid,
    ]);

    $response->assertCreated();
    $campaign = Campaign::query()->first();
    expect($campaign->voice_agent_id)->toBe($voiceAgent->id);
    $response->assertJsonPath('data.voiceAgentId', $voiceAgent->uuid);
});

it('creates a whatsapp-call-flow campaign without requiring a message or channel', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = actingAsCampaignManager();

    $callFlow = WhatsappCallFlow::factory()->create(['company_id' => $user->company_id]);

    $response = $this->actingAs($user)->postJson('/api/v1/campaigns', [
        'name' => 'WhatsApp call outreach',
        'audienceTag' => 'VIP',
        'whatsappCallFlowId' => $callFlow->uuid,
    ]);

    $response->assertCreated();
    $campaign = Campaign::query()->first();
    expect($campaign->whatsapp_call_flow_id)->toBe($callFlow->id);
    $response->assertJsonPath('data.whatsappCallFlowId', $callFlow->uuid);
});

it('rejects a voiceAgentId belonging to another company', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = actingAsCampaignManager();

    $otherCompany = Company::factory()->create();
    $otherVoiceAgent = VoiceAgent::factory()->create(['company_id' => $otherCompany->id]);

    $response = $this->actingAs($user)->postJson('/api/v1/campaigns', [
        'name' => 'Cross-tenant attempt',
        'audienceTag' => 'VIP',
        'voiceAgentId' => $otherVoiceAgent->uuid,
    ]);

    $response->assertUnprocessable();
});

it('rejects a whatsappCallFlowId belonging to another company', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = actingAsCampaignManager();

    $otherCompany = Company::factory()->create();
    $otherCallFlow = WhatsappCallFlow::factory()->create(['company_id' => $otherCompany->id]);

    $response = $this->actingAs($user)->postJson('/api/v1/campaigns', [
        'name' => 'Cross-tenant attempt',
        'audienceTag' => 'VIP',
        'whatsappCallFlowId' => $otherCallFlow->uuid,
    ]);

    $response->assertUnprocessable();
});
