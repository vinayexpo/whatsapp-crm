<?php

use App\Models\ApiConnection;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use App\Models\WhatsappCall;
use App\Models\WhatsappCallFlow;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

function actingAsInitiateCallRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('rejects unauthenticated call initiation', function () {
    $contact = Contact::factory()->create();

    $this->postJson('/api/v1/whatsapp-calls', ['contactId' => $contact->uuid])
        ->assertUnauthorized();
});

it('forbids an agent from initiating a call', function () {
    $agent = actingAsInitiateCallRole('agent');
    $contact = Contact::factory()->create(['company_id' => $agent->company_id]);

    $this->actingAs($agent)->postJson('/api/v1/whatsapp-calls', ['contactId' => $contact->uuid])
        ->assertForbidden();
});

it('allows a manager to create a call row without placing the call yet', function () {
    $manager = actingAsInitiateCallRole('manager');
    ApiConnection::factory()->connected()->create([
        'company_id' => $manager->company_id,
        'channel' => 'whatsapp',
        'calling_enabled' => true,
    ]);
    $contact = Contact::factory()->create(['company_id' => $manager->company_id]);

    $response = $this->actingAs($manager)->postJson('/api/v1/whatsapp-calls', [
        'contactId' => $contact->uuid,
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.direction', 'outbound');
    $response->assertJsonPath('data.contactId', $contact->uuid);
    $response->assertJsonPath('data.status', 'ringing');
    $response->assertJsonPath('data.sdpExchangeStatus', 'pending_offer');
    expect($response->json('data.metaCallId'))->toBeNull();

    $call = WhatsappCall::query()->where('contact_id', $contact->id)->firstOrFail();
    expect($call->company_id)->toBe($manager->company_id);
    expect($call->meta_call_id)->toBeNull();
});

it('places the real call once the browser submits its SDP offer', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['calls' => [['id' => 'wacid.real-call-1']]], 200)]);

    $manager = actingAsInitiateCallRole('manager');
    ApiConnection::factory()->connected()->create([
        'company_id' => $manager->company_id,
        'channel' => 'whatsapp',
        'calling_enabled' => true,
    ]);
    $contact = Contact::factory()->create(['company_id' => $manager->company_id]);
    $call = WhatsappCall::factory()->create([
        'company_id' => $manager->company_id,
        'contact_id' => $contact->id,
        'direction' => 'outbound',
        'status' => 'ringing',
    ]);

    $response = $this->actingAs($manager)->postJson("/api/v1/whatsapp-calls/{$call->uuid}/offer", [
        'sdpOffer' => 'v=0...fake-offer-sdp',
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.metaCallId', 'wacid.real-call-1');
    $response->assertJsonPath('data.sdpExchangeStatus', 'offer_sent');

    $call->refresh();
    expect($call->meta_call_id)->toBe('wacid.real-call-1');
    expect($call->local_sdp_offer)->toBe('v=0...fake-offer-sdp');
});

it('surfaces the real Meta error when the SDP offer is rejected', function () {
    Http::fake(['graph.facebook.com/*' => Http::response([
        'error' => [
            'message' => 'Missing session parameter',
            'code' => 131009,
            'error_subcode' => 2494010,
        ],
    ], 400)]);

    $manager = actingAsInitiateCallRole('manager');
    ApiConnection::factory()->connected()->create([
        'company_id' => $manager->company_id,
        'channel' => 'whatsapp',
        'calling_enabled' => true,
    ]);
    $contact = Contact::factory()->create(['company_id' => $manager->company_id]);
    $call = WhatsappCall::factory()->create([
        'company_id' => $manager->company_id,
        'contact_id' => $contact->id,
        'direction' => 'outbound',
        'status' => 'ringing',
    ]);

    $response = $this->actingAs($manager)->postJson("/api/v1/whatsapp-calls/{$call->uuid}/offer", [
        'sdpOffer' => 'v=0...fake-offer-sdp',
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors.sdpOffer.0'))->toContain('Missing session parameter');
    expect($response->json('errors.sdpOffer.0'))->toContain('131009/2494010');

    $call->refresh();
    expect($call->status)->toBe('failed');
    expect($call->sdp_exchange_status)->toBe('failed');
});

it('hangs up an in-progress call', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['success' => true], 200)]);

    $manager = actingAsInitiateCallRole('manager');
    ApiConnection::factory()->connected()->create([
        'company_id' => $manager->company_id,
        'channel' => 'whatsapp',
        'calling_enabled' => true,
    ]);
    $contact = Contact::factory()->create(['company_id' => $manager->company_id]);
    $call = WhatsappCall::factory()->create([
        'company_id' => $manager->company_id,
        'contact_id' => $contact->id,
        'direction' => 'outbound',
        'status' => 'in_progress',
        'meta_call_id' => 'wacid.real-call-4',
    ]);

    $response = $this->actingAs($manager)->postJson("/api/v1/whatsapp-calls/{$call->uuid}/hangup");

    $response->assertOk();
    $response->assertJsonPath('data.status', 'completed');

    $call->refresh();
    expect($call->status)->toBe('completed');
    expect($call->ended_at)->not->toBeNull();
});

it('associates the call with a conversation and call flow when provided', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['calls' => [['id' => 'wacid.real-call-2']]], 200)]);

    $manager = actingAsInitiateCallRole('manager');
    $connection = ApiConnection::factory()->connected()->create([
        'company_id' => $manager->company_id,
        'channel' => 'whatsapp',
        'calling_enabled' => true,
    ]);
    $contact = Contact::factory()->create(['company_id' => $manager->company_id]);
    $conversation = Conversation::factory()->create(['company_id' => $manager->company_id, 'contact_id' => $contact->id]);
    $callFlow = WhatsappCallFlow::factory()->create(['company_id' => $manager->company_id, 'api_connection_id' => $connection->id]);

    $response = $this->actingAs($manager)->postJson('/api/v1/whatsapp-calls', [
        'contactId' => $contact->uuid,
        'conversationId' => $conversation->uuid,
        'callFlowId' => $callFlow->uuid,
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.conversationId', $conversation->uuid);
    $response->assertJsonPath('data.callFlowId', $callFlow->uuid);
});

it('rejects initiating a call when no whatsapp connection has calling enabled', function () {
    $manager = actingAsInitiateCallRole('manager');
    $contact = Contact::factory()->create(['company_id' => $manager->company_id]);

    $response = $this->actingAs($manager)->postJson('/api/v1/whatsapp-calls', [
        'contactId' => $contact->uuid,
    ]);

    $response->assertUnprocessable();
    expect(WhatsappCall::query()->where('contact_id', $contact->id)->exists())->toBeFalse();
});

it('404s when initiating a call for a contact belonging to another company', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['calls' => [['id' => 'wacid.real-call-3']]], 200)]);

    $manager = actingAsInitiateCallRole('manager');
    ApiConnection::factory()->connected()->create([
        'company_id' => $manager->company_id,
        'channel' => 'whatsapp',
        'calling_enabled' => true,
    ]);
    $otherContact = Contact::factory()->create(['company_id' => Company::factory()]);

    $this->actingAs($manager)->postJson('/api/v1/whatsapp-calls', ['contactId' => $otherContact->uuid])
        ->assertNotFound();
});

it('filters whatsapp calls by contactId and conversationId', function () {
    $manager = actingAsInitiateCallRole('manager');
    $contactA = Contact::factory()->create(['company_id' => $manager->company_id]);
    $contactB = Contact::factory()->create(['company_id' => $manager->company_id]);
    $conversation = Conversation::factory()->create(['company_id' => $manager->company_id, 'contact_id' => $contactA->id]);

    WhatsappCall::factory()->create(['company_id' => $manager->company_id, 'contact_id' => $contactA->id, 'conversation_id' => $conversation->id]);
    WhatsappCall::factory()->create(['company_id' => $manager->company_id, 'contact_id' => $contactB->id]);

    $byContact = $this->actingAs($manager)->getJson("/api/v1/whatsapp-calls?contactId={$contactA->uuid}");
    $byContact->assertOk();
    expect($byContact->json('data'))->toHaveCount(1);
    $byContact->assertJsonPath('data.0.contactId', $contactA->uuid);

    $byConversation = $this->actingAs($manager)->getJson("/api/v1/whatsapp-calls?conversationId={$conversation->uuid}");
    $byConversation->assertOk();
    expect($byConversation->json('data'))->toHaveCount(1);
    $byConversation->assertJsonPath('data.0.conversationId', $conversation->uuid);
});

it('falls back to a STUN-only ICE server list when Twilio credentials are not configured', function () {
    config(['services.twilio.account_sid' => null, 'services.twilio.auth_token' => null]);
    $manager = actingAsInitiateCallRole('manager');

    $response = $this->actingAs($manager)->getJson('/api/v1/whatsapp-calls/ice-servers');

    $response->assertOk();
    expect($response->json('data.iceServers'))->toBe([['urls' => ['stun:stun.l.google.com:19302']]]);
});

it('returns Twilio TURN credentials as ICE servers when configured', function () {
    config(['services.twilio.account_sid' => 'ACtest', 'services.twilio.auth_token' => 'secret']);
    $manager = actingAsInitiateCallRole('manager');

    Http::fake([
        'api.twilio.com/*' => Http::response([
            'ice_servers' => [
                ['url' => 'stun:global.stun.twilio.com:3478'],
                ['url' => 'turn:global.turn.twilio.com:3478?transport=udp', 'username' => 'user1', 'credential' => 'cred1'],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($manager)->getJson('/api/v1/whatsapp-calls/ice-servers');

    $response->assertOk();
    expect($response->json('data.iceServers'))->toHaveCount(2);
    $response->assertJsonPath('data.iceServers.1.username', 'user1');
});

it('falls back to STUN-only when the Twilio TURN request fails', function () {
    config(['services.twilio.account_sid' => 'ACtest', 'services.twilio.auth_token' => 'secret']);
    $manager = actingAsInitiateCallRole('manager');

    Http::fake([
        'api.twilio.com/*' => Http::response(['message' => 'Unauthorized'], 401),
    ]);

    $response = $this->actingAs($manager)->getJson('/api/v1/whatsapp-calls/ice-servers');

    $response->assertOk();
    expect($response->json('data.iceServers'))->toBe([['urls' => ['stun:stun.l.google.com:19302']]]);
});
