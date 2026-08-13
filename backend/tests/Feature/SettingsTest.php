<?php

use App\Models\AiAssistantSetting;
use App\Models\ApiConnection;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function actingAsSettingsRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

// --- API connections ---

it('rejects unauthenticated api-connections listing', function () {
    $this->getJson('/api/v1/api-connections')->assertUnauthorized();
});

it('forbids a non-admin from viewing api connections', function () {
    $user = actingAsSettingsRole('agent');

    $this->actingAs($user)->getJson('/api/v1/api-connections')->assertForbidden();
});

it('allows an admin to list api connections', function () {
    ApiConnection::factory()->create(['channel' => 'whatsapp', 'status' => 'disconnected']);
    $admin = actingAsSettingsRole('admin');

    $this->actingAs($admin)->getJson('/api/v1/api-connections')->assertOk();
});

it('allows an admin to connect a whatsapp connection after Meta verifies the token', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['id' => 'waba-123'], 200),
    ]);

    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp', 'status' => 'disconnected']);
    $admin = actingAsSettingsRole('admin');

    $response = $this->actingAs($admin)->patchJson("/api/v1/api-connections/{$connection->uuid}", [
        'status' => 'connected',
        'accessToken' => 'fake-token-123',
        'wabaId' => 'waba-123',
        'phoneNumberId' => 'phone-456',
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.status', 'connected');
    expect($response->json('data'))->not->toHaveKey('accessToken');
    expect($connection->fresh()->status)->toBe('connected');
    expect($connection->fresh()->access_token)->toBe('fake-token-123');
    expect($connection->fresh()->waba_id)->toBe('waba-123');
    expect($connection->fresh()->phone_number_id)->toBe('phone-456');
});

it('allows an admin to connect an instagram connection after Meta verifies the token', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['id' => 'ig-123'], 200),
    ]);

    $connection = ApiConnection::factory()->create(['channel' => 'instagram', 'status' => 'disconnected']);
    $admin = actingAsSettingsRole('admin');

    $response = $this->actingAs($admin)->patchJson("/api/v1/api-connections/{$connection->uuid}", [
        'status' => 'connected',
        'accessToken' => 'fake-token-123',
        'instagramAccountId' => 'ig-123',
    ]);

    $response->assertOk();
    expect($connection->fresh()->status)->toBe('connected');
    expect($connection->fresh()->instagram_account_id)->toBe('ig-123');
});

it('allows an admin to connect a voice connection after Twilio verifies the credentials', function () {
    Http::fake([
        'api.twilio.com/*' => Http::response(['sid' => 'AC123'], 200),
    ]);

    $connection = ApiConnection::factory()->create(['channel' => 'voice', 'status' => 'disconnected']);
    $admin = actingAsSettingsRole('admin');

    $response = $this->actingAs($admin)->patchJson("/api/v1/api-connections/{$connection->uuid}", [
        'status' => 'connected',
        'accessToken' => 'auth-token-123',
        'twilioAccountSid' => 'AC123',
        'twilioPhoneNumber' => '+15551234567',
    ]);

    $response->assertOk();
    expect($connection->fresh()->status)->toBe('connected');
    expect($connection->fresh()->access_token)->toBe('auth-token-123');
    expect($connection->fresh()->twilio_account_sid)->toBe('AC123');
    expect($connection->fresh()->twilio_phone_number)->toBe('+15551234567');
});

it('rejects connecting a voice connection when Twilio rejects the credentials', function () {
    Http::fake([
        'api.twilio.com/*' => Http::response(['message' => 'Authenticate'], 401),
    ]);

    $connection = ApiConnection::factory()->create(['channel' => 'voice', 'status' => 'disconnected']);
    $admin = actingAsSettingsRole('admin');

    $response = $this->actingAs($admin)->patchJson("/api/v1/api-connections/{$connection->uuid}", [
        'status' => 'connected',
        'accessToken' => 'bad-token',
        'twilioAccountSid' => 'AC123',
        'twilioPhoneNumber' => '+15551234567',
    ]);

    $response->assertUnprocessable();
    expect($connection->fresh()->status)->toBe('disconnected');
});

it('rejects connecting when required account ids are missing', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp', 'status' => 'disconnected']);
    $admin = actingAsSettingsRole('admin');

    $response = $this->actingAs($admin)->patchJson("/api/v1/api-connections/{$connection->uuid}", [
        'status' => 'connected',
        'accessToken' => 'fake-token-123',
    ]);

    $response->assertUnprocessable();
    expect($connection->fresh()->status)->toBe('disconnected');
});

it('rejects connecting when Meta rejects the access token', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid token']], 401),
    ]);

    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp', 'status' => 'disconnected']);
    $admin = actingAsSettingsRole('admin');

    $response = $this->actingAs($admin)->patchJson("/api/v1/api-connections/{$connection->uuid}", [
        'status' => 'connected',
        'accessToken' => 'bad-token',
        'wabaId' => 'waba-123',
        'phoneNumberId' => 'phone-456',
    ]);

    $response->assertUnprocessable();
    expect($connection->fresh()->status)->toBe('disconnected');
    expect($connection->fresh()->access_token)->toBeNull();
});

it('allows an admin to disconnect an api connection without hitting Meta', function () {
    Http::fake();

    $connection = ApiConnection::factory()->connected()->create(['channel' => 'whatsapp']);
    $admin = actingAsSettingsRole('admin');

    $response = $this->actingAs($admin)->patchJson("/api/v1/api-connections/{$connection->uuid}", [
        'status' => 'disconnected',
    ]);

    $response->assertOk();
    expect($connection->fresh()->status)->toBe('disconnected');
    expect($connection->fresh()->access_token)->toBeNull();
    Http::assertNothingSent();
});

// --- WhatsApp calling toggle ---

it('rejects unauthenticated calling toggle', function () {
    $connection = ApiConnection::factory()->connected()->create(['channel' => 'whatsapp']);

    $this->patchJson("/api/v1/api-connections/{$connection->uuid}/calling", ['callingEnabled' => true])
        ->assertUnauthorized();
});

it('forbids a non-admin from toggling calling', function () {
    $connection = ApiConnection::factory()->connected()->create(['channel' => 'whatsapp']);
    $agent = actingAsSettingsRole('agent');

    $this->actingAs($agent)->patchJson("/api/v1/api-connections/{$connection->uuid}/calling", ['callingEnabled' => true])
        ->assertForbidden();
});

it('allows an admin to enable calling on a connected whatsapp connection', function () {
    $connection = ApiConnection::factory()->connected()->create(['channel' => 'whatsapp']);
    $admin = actingAsSettingsRole('admin');

    $response = $this->actingAs($admin)->patchJson("/api/v1/api-connections/{$connection->uuid}/calling", [
        'callingEnabled' => true,
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.callingEnabled', true);
    $response->assertJsonPath('data.callingStatus', 'active');
    expect($connection->fresh()->calling_enabled)->toBeTrue();
    expect($connection->fresh()->calling_status)->toBe('active');
    expect($connection->fresh()->calling_verified_at)->not->toBeNull();
});

it('allows an admin to disable calling', function () {
    $connection = ApiConnection::factory()->connected()->create([
        'channel' => 'whatsapp',
        'calling_enabled' => true,
        'calling_status' => 'active',
        'calling_verified_at' => now(),
    ]);
    $admin = actingAsSettingsRole('admin');

    $response = $this->actingAs($admin)->patchJson("/api/v1/api-connections/{$connection->uuid}/calling", [
        'callingEnabled' => false,
    ]);

    $response->assertOk();
    expect($connection->fresh()->calling_enabled)->toBeFalse();
    expect($connection->fresh()->calling_status)->toBe('disabled');
    expect($connection->fresh()->calling_verified_at)->toBeNull();
});

it('rejects enabling calling on a disconnected whatsapp connection', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp', 'status' => 'disconnected']);
    $admin = actingAsSettingsRole('admin');

    $response = $this->actingAs($admin)->patchJson("/api/v1/api-connections/{$connection->uuid}/calling", [
        'callingEnabled' => true,
    ]);

    $response->assertUnprocessable();
    expect($connection->fresh()->calling_enabled)->toBeFalse();
});

it('rejects enabling calling on a non-whatsapp connection', function () {
    $connection = ApiConnection::factory()->connected()->create(['channel' => 'instagram']);
    $admin = actingAsSettingsRole('admin');

    $response = $this->actingAs($admin)->patchJson("/api/v1/api-connections/{$connection->uuid}/calling", [
        'callingEnabled' => true,
    ]);

    $response->assertUnprocessable();
    expect($connection->fresh()->calling_enabled)->toBeFalse();
});

// --- Notification preferences ---

it('rejects unauthenticated notification-preferences access', function () {
    $this->getJson('/api/v1/notification-preferences')->assertUnauthorized();
});

it('allows any authenticated role to view and update their own notification preferences', function () {
    $agent = actingAsSettingsRole('agent');

    $show = $this->actingAs($agent)->getJson('/api/v1/notification-preferences');
    $show->assertOk();
    $show->assertJsonPath('data.newMessageAlerts', true);

    $update = $this->actingAs($agent)->patchJson('/api/v1/notification-preferences', [
        'soundAlerts' => true,
        'dailySummaryEmail' => false,
    ]);

    $update->assertOk();
    $update->assertJsonPath('data.soundAlerts', true);
    $update->assertJsonPath('data.dailySummaryEmail', false);
    $update->assertJsonPath('data.campaignCompleted', true);
});

it('keeps notification preferences scoped per user', function () {
    $agentA = actingAsSettingsRole('agent');
    $agentB = actingAsSettingsRole('agent');

    $this->actingAs($agentA)->patchJson('/api/v1/notification-preferences', [
        'soundAlerts' => true,
    ])->assertOk();

    $response = $this->actingAs($agentB)->getJson('/api/v1/notification-preferences');
    $response->assertJsonPath('data.soundAlerts', false);
});

// --- AI assistant settings ---

it('forbids a non-admin from viewing ai assistant settings', function () {
    $user = actingAsSettingsRole('manager');

    $this->actingAs($user)->getJson('/api/v1/ai-assistant-settings')->assertForbidden();
});

it('allows an admin to view and update the global ai assistant settings', function () {
    $admin = actingAsSettingsRole('admin');

    $show = $this->actingAs($admin)->getJson('/api/v1/ai-assistant-settings');
    $show->assertOk();
    $show->assertJsonPath('data.model', 'gpt-4o-mini');

    $update = $this->actingAs($admin)->patchJson('/api/v1/ai-assistant-settings', [
        'model' => 'gpt-4.1-mini',
    ]);

    $update->assertOk();
    $update->assertJsonPath('data.model', 'gpt-4.1-mini');
    expect(AiAssistantSetting::current()->model)->toBe('gpt-4.1-mini');
});

it('accepts ai assistant chat responses that return structured content parts', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => [
                        ['type' => 'output_text', 'text' => 'Structured reply'],
                    ],
                ],
            ]],
        ], 200),
    ]);

    $admin = actingAsSettingsRole('admin');

    AiAssistantSetting::current()->update([
        'api_key' => 'test-key',
        'model' => 'gpt-4o-mini',
        'base_url' => 'https://api.openai.com/v1',
    ]);

    $response = $this->actingAs($admin)->postJson('/api/v1/ai-assistant/chat', [
        'messages' => [
            ['role' => 'user', 'content' => 'Hello'],
        ],
    ]);

    $response->assertOk()->assertJsonPath('data.content', 'Structured reply');
});

// --- Activity logs ---

it('rejects unauthenticated activity-log listing', function () {
    $this->getJson('/api/v1/activity-logs')->assertUnauthorized();
});

it('allows any authenticated role to view the activity feed', function () {
    \App\Models\ActivityLog::factory()->count(3)->create();
    $agent = actingAsSettingsRole('agent');

    $response = $this->actingAs($agent)->getJson('/api/v1/activity-logs');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
});
