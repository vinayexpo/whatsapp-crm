<?php

use App\Models\Notification;
use App\Models\User;

it('lists the authenticated users notifications, newest first', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $older = Notification::factory()->for($user)->create(['created_at' => now()->subHour()]);
    $newer = Notification::factory()->for($user)->create(['created_at' => now()]);
    Notification::factory()->for($other)->create();

    $response = $this->actingAs($user)->getJson('/api/v1/notifications')->assertOk();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toHaveCount(2)
        ->and($ids->first())->toBe($newer->uuid)
        ->and($ids->last())->toBe($older->uuid);
});

it('returns the unread notification count for the authenticated user', function () {
    $user = User::factory()->create();
    Notification::factory()->for($user)->count(2)->create();
    Notification::factory()->for($user)->read()->create();
    Notification::factory()->create(); // other user, unread

    $response = $this->actingAs($user)->getJson('/api/v1/notifications/unread-count')->assertOk();

    expect($response->json('data.count'))->toBe(2);
});

it('marks a single notification as read', function () {
    $user = User::factory()->create();
    $notification = Notification::factory()->for($user)->create();

    $this->actingAs($user)
        ->patchJson("/api/v1/notifications/{$notification->uuid}/read")
        ->assertOk()
        ->assertJsonPath('data.readAt', fn ($value) => $value !== null);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('prevents marking another users notification as read', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();
    $notification = Notification::factory()->for($owner)->create();

    $this->actingAs($user)
        ->patchJson("/api/v1/notifications/{$notification->uuid}/read")
        ->assertForbidden();

    expect($notification->fresh()->read_at)->toBeNull();
});

it('marks all of the authenticated users notifications as read', function () {
    $user = User::factory()->create();
    Notification::factory()->for($user)->count(3)->create();
    $otherUnread = Notification::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/notifications/mark-all-read')->assertOk();

    expect(Notification::query()->where('user_id', $user->id)->whereNull('read_at')->count())->toBe(0)
        ->and($otherUnread->fresh()->read_at)->toBeNull();
});

it('requires authentication to access notification endpoints', function () {
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
    $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
    $this->postJson('/api/v1/notifications/mark-all-read')->assertUnauthorized();
});
