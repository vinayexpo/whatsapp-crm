<?php

use App\Models\ApiConnection;
use App\Models\User;
use App\Models\WhatsappFlow;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function actingAsFlowRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('rejects unauthenticated flow listing and sync', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);

    $this->getJson("/api/v1/api-connections/{$connection->uuid}/flows")->assertUnauthorized();
    $this->postJson("/api/v1/api-connections/{$connection->uuid}/flows/sync")->assertUnauthorized();
});

it('forbids an agent from syncing flows', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    $user = actingAsFlowRole('agent');

    $this->actingAs($user)
        ->postJson("/api/v1/api-connections/{$connection->uuid}/flows/sync")
        ->assertForbidden();
});

it('allows a manager to sync flows from the fake Meta driver and upserts them', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    $user = actingAsFlowRole('manager');

    $response = $this->actingAs($user)->postJson("/api/v1/api-connections/{$connection->uuid}/flows/sync");

    $response->assertOk();
    expect(WhatsappFlow::query()->where('api_connection_id', $connection->id)->count())->toBe(3);

    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toContain('appointment_booking');

    // Syncing again should upsert, not duplicate.
    $this->actingAs($user)->postJson("/api/v1/api-connections/{$connection->uuid}/flows/sync")->assertOk();
    expect(WhatsappFlow::query()->where('api_connection_id', $connection->id)->count())->toBe(3);
});

it('rejects syncing flows for a non-whatsapp connection', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'instagram']);
    $user = actingAsFlowRole('admin');

    $this->actingAs($user)
        ->postJson("/api/v1/api-connections/{$connection->uuid}/flows/sync")
        ->assertUnprocessable();
});

it('lists synced flows for a connection', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    WhatsappFlow::factory()->create(['api_connection_id' => $connection->id, 'name' => 'welcome_survey']);
    $user = actingAsFlowRole('manager');

    $response = $this->actingAs($user)->getJson("/api/v1/api-connections/{$connection->uuid}/flows");

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('name')->all())->toContain('welcome_survey');
});

it('uses the real Graph API driver when the connection has a saved access token', function () {
    \Illuminate\Support\Facades\Http::fake([
        'graph.facebook.com/*' => \Illuminate\Support\Facades\Http::response([
            'data' => [
                ['id' => 'flow-999', 'name' => 'real_flow', 'status' => 'PUBLISHED', 'categories' => ['SURVEY']],
            ],
        ], 200),
    ]);

    $connection = ApiConnection::factory()->connected()->create([
        'channel' => 'whatsapp',
        'waba_id' => 'waba-789',
    ]);
    $user = actingAsFlowRole('manager');

    $response = $this->actingAs($user)->postJson("/api/v1/api-connections/{$connection->uuid}/flows/sync");

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toContain('real_flow');

    \Illuminate\Support\Facades\Http::assertSent(function ($request) {
        return str_contains($request->url(), 'graph.facebook.com')
            && str_contains($request->url(), 'waba-789/flows');
    });
});
