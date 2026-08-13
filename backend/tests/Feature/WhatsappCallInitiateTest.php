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

it('allows a manager to place an outbound call on a calling-enabled connection', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['calls' => [['id' => 'wacid.real-call-1']]], 200)]);

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
    expect($response->json('data.metaCallId'))->not->toBeNull();

    $call = WhatsappCall::query()->where('contact_id', $contact->id)->firstOrFail();
    expect($call->company_id)->toBe($manager->company_id);
    expect($call->meta_call_id)->toBe('wacid.real-call-1');
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
