<?php

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\DailyMetric;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

function actingAsMetricsRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('rejects unauthenticated daily metrics listing', function () {
    $this->getJson('/api/v1/daily-metrics')->assertUnauthorized();
});

it('allows any authenticated role to list daily metrics in date order', function () {
    DailyMetric::factory()->create(['date' => '2026-08-02']);
    DailyMetric::factory()->create(['date' => '2026-08-01']);

    $user = actingAsMetricsRole('manager');

    $response = $this->actingAs($user)->getJson('/api/v1/daily-metrics');

    $response->assertOk();
    $dates = collect($response->json('data'))->pluck('date');
    expect($dates->all())->toBe(['2026-08-01', '2026-08-02']);
});

it('forbids an agent from listing daily metrics', function () {
    $user = actingAsMetricsRole('agent');

    $this->actingAs($user)->getJson('/api/v1/daily-metrics')->assertForbidden();
});

it('aggregates message volume, delivery, read, reply, and response time for a given day', function () {
    $contact = Contact::factory()->create(['channel' => 'whatsapp']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id, 'channel' => 'whatsapp']);

    $day = '2026-08-02';

    $inbound = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'sent_at' => "$day 10:00:00",
    ]);

    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'outbound',
        'status' => 'read',
        'sent_at' => "$day 10:10:00",
    ]);

    $instagramContact = Contact::factory()->create(['channel' => 'instagram']);
    $instagramConversation = Conversation::factory()->create(['contact_id' => $instagramContact->id, 'channel' => 'instagram']);
    Message::factory()->create([
        'conversation_id' => $instagramConversation->id,
        'direction' => 'outbound',
        'status' => 'delivered',
        'sent_at' => "$day 11:00:00",
    ]);

    Artisan::call('metrics:aggregate-daily', ['date' => $day]);

    $metric = DailyMetric::query()->where('date', $day)->first();

    expect($metric)->not->toBeNull();
    expect($metric->whatsapp_sent)->toBe(1);
    expect($metric->instagram_sent)->toBe(1);
    expect($metric->delivered)->toBe(2);
    expect($metric->read)->toBe(1);
    expect($metric->replied)->toBe(1);
    expect($metric->avg_response_minutes)->toBe(10);
});

it('is idempotent when run twice for the same day', function () {
    $day = '2026-08-02';
    Artisan::call('metrics:aggregate-daily', ['date' => $day]);
    Artisan::call('metrics:aggregate-daily', ['date' => $day]);

    expect(DailyMetric::query()->where('date', $day)->count())->toBe(1);
});
