<?php

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\PipelineStage;
use App\Models\User;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

function actingAsRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('lists contacts for any authenticated role', function () {
    Contact::factory()->count(3)->create();
    $user = actingAsRole('admin');

    $response = $this->actingAs($user)->getJson('/api/v1/contacts');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
});

it('scopes contacts for an agent to only those with a conversation assigned to them', function () {
    $agent = actingAsRole('agent');

    $assignedContact = Contact::factory()->create(['name' => 'Assigned To Me']);
    Conversation::factory()->create(['contact_id' => $assignedContact->id, 'assigned_to' => $agent->id]);

    $otherContact = Contact::factory()->create(['name' => 'Not Assigned']);
    Conversation::factory()->create(['contact_id' => $otherContact->id, 'assigned_to' => null]);

    $response = $this->actingAs($agent)->getJson('/api/v1/contacts');

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toContain('Assigned To Me');
    expect($names)->not->toContain('Not Assigned');
});

it('rejects unauthenticated contact listing', function () {
    $this->getJson('/api/v1/contacts')->assertUnauthorized();
});

it('allows admin to create a contact', function () {
    $admin = actingAsRole('admin');

    $response = $this->actingAs($admin)->postJson('/api/v1/contacts', [
        'name' => 'Test Contact',
        'channel' => 'whatsapp',
        'handle' => '+1 555 000 0000',
        'pipelineStage' => 'new-lead',
        'dealValue' => 500,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Test Contact')
        ->assertJsonPath('data.pipelineStage', 'new-lead');

    $this->assertDatabaseHas('contacts', ['name' => 'Test Contact']);
});

it('forbids agent from creating a contact', function () {
    $agent = actingAsRole('agent');

    $response = $this->actingAs($agent)->postJson('/api/v1/contacts', [
        'name' => 'Test Contact',
        'channel' => 'whatsapp',
        'handle' => '+1 555 000 0000',
        'pipelineStage' => 'new-lead',
    ]);

    $response->assertForbidden();
});

it('allows manager to update a contact', function () {
    $manager = actingAsRole('manager');
    $contact = Contact::factory()->create(['name' => 'Original Name']);

    $response = $this->actingAs($manager)->patchJson("/api/v1/contacts/{$contact->uuid}", [
        'name' => 'Updated Name',
    ]);

    $response->assertOk()->assertJsonPath('data.name', 'Updated Name');
});

it('forbids agent from deleting a contact', function () {
    $agent = actingAsRole('agent');
    $contact = Contact::factory()->create();

    $this->actingAs($agent)->deleteJson("/api/v1/contacts/{$contact->uuid}")->assertForbidden();
});

it('allows agent to move a contact pipeline stage', function () {
    $agent = actingAsRole('agent');
    $contact = Contact::factory()->create(['pipeline_stage_id' => 'new-lead']);

    $response = $this->actingAs($agent)->patchJson("/api/v1/contacts/{$contact->uuid}/pipeline-stage", [
        'pipelineStage' => 'qualified',
        'updatedAt' => $contact->updated_at->toIso8601String(),
    ]);

    $response->assertOk()->assertJsonPath('data.pipelineStage', 'qualified');
});

it('rejects a pipeline stage move with a stale updatedAt (optimistic concurrency)', function () {
    $agent = actingAsRole('agent');
    $contact = Contact::factory()->create(['pipeline_stage_id' => 'new-lead']);

    $stale = $contact->updated_at->subMinute()->toIso8601String();

    $response = $this->actingAs($agent)->patchJson("/api/v1/contacts/{$contact->uuid}/pipeline-stage", [
        'pipelineStage' => 'qualified',
        'updatedAt' => $stale,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('updatedAt');
    expect($contact->fresh()->pipeline_stage_id)->toBe('new-lead');
});

it('allows adding a tag to a contact as admin', function () {
    $admin = actingAsRole('admin');
    $contact = Contact::factory()->create();

    $response = $this->actingAs($admin)->postJson("/api/v1/contacts/{$contact->uuid}/tags", [
        'tag' => 'VIP',
    ]);

    $response->assertOk();
    expect($response->json('data.tags'))->toContain('VIP');
});

it('forbids agent from adding a tag to a contact', function () {
    $agent = actingAsRole('agent');
    $contact = Contact::factory()->create();

    $this->actingAs($agent)->postJson("/api/v1/contacts/{$contact->uuid}/tags", [
        'tag' => 'VIP',
    ])->assertForbidden();
});

it('lists pipeline stages in position order', function () {
    $user = actingAsRole('agent');

    $response = $this->actingAs($user)->getJson('/api/v1/pipeline-stages');

    $response->assertOk();
    expect($response->json('data.0.id'))->toBe('new-lead');
    expect($response->json('data'))->toHaveCount(6);
});
