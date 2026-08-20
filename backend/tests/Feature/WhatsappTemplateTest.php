<?php

use App\Jobs\SendCampaignMessage;
use App\Models\ApiConnection;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

function actingAsTemplateRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('rejects unauthenticated template listing and sync', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);

    $this->getJson("/api/v1/api-connections/{$connection->uuid}/templates")->assertUnauthorized();
    $this->postJson("/api/v1/api-connections/{$connection->uuid}/templates/sync")->assertUnauthorized();
});

it('forbids an agent from syncing templates', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    $user = actingAsTemplateRole('agent');

    $this->actingAs($user)
        ->postJson("/api/v1/api-connections/{$connection->uuid}/templates/sync")
        ->assertForbidden();
});

it('allows a manager to sync templates from the fake Meta driver and upserts them', function () {
    config(['services.meta.whatsapp_driver' => 'fake']);

    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    $user = actingAsTemplateRole('manager');

    $response = $this->actingAs($user)->postJson("/api/v1/api-connections/{$connection->uuid}/templates/sync");

    $response->assertOk();
    expect(WhatsappTemplate::query()->where('api_connection_id', $connection->id)->count())->toBe(3);

    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toContain('order_confirmation');

    // Syncing again should upsert, not duplicate.
    $this->actingAs($user)->postJson("/api/v1/api-connections/{$connection->uuid}/templates/sync")->assertOk();
    expect(WhatsappTemplate::query()->where('api_connection_id', $connection->id)->count())->toBe(3);
});

it('rejects syncing templates for a non-whatsapp connection', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'instagram']);
    $user = actingAsTemplateRole('admin');

    $this->actingAs($user)
        ->postJson("/api/v1/api-connections/{$connection->uuid}/templates/sync")
        ->assertUnprocessable();
});

it('lists synced templates for a connection', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    WhatsappTemplate::factory()->create(['api_connection_id' => $connection->id, 'name' => 'welcome_message']);
    $user = actingAsTemplateRole('manager');

    $response = $this->actingAs($user)->getJson("/api/v1/api-connections/{$connection->uuid}/templates");

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('name')->all())->toContain('welcome_message');
});

it('filters templates by status', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    WhatsappTemplate::factory()->create(['api_connection_id' => $connection->id, 'status' => 'approved']);
    WhatsappTemplate::factory()->create(['api_connection_id' => $connection->id, 'status' => 'pending']);
    $user = actingAsTemplateRole('manager');

    $response = $this->actingAs($user)->getJson("/api/v1/api-connections/{$connection->uuid}/templates?status=pending");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.status'))->toBe('pending');
});

it('filters templates by search matching name', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    WhatsappTemplate::factory()->create(['api_connection_id' => $connection->id, 'name' => 'welcome_message']);
    WhatsappTemplate::factory()->create(['api_connection_id' => $connection->id, 'name' => 'order_confirmation']);
    $user = actingAsTemplateRole('manager');

    $response = $this->actingAs($user)->getJson("/api/v1/api-connections/{$connection->uuid}/templates?search=welcome");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('welcome_message');
});

it('creates a campaign linked to a template with variables', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    $template = WhatsappTemplate::factory()->create([
        'api_connection_id' => $connection->id,
        'name' => 'order_confirmation',
        'body' => 'Hi {{1}}, your order {{2}} is confirmed.',
        'variables' => ['1', '2'],
    ]);
    $user = actingAsTemplateRole('manager');

    $response = $this->actingAs($user)->postJson('/api/v1/campaigns', [
        'name' => 'Order Confirmations',
        'channel' => 'whatsapp',
        'message' => 'Hi {{1}}, your order {{2}} is confirmed.',
        'audienceTag' => 'VIP',
        'templateId' => $template->uuid,
        'templateVariables' => ['1' => 'Amara', '2' => '#1234'],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.templateId', $template->uuid);

    $campaign = Campaign::query()->first();
    expect($campaign->whatsapp_template_id)->toBe($template->id);
    expect($campaign->template_variables)->toBe(['1' => 'Amara', '2' => '#1234']);
});

it('rejects campaign creation with an unknown templateId', function () {
    $user = actingAsTemplateRole('manager');

    $this->actingAs($user)->postJson('/api/v1/campaigns', [
        'name' => 'Broken Campaign',
        'channel' => 'whatsapp',
        'message' => 'Hi',
        'audienceTag' => 'VIP',
        'templateId' => 'not-a-real-uuid',
    ])->assertUnprocessable();
});

it('sends a template-rendered message outside the 24-hour window when a template is linked', function () {
    \Illuminate\Support\Facades\Queue::fake();

    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp']);
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'sent_at' => now()->subHours(30),
    ]);

    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    $template = WhatsappTemplate::factory()->create([
        'api_connection_id' => $connection->id,
        'body' => 'Hi {{1}}, your order {{2}} is confirmed.',
        'variables' => ['1', '2'],
    ]);

    $campaign = Campaign::factory()->create([
        'channel' => 'whatsapp',
        'message' => 'fallback text',
        'whatsapp_template_id' => $template->id,
        'template_variables' => ['1' => 'Amara', '2' => '#1234'],
    ]);
    $recipient = CampaignRecipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'status' => 'pending',
    ]);

    (new SendCampaignMessage($recipient->id))->handle(app(\App\Services\Messaging\MessagingDriverResolver::class));

    expect($recipient->fresh()->status)->toBe('sent');

    $message = Message::query()->find($recipient->fresh()->message_id);
    expect($message->text)->toBe('Hi Amara, your order #1234 is confirmed.');
});
