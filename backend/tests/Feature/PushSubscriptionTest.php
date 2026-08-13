<?php

use App\Models\PushSubscription;
use App\Models\User;

it('returns the configured vapid public key', function () {
    config(['services.web_push.vapid_public_key' => 'test-public-key']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/push/vapid-public-key')
        ->assertOk()
        ->assertJsonPath('data.publicKey', 'test-public-key');
});

it('creates a push subscription for the authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/push-subscriptions', [
        'endpoint' => 'https://push.example.com/abc123',
        'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-secret'],
        'contentEncoding' => 'aes128gcm',
    ])->assertCreated();

    $this->assertDatabaseHas('push_subscriptions', [
        'user_id' => $user->id,
        'endpoint' => 'https://push.example.com/abc123',
        'public_key' => 'p256dh-key',
        'auth_token' => 'auth-secret',
        'content_encoding' => 'aes128gcm',
    ]);
});

it('updates an existing subscription instead of duplicating on the same endpoint', function () {
    $user = User::factory()->create();
    PushSubscription::factory()->for($user)->create(['endpoint' => 'https://push.example.com/abc123']);

    $this->actingAs($user)->postJson('/api/v1/push-subscriptions', [
        'endpoint' => 'https://push.example.com/abc123',
        'keys' => ['p256dh' => 'new-key', 'auth' => 'new-secret'],
    ])->assertCreated();

    expect(PushSubscription::query()->where('user_id', $user->id)->count())->toBe(1);
    $this->assertDatabaseHas('push_subscriptions', [
        'user_id' => $user->id,
        'endpoint' => 'https://push.example.com/abc123',
        'public_key' => 'new-key',
    ]);
});

it('validates required fields when creating a subscription', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/push-subscriptions', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
});

it('deletes a push subscription by endpoint for the authenticated user', function () {
    $user = User::factory()->create();
    PushSubscription::factory()->for($user)->create(['endpoint' => 'https://push.example.com/abc123']);

    $this->actingAs($user)->deleteJson('/api/v1/push-subscriptions', [
        'endpoint' => 'https://push.example.com/abc123',
    ])->assertOk();

    $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://push.example.com/abc123']);
});

it('does not delete another users push subscription', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();
    $subscription = PushSubscription::factory()->for($owner)->create(['endpoint' => 'https://push.example.com/other']);

    $this->actingAs($user)->deleteJson('/api/v1/push-subscriptions', [
        'endpoint' => 'https://push.example.com/other',
    ])->assertOk();

    $this->assertDatabaseHas('push_subscriptions', ['id' => $subscription->id]);
});

it('requires authentication for push subscription endpoints', function () {
    $this->getJson('/api/v1/push/vapid-public-key')->assertUnauthorized();
    $this->postJson('/api/v1/push-subscriptions', [])->assertUnauthorized();
    $this->deleteJson('/api/v1/push-subscriptions', [])->assertUnauthorized();
});
