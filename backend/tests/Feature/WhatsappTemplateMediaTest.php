<?php

use App\Models\ApiConnection;
use App\Models\User;
use App\Models\WhatsappTemplate;
use App\Services\Templates\GraphApiTemplateService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    config(['services.meta.whatsapp_driver' => 'fake']);
});

function actingAsMediaRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('uploads header media via the fake driver and returns a handle', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    $user = actingAsMediaRole('manager');

    $file = UploadedFile::fake()->image('header.jpg');

    $response = $this->actingAs($user)
        ->postJson("/api/v1/api-connections/{$connection->uuid}/templates/media", [
            'media' => $file,
        ]);

    $response->assertOk();
    expect($response->json('data.handle'))->toStartWith('fake-handle-');
});

it('rejects media upload for a user without campaigns.manage', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    $user = actingAsMediaRole('agent');

    $file = UploadedFile::fake()->image('header.jpg');

    $this->actingAs($user)
        ->postJson("/api/v1/api-connections/{$connection->uuid}/templates/media", [
            'media' => $file,
        ])->assertForbidden();
});

it('creates a draft template with an image header including the header handle', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    $user = actingAsMediaRole('manager');

    $response = $this->actingAs($user)
        ->postJson("/api/v1/api-connections/{$connection->uuid}/templates", [
            'name' => 'promo_image',
            'language' => 'en_US',
            'category' => 'marketing',
            'body' => 'Check out our sale {{1}}!',
            'variables' => ['1'],
            'components' => [
                ['type' => 'HEADER', 'format' => 'IMAGE', 'example' => ['header_handle' => ['fake-handle-123']]],
                ['type' => 'BODY', 'text' => 'Check out our sale {{1}}!'],
            ],
        ]);

    $response->assertCreated();

    $template = WhatsappTemplate::query()->where('name', 'promo_image')->first();
    $header = collect($template->components)->firstWhere('type', 'HEADER');

    expect($header['format'])->toBe('IMAGE');
    expect($header['example']['header_handle'])->toBe(['fake-handle-123']);
});

it('pushes local template edits to meta via the fake driver', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    $user = actingAsMediaRole('manager');
    $template = WhatsappTemplate::factory()->create([
        'api_connection_id' => $connection->id,
        'status' => 'approved',
        'meta_template_id' => 'meta-123',
    ]);

    $response = $this->actingAs($user)->postJson("/api/v1/templates/{$template->uuid}/push-meta");

    $response->assertOk();
    $template->refresh();
    expect($template->status)->toBe('pending');
    expect($template->synced_at)->not->toBeNull();
});

it('rejects push when the template has no meta_template_id yet', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    $user = actingAsMediaRole('manager');
    $template = WhatsappTemplate::factory()->create([
        'api_connection_id' => $connection->id,
        'status' => 'draft',
        'meta_template_id' => null,
    ]);

    $this->actingAs($user)
        ->postJson("/api/v1/templates/{$template->uuid}/push-meta")
        ->assertUnprocessable();
});

it('rejects push for a user without campaigns.manage', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    $user = actingAsMediaRole('agent');
    $template = WhatsappTemplate::factory()->create([
        'api_connection_id' => $connection->id,
        'status' => 'approved',
        'meta_template_id' => 'meta-123',
    ]);

    $this->actingAs($user)
        ->postJson("/api/v1/templates/{$template->uuid}/push-meta")
        ->assertForbidden();
});

it('skips overwriting a template edited locally after its last sync', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    $user = actingAsMediaRole('manager');

    $existing = WhatsappTemplate::factory()->create([
        'api_connection_id' => $connection->id,
        'meta_template_id' => '1001_'.$connection->id,
        'name' => 'Locally Edited',
        'status' => 'pending',
        'synced_at' => now()->subDay(),
    ]);
    $existing->touch();

    $this->actingAs($user)->postJson("/api/v1/api-connections/{$connection->uuid}/templates/sync")->assertOk();

    $existing->refresh();
    expect($existing->name)->toBe('Locally Edited');
    expect($existing->status)->toBe('pending');
});

it('hits the two-step resumable upload endpoints in order with the right payload shape', function () {
    $connection = ApiConnection::factory()->connected()->create(['channel' => 'whatsapp']);
    config(['services.meta.app_id' => 'app-123']);

    Http::fake([
        'graph.facebook.com/v20.0/app-123/uploads' => Http::response(['id' => 'upload:session-1'], 200),
        'graph.facebook.com/v20.0/upload:session-1' => Http::response(['h' => 'real-handle-1'], 200),
    ]);

    $file = UploadedFile::fake()->image('header.jpg')->size(100);

    $service = new GraphApiTemplateService();
    $handle = $service->uploadHeaderMedia($connection, $file);

    expect($handle)->toBe('real-handle-1');

    Http::assertSentInOrder([
        function ($request) {
            return str_contains($request->url(), 'app-123/uploads')
                && $request['file_type'] === 'image/jpeg';
        },
        function ($request) {
            return str_contains($request->url(), 'upload:session-1')
                && $request->hasHeader('file_offset', '0');
        },
    ]);
});
