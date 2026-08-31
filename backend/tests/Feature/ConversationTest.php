<?php

use App\Models\ApiConnection;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

function actingAsConversationRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('lists conversations ordered by last message time', function () {
    $older = Conversation::factory()->create(['last_message_at' => now()->subDay()]);
    $newer = Conversation::factory()->create(['last_message_at' => now()]);
    $user = actingAsConversationRole('manager');

    $response = $this->actingAs($user)->getJson('/api/v1/conversations');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids->first())->toBe($newer->uuid);
    expect($ids->last())->toBe($older->uuid);
});

it('rejects unauthenticated conversation listing', function () {
    $this->getJson('/api/v1/conversations')->assertUnauthorized();
});

it('paginates conversation listing', function () {
    Conversation::factory()->count(3)->create();
    $user = actingAsConversationRole('manager');

    $response = $this->actingAs($user)->getJson('/api/v1/conversations?per_page=1');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('meta.last_page'))->toBe(3);
    expect($response->json('meta.total'))->toBe(3);
});

it('filters conversation listing by contactId', function () {
    $target = Conversation::factory()->create();
    Conversation::factory()->count(3)->create();
    $user = actingAsConversationRole('manager');

    $response = $this->actingAs($user)->getJson('/api/v1/conversations?contactId='.$target->contact->uuid);

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids->all())->toBe([$target->uuid]);
});

it('filters conversation listing by contact search term', function () {
    $target = Conversation::factory()->for(Contact::factory()->state(['name' => 'Vinay Kumar']), 'contact')->create();
    Conversation::factory()->for(Contact::factory()->state(['name' => 'Someone Else']), 'contact')->create();
    $user = actingAsConversationRole('manager');

    $response = $this->actingAs($user)->getJson('/api/v1/conversations?search=vinay');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids->all())->toBe([$target->uuid]);
});

it('filters conversation listing by channel', function () {
    $target = Conversation::factory()->create(['channel' => 'website']);
    Conversation::factory()->create(['channel' => 'whatsapp']);
    $user = actingAsConversationRole('manager');

    $response = $this->actingAs($user)->getJson('/api/v1/conversations?channel=website');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids->all())->toBe([$target->uuid]);
});

it('filters conversation listing by status', function () {
    $target = Conversation::factory()->create(['status' => 'pending']);
    Conversation::factory()->create(['status' => 'open']);
    $user = actingAsConversationRole('manager');

    $response = $this->actingAs($user)->getJson('/api/v1/conversations?status=pending');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids->all())->toBe([$target->uuid]);
});

it('lists messages for a conversation in chronological order', function () {
    $conversation = Conversation::factory()->create();
    $first = Message::factory()->create(['conversation_id' => $conversation->id, 'sent_at' => now()->subHour()]);
    $second = Message::factory()->create(['conversation_id' => $conversation->id, 'sent_at' => now()]);
    $user = actingAsConversationRole('agent');

    $response = $this->actingAs($user)->getJson("/api/v1/conversations/{$conversation->uuid}/messages");

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids->toArray())->toBe([$first->uuid, $second->uuid]);
});

it('caps the initial messages load with limit and reports hasMore', function () {
    $conversation = Conversation::factory()->create();
    foreach (range(1, 5) as $i) {
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sent_at' => now()->subMinutes(5 - $i),
        ]);
    }
    $user = actingAsConversationRole('agent');

    $response = $this->actingAs($user)->getJson("/api/v1/conversations/{$conversation->uuid}/messages?limit=3");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
    expect($response->json('meta.hasMore'))->toBeTrue();

    // returned page should be the 3 most recent messages, oldest-first
    $ids = collect($response->json('data'))->pluck('id')->toArray();
    $expectedIds = $conversation->messages()->orderByDesc('sent_at')->limit(3)->pluck('uuid')->reverse()->values()->toArray();
    expect($ids)->toBe($expectedIds);
});

it('paginates older messages via the before cursor with no duplicates or gaps', function () {
    $conversation = Conversation::factory()->create();
    $messages = collect(range(1, 5))->map(fn ($i) => Message::factory()->create([
        'conversation_id' => $conversation->id,
        'sent_at' => now()->subMinutes(5 - $i),
    ]));
    $user = actingAsConversationRole('agent');

    $initial = $this->actingAs($user)
        ->getJson("/api/v1/conversations/{$conversation->uuid}/messages?limit=2")
        ->assertOk();
    expect($initial->json('meta.hasMore'))->toBeTrue();

    $oldestLoadedId = $initial->json('data.0.id');

    $older = $this->actingAs($user)
        ->getJson("/api/v1/conversations/{$conversation->uuid}/messages?before={$oldestLoadedId}&limit=2")
        ->assertOk();

    $olderIds = collect($older->json('data'))->pluck('id')->toArray();
    $initialIds = collect($initial->json('data'))->pluck('id')->toArray();

    expect(array_intersect($olderIds, $initialIds))->toBeEmpty();
    expect($older->json('meta.hasMore'))->toBeTrue();

    $oldestOlderId = $older->json('data.0.id');
    $lastPage = $this->actingAs($user)
        ->getJson("/api/v1/conversations/{$conversation->uuid}/messages?before={$oldestOlderId}&limit=2")
        ->assertOk();

    expect($lastPage->json('meta.hasMore'))->toBeFalse();
});

it('allows an agent to send an outbound message', function () {
    $conversation = Conversation::factory()->create();
    $user = actingAsConversationRole('agent');

    $response = $this->actingAs($user)->postJson("/api/v1/conversations/{$conversation->uuid}/messages", [
        'text' => 'Thanks for reaching out!',
    ]);

    $response->assertCreated();
    expect(Message::query()->where('conversation_id', $conversation->id)->count())->toBe(1);

    $message = Message::query()->where('conversation_id', $conversation->id)->first();
    expect($message->direction)->toBe('outbound');
    expect($message->text)->toBe('Thanks for reaching out!');
    expect($message->external_message_id)->not->toBeNull();
    expect($conversation->fresh()->last_message_at)->not->toBeNull();
});

it('validates the message text is required', function () {
    $conversation = Conversation::factory()->create();
    $user = actingAsConversationRole('agent');

    $this->actingAs($user)->postJson("/api/v1/conversations/{$conversation->uuid}/messages", [])
        ->assertUnprocessable();
});

it('allows an agent to send a message with an attachment and no text', function () {
    Illuminate\Support\Facades\Storage::fake('public');

    $conversation = Conversation::factory()->create();
    $user = actingAsConversationRole('agent');

    $file = Illuminate\Http\UploadedFile::fake()->image('photo.jpg');

    $response = $this->actingAs($user)->postJson("/api/v1/conversations/{$conversation->uuid}/messages", [
        'attachmentFile' => $file,
    ]);

    $response->assertCreated();

    $message = Message::query()->where('conversation_id', $conversation->id)->first();
    expect($message->attachment_type)->toBe('image');
    expect($message->attachment_url)->not->toBeNull();
});

it('sends an outbound attachment to Meta via the two-step media upload instead of a link', function () {
    Illuminate\Support\Facades\Storage::fake('public');

    $connection = ApiConnection::factory()->connected()->create([
        'channel' => 'whatsapp',
        'phone_number_id' => '1234567890',
    ]);
    $conversation = Conversation::factory()->create([
        'company_id' => $connection->company_id,
        'channel' => 'whatsapp',
    ]);
    $user = actingAsConversationRole('agent');

    Http::fake([
        'graph.facebook.com/*/media' => Http::response(['id' => 'media-abc123'], 200),
        'graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.SENT1']]], 200),
    ]);

    $file = Illuminate\Http\UploadedFile::fake()->image('photo.jpg');

    $response = $this->actingAs($user)->postJson("/api/v1/conversations/{$conversation->uuid}/messages", [
        'attachmentFile' => $file,
    ]);

    $response->assertCreated();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/media'));
    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/messages')) {
            return false;
        }

        return $request['type'] === 'image'
            && $request['image']['id'] === 'media-abc123'
            && ! isset($request['image']['link']);
    });

    $message = Message::query()->where('conversation_id', $conversation->id)->first();
    expect($message->external_message_id)->toBe('wamid.SENT1');
});

it('marks a conversation as read', function () {
    $conversation = Conversation::factory()->create(['unread_count' => 5]);
    $user = actingAsConversationRole('agent');

    $response = $this->actingAs($user)->postJson("/api/v1/conversations/{$conversation->uuid}/read");

    $response->assertOk();
    expect($conversation->fresh()->unread_count)->toBe(0);
});

it('updates a conversation status', function () {
    $conversation = Conversation::factory()->create(['status' => 'open']);
    $user = actingAsConversationRole('manager');

    $response = $this->actingAs($user)->patchJson("/api/v1/conversations/{$conversation->uuid}/status", [
        'status' => 'resolved',
    ]);

    $response->assertOk();
    expect($conversation->fresh()->status)->toBe('resolved');
});

it('rejects an invalid conversation status', function () {
    $conversation = Conversation::factory()->create();
    $user = actingAsConversationRole('agent');

    $this->actingAs($user)->patchJson("/api/v1/conversations/{$conversation->uuid}/status", [
        'status' => 'not-a-real-status',
    ])->assertUnprocessable();
});
