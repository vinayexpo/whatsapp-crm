<?php

use App\Events\NotificationCreated;
use App\Models\NotificationPreference;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Notifications\NotificationDispatchService;
use App\Services\Notifications\PushNotificationServiceInterface;
use Illuminate\Support\Facades\Event;

it('creates a notification and broadcasts NotificationCreated on the users private channel', function () {
    Event::fake([NotificationCreated::class]);
    $user = User::factory()->create();

    $notification = app(NotificationDispatchService::class)->notify(
        $user,
        'campaign_completed',
        'Campaign completed',
        'Your campaign finished sending.',
        ['campaignId' => 'camp-123'],
    );

    expect($notification)->not->toBeNull()
        ->and($notification->user_id)->toBe($user->id);

    Event::assertDispatched(NotificationCreated::class, function (NotificationCreated $event) use ($notification) {
        return $event->notification->id === $notification->id
            && $event->broadcastAs() === 'notification.created'
            && collect($event->broadcastOn())->contains(
                fn ($channel) => $channel->name === 'private-App.Models.User.'.$notification->user_id
            );
    });
});

it('does not create a notification when the user has disabled that preference', function () {
    Event::fake([NotificationCreated::class]);
    $user = User::factory()->create();
    NotificationPreference::factory()->for($user)->create(['automation_triggered' => false]);

    $notification = app(NotificationDispatchService::class)->notify(
        $user,
        'automation_triggered',
        'Automation ran',
        'An automation flow was triggered.',
    );

    expect($notification)->toBeNull();
    $this->assertDatabaseMissing('notifications', ['user_id' => $user->id, 'type' => 'automation_triggered']);
    Event::assertNotDispatched(NotificationCreated::class);
});

it('creates the notification when no preference row exists (defaults to enabled)', function () {
    $user = User::factory()->create();

    $notification = app(NotificationDispatchService::class)->notify(
        $user,
        'campaign_completed',
        'Campaign completed',
        'Your campaign finished sending.',
    );

    expect($notification)->not->toBeNull();
    $this->assertDatabaseHas('notifications', ['id' => $notification->id, 'user_id' => $user->id]);
});

it('fans a push payload out to every subscription the user has registered', function () {
    $user = User::factory()->create();
    $subscriptions = PushSubscription::factory()->for($user)->count(2)->create();

    $pushService = Mockery::mock(PushNotificationServiceInterface::class);
    $pushService->shouldReceive('send')->twice();
    app()->instance(PushNotificationServiceInterface::class, $pushService);

    app(NotificationDispatchService::class)->notify(
        $user,
        'campaign_completed',
        'Campaign completed',
        'Your campaign finished sending.',
    );

    expect($subscriptions)->toHaveCount(2);
});
