<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\RateLimiter;

function loginRequest(array $payload)
{
    return test()->withHeader('Origin', config('app.frontend_url', 'http://localhost:5173'))
        ->postJson('/api/v1/auth/login', $payload);
}

beforeEach(function () {
    config(['app.frontend_url' => env('FRONTEND_URL', 'http://localhost:5173')]);
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('logs in with valid credentials and returns the user with their role', function () {
    $user = User::factory()->create([
        'email' => 'admin@omnichat.test',
        'password' => bcrypt('password'),
    ]);
    $user->assignRole('admin');

    $response = loginRequest([
        'email' => 'admin@omnichat.test',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.email', 'admin@omnichat.test')
        ->assertJsonPath('data.role', 'admin');

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    User::factory()->create([
        'email' => 'admin@omnichat.test',
        'password' => bcrypt('password'),
    ]);

    $response = loginRequest([
        'email' => 'admin@omnichat.test',
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('returns the authenticated user and role via /auth/me', function () {
    $user = User::factory()->create();
    $user->assignRole('manager');

    $response = $this->actingAs($user)->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('data.id', $user->uuid)
        ->assertJsonPath('data.role', 'manager');
});

it('rejects /auth/me when unauthenticated', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('clears the session on logout', function () {
    $user = User::factory()->create([
        'email' => 'agent@omnichat.test',
        'password' => bcrypt('password'),
    ]);
    $user->assignRole('agent');

    loginRequest([
        'email' => 'agent@omnichat.test',
        'password' => 'password',
    ])->assertOk();

    $this->withHeader('Origin', config('app.frontend_url', 'http://localhost:5173'))
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    $this->assertGuest('web');
});

it('rate limits repeated failed login attempts', function () {
    User::factory()->create([
        'email' => 'admin@omnichat.test',
        'password' => bcrypt('password'),
    ]);

    RateLimiter::clear('admin@omnichat.test|127.0.0.1');

    for ($i = 0; $i < 5; $i++) {
        loginRequest([
            'email' => 'admin@omnichat.test',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    $response = loginRequest([
        'email' => 'admin@omnichat.test',
        'password' => 'password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});
