<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

function actingAsAssignmentRole(string $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

function conversationForCompany(Company $company, array $attributes = []): Conversation
{
    $contact = Contact::factory()->create(['company_id' => $company->id]);

    return Conversation::factory()->create([...$attributes, 'company_id' => $company->id, 'contact_id' => $contact->id]);
}

it('assigns a conversation to a team member in the same company', function () {
    $company = Company::factory()->create();
    $agent = actingAsAssignmentRole('agent', $company);
    $manager = actingAsAssignmentRole('manager', $company);
    $conversation = conversationForCompany($company);

    $response = $this->actingAs($manager)->patchJson("/api/v1/conversations/{$conversation->uuid}/assign", [
        'userId' => $agent->uuid,
    ]);

    $response->assertOk();
    expect($response->json('data.assignedTo.id'))->toBe($agent->uuid);
    expect($conversation->fresh()->assigned_to)->toBe($agent->id);
});

it('unassigns a conversation when userId is null', function () {
    $company = Company::factory()->create();
    $agent = actingAsAssignmentRole('agent', $company);
    $manager = actingAsAssignmentRole('manager', $company);
    $conversation = conversationForCompany($company, ['assigned_to' => $agent->id]);

    $response = $this->actingAs($manager)->patchJson("/api/v1/conversations/{$conversation->uuid}/assign", [
        'userId' => null,
    ]);

    $response->assertOk();
    expect($response->json('data.assignedTo'))->toBeNull();
    expect($conversation->fresh()->assigned_to)->toBeNull();
});

it('rejects assigning a conversation to a user from another company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $manager = actingAsAssignmentRole('manager', $companyA);
    $foreignAgent = actingAsAssignmentRole('agent', $companyB);
    $conversation = conversationForCompany($companyA);

    $this->actingAs($manager)->patchJson("/api/v1/conversations/{$conversation->uuid}/assign", [
        'userId' => $foreignAgent->uuid,
    ])->assertNotFound();

    expect($conversation->fresh()->assigned_to)->toBeNull();
});

it('filters the conversations index by assignedTo=me', function () {
    $company = Company::factory()->create();
    $agent = actingAsAssignmentRole('agent', $company);
    $other = actingAsAssignmentRole('agent', $company);

    $mine = conversationForCompany($company, ['assigned_to' => $agent->id]);
    conversationForCompany($company, ['assigned_to' => $other->id]);
    conversationForCompany($company, ['assigned_to' => null]);

    $response = $this->actingAs($agent)->getJson('/api/v1/conversations?assignedTo=me');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids->toArray())->toBe([$mine->uuid]);
});

it('filters the conversations index by assignedTo=unassigned', function () {
    $company = Company::factory()->create();
    $manager = actingAsAssignmentRole('manager', $company);
    $agent = actingAsAssignmentRole('agent', $company);

    conversationForCompany($company, ['assigned_to' => $agent->id]);
    $unassigned = conversationForCompany($company, ['assigned_to' => null]);

    $response = $this->actingAs($manager)->getJson('/api/v1/conversations?assignedTo=unassigned');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids->toArray())->toBe([$unassigned->uuid]);
});

it('restricts an agent to only their assigned conversations regardless of the assignedTo query param', function () {
    $company = Company::factory()->create();
    $agent = actingAsAssignmentRole('agent', $company);

    $mine = conversationForCompany($company, ['assigned_to' => $agent->id]);
    conversationForCompany($company, ['assigned_to' => null]);

    $response = $this->actingAs($agent)->getJson('/api/v1/conversations?assignedTo=unassigned');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids->toArray())->toBe([$mine->uuid]);
});
