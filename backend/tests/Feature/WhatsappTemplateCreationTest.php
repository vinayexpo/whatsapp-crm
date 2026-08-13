<?php

use App\Models\ApiConnection;
use App\Models\Notification;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    config(['services.meta.whatsapp_driver' => 'fake']);
});

function actingAsCreationRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('rejects unauthenticated template creation', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);

    $this->postJson("/api/v1/api-connections/{$connection->uuid}/templates", [
        'name' => 'welcome_message',
        'language' => 'en_US',
        'category' => 'utility',
        'body' => 'Hi {{1}}, welcome!',
    ])->assertUnauthorized();
});

it('forbids an agent from creating a template', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    $user = actingAsCreationRole('agent');

    $this->actingAs($user)
        ->postJson("/api/v1/api-connections/{$connection->uuid}/templates", [
            'name' => 'welcome_message',
            'language' => 'en_US',
            'category' => 'utility',
            'body' => 'Hi {{1}}, welcome!',
        ])->assertForbidden();
});

it('allows a manager to create a draft template', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    $user = actingAsCreationRole('manager');

    $response = $this->actingAs($user)
        ->postJson("/api/v1/api-connections/{$connection->uuid}/templates", [
            'name' => 'welcome_message',
            'language' => 'en_US',
            'category' => 'utility',
            'body' => 'Hi {{1}}, welcome!',
            'variables' => ['1'],
            'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}, welcome!']],
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.status', 'draft');
    $response->assertJsonPath('data.name', 'welcome_message');

    $template = WhatsappTemplate::query()->where('name', 'welcome_message')->first();
    expect($template)->not->toBeNull();
    expect($template->status)->toBe('draft');
    expect($template->meta_template_id)->toBeNull();
    expect($template->created_by_user_id)->toBe($user->id);
});

it('rejects creating a template for a non-whatsapp connection', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'instagram']);
    $user = actingAsCreationRole('manager');

    $this->actingAs($user)
        ->postJson("/api/v1/api-connections/{$connection->uuid}/templates", [
            'name' => 'welcome_message',
            'language' => 'en_US',
            'category' => 'utility',
            'body' => 'Hi {{1}}, welcome!',
        ])->assertUnprocessable();
});

it('allows editing a draft template but not a non-draft one', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    $user = actingAsCreationRole('manager');
    $draft = WhatsappTemplate::factory()->create([
        'api_connection_id' => $connection->id,
        'status' => 'draft',
        'meta_template_id' => null,
    ]);
    $approved = WhatsappTemplate::factory()->create([
        'api_connection_id' => $connection->id,
        'status' => 'approved',
    ]);

    $this->actingAs($user)
        ->patchJson("/api/v1/templates/{$draft->uuid}", ['body' => 'Updated body {{1}}'])
        ->assertOk()
        ->assertJsonPath('data.body', 'Updated body {{1}}');

    $this->actingAs($user)
        ->patchJson("/api/v1/templates/{$approved->uuid}", ['body' => 'Updated body {{1}}'])
        ->assertUnprocessable();
});

it('allows deleting a draft template but not a non-draft one', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    $user = actingAsCreationRole('manager');
    $draft = WhatsappTemplate::factory()->create([
        'api_connection_id' => $connection->id,
        'status' => 'draft',
        'meta_template_id' => null,
    ]);
    $approved = WhatsappTemplate::factory()->create([
        'api_connection_id' => $connection->id,
        'status' => 'approved',
    ]);

    $this->actingAs($user)->deleteJson("/api/v1/templates/{$approved->uuid}")->assertUnprocessable();
    $this->actingAs($user)->deleteJson("/api/v1/templates/{$draft->uuid}")->assertNoContent();

    expect(WhatsappTemplate::query()->find($draft->id))->toBeNull();
});

it('submits a draft template and transitions it to pending or resolved status', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    $user = actingAsCreationRole('manager');
    $draft = WhatsappTemplate::factory()->create([
        'api_connection_id' => $connection->id,
        'name' => 'welcome_message',
        'status' => 'draft',
        'meta_template_id' => null,
        'synced_at' => null,
    ]);

    $response = $this->actingAs($user)->postJson("/api/v1/templates/{$draft->uuid}/submit");

    $response->assertOk();
    $draft->refresh();
    expect($draft->status)->toBe('approved');
    expect($draft->meta_template_id)->not->toBeNull();
    expect($draft->submitted_at)->not->toBeNull();

    expect(Notification::query()->where('user_id', $user->id)->where('type', 'template_approved')->exists())->toBeTrue();
});

it('rejects submitting a non-draft template', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    $user = actingAsCreationRole('manager');
    $template = WhatsappTemplate::factory()->create([
        'api_connection_id' => $connection->id,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->postJson("/api/v1/templates/{$template->uuid}/submit")
        ->assertUnprocessable();
});

it('notifies users with campaigns.manage permission when a template is approved via sync', function () {
    $connection = ApiConnection::factory()->create(['channel' => 'whatsapp']);
    $manager = actingAsCreationRole('manager');

    $template = WhatsappTemplate::factory()->create([
        'api_connection_id' => $connection->id,
        'meta_template_id' => '1001_'.$connection->id,
        'name' => 'order_confirmation',
        'status' => 'pending',
    ]);

    $this->actingAs($manager)->postJson("/api/v1/api-connections/{$connection->uuid}/templates/sync")->assertOk();

    $template->refresh();
    expect($template->status)->toBe('approved');

    expect(Notification::query()->where('user_id', $manager->id)->where('type', 'template_approved')->exists())->toBeTrue();
});
