<?php

use App\Jobs\SendCampaignMessage;
use App\Models\ApiConnection;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

function actingAsCampaignRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('rejects unauthenticated campaign listing', function () {
    $this->getJson('/api/v1/campaigns')->assertUnauthorized();
});

it('forbids an agent from viewing or creating campaigns', function () {
    Campaign::factory()->count(2)->create();
    $user = actingAsCampaignRole('agent');

    $this->actingAs($user)->getJson('/api/v1/campaigns')->assertForbidden();

    $this->actingAs($user)->postJson('/api/v1/campaigns', [
        'name' => 'Should fail',
        'channel' => 'whatsapp',
        'message' => 'Hi',
        'audienceTag' => 'VIP',
    ])->assertForbidden();
});

it('allows a manager to create a campaign and resolves recipient count from tagged contacts', function () {
    $tag = Tag::query()->create(['name' => 'VIP']);
    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $contact->tags()->attach($tag);
    Contact::factory()->create(['channel' => 'whatsapp']); // untagged, should not count

    $user = actingAsCampaignRole('manager');

    $response = $this->actingAs($user)->postJson('/api/v1/campaigns', [
        'name' => 'August Flash Sale',
        'channel' => 'whatsapp',
        'message' => 'Hello {{first_name}}',
        'audienceTag' => 'VIP',
    ]);

    $response->assertCreated();
    $campaign = Campaign::query()->first();
    expect($campaign->name)->toBe('August Flash Sale');
    expect($campaign->status)->toBe('active');
    expect($campaign->recipient_count)->toBe(1);

    // The response body is what the frontend actually renders immediately after
    // creation (before any refetch), so counts must not be null in-memory.
    $response->assertJsonPath('data.deliveredCount', 0);
    $response->assertJsonPath('data.readCount', 0);
    $response->assertJsonPath('data.repliedCount', 0);
});

it('creates a scheduled campaign when scheduledAt is provided', function () {
    $user = actingAsCampaignRole('admin');

    $response = $this->actingAs($user)->postJson('/api/v1/campaigns', [
        'name' => 'Future Campaign',
        'channel' => 'instagram',
        'message' => 'Hi there',
        'audienceTag' => 'New',
        'scheduledAt' => now()->addDay()->toIso8601String(),
    ]);

    $response->assertCreated();
    expect(Campaign::query()->first()->status)->toBe('scheduled');
});

it('allows deleting a campaign as admin', function () {
    $campaign = Campaign::factory()->create();
    $user = actingAsCampaignRole('admin');

    $this->actingAs($user)->deleteJson("/api/v1/campaigns/{$campaign->uuid}")->assertNoContent();

    expect(Campaign::query()->count())->toBe(0);
});

it('resolves audience fresh at dispatch time and queues a job per pending recipient', function () {
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

    expect(CampaignRecipient::query()->where('campaign_id', $campaign->id)->count())->toBe(1);
    expect($campaign->fresh()->status)->toBe('completed');
    Queue::assertPushed(SendCampaignMessage::class, 1);
});

it('does not create duplicate recipients when dispatched twice (idempotency)', function () {
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

    // Simulate a second run against the same campaign (dispatched_at now set, so
    // the outer query would normally skip it — reset it to prove the
    // recipient-level uniqueness holds even if dispatch runs again).
    $campaign->update(['dispatched_at' => null]);
    Artisan::call('campaigns:dispatch');

    expect(CampaignRecipient::query()->where('campaign_id', $campaign->id)->count())->toBe(1);
});

it('marks a campaign recipient failed when outside the 24-hour messaging window', function () {
    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp']);
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'sent_at' => now()->subHours(30),
    ]);

    $campaign = Campaign::factory()->create(['channel' => 'whatsapp', 'message' => 'Hello!']);
    $recipient = CampaignRecipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'status' => 'pending',
    ]);

    (new SendCampaignMessage($recipient->id))->handle(app(\App\Services\Messaging\MessagingDriverResolver::class));

    expect($recipient->fresh()->status)->toBe('failed');
    expect($recipient->fresh()->failure_reason)->not->toBeNull();
});

it('notifies campaign managers with campaign_failed when every recipient fails to send', function () {
    $manager = actingAsCampaignRole('manager');

    $tag = Tag::query()->create(['name' => 'VIP']);
    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $contact->tags()->attach($tag);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp']);
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'sent_at' => now()->subHours(30),
    ]);

    $campaign = Campaign::factory()->create([
        'channel' => 'whatsapp',
        'audience_tag' => 'VIP',
        'message' => 'Hello!',
        'status' => 'scheduled',
        'scheduled_at' => now()->subMinute(),
        'dispatched_at' => null,
    ]);

    Artisan::call('campaigns:dispatch');

    expect(CampaignRecipient::query()->where('campaign_id', $campaign->id)->first()->status)->toBe('failed');
    $this->assertDatabaseHas('notifications', [
        'user_id' => $manager->id,
        'type' => 'campaign_failed',
    ]);
});

it('sends a campaign message when inside the 24-hour messaging window', function () {
    Queue::fake();

    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp']);
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'sent_at' => now()->subHours(2),
    ]);

    $campaign = Campaign::factory()->create(['channel' => 'whatsapp', 'message' => 'Hello!']);
    $recipient = CampaignRecipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'status' => 'pending',
    ]);

    (new SendCampaignMessage($recipient->id))->handle(app(\App\Services\Messaging\MessagingDriverResolver::class));

    expect($recipient->fresh()->status)->toBe('sent');
    expect($recipient->fresh()->message_id)->not->toBeNull();

    $message = Message::query()->find($recipient->fresh()->message_id);
    expect($message->text)->toBe('Hello!');
    expect($message->direction)->toBe('outbound');
});

it('uses the real Graph API driver when the connection has a saved access token', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.real123']]], 200),
    ]);

    ApiConnection::factory()->connected()->create([
        'channel' => 'whatsapp',
        'phone_number_id' => 'phone-456',
    ]);

    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp']);
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'sent_at' => now()->subHours(2),
    ]);

    $campaign = Campaign::factory()->create(['channel' => 'whatsapp', 'message' => 'Hello!']);
    $recipient = CampaignRecipient::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'status' => 'pending',
    ]);

    (new SendCampaignMessage($recipient->id))->handle(app(\App\Services\Messaging\MessagingDriverResolver::class));

    expect($recipient->fresh()->status)->toBe('sent');

    $message = Message::query()->find($recipient->fresh()->message_id);
    expect($message->external_message_id)->toBe('wamid.real123');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'graph.facebook.com')
            && str_contains($request->url(), 'phone-456');
    });
});
