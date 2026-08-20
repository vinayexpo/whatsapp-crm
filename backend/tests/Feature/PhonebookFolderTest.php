<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\PhonebookFolder;
use App\Models\User;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

function actingAsPhonebookRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('rejects unauthenticated phonebook folder listing', function () {
    $this->getJson('/api/v1/phonebook-folders')->assertUnauthorized();
});

it('lists phonebook folders for any authenticated role', function () {
    PhonebookFolder::factory()->count(2)->create();
    $user = actingAsPhonebookRole('manager');

    $response = $this->actingAs($user)->getJson('/api/v1/phonebook-folders');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('forbids an agent from listing phonebook folders', function () {
    PhonebookFolder::factory()->count(2)->create();
    $user = actingAsPhonebookRole('agent');

    $this->actingAs($user)->getJson('/api/v1/phonebook-folders')->assertForbidden();
});

it('allows an admin to create a phonebook folder', function () {
    $admin = actingAsPhonebookRole('admin');

    $response = $this->actingAs($admin)->postJson('/api/v1/phonebook-folders', [
        'name' => 'Spring Promo',
    ]);

    $response->assertCreated()->assertJsonPath('data.name', 'Spring Promo');
    $this->assertDatabaseHas('phonebook_folders', ['name' => 'Spring Promo']);
});

it('forbids an agent from creating a phonebook folder', function () {
    $agent = actingAsPhonebookRole('agent');

    $this->actingAs($agent)->postJson('/api/v1/phonebook-folders', [
        'name' => 'Spring Promo',
    ])->assertForbidden();
});

it('allows a manager to rename a phonebook folder', function () {
    $manager = actingAsPhonebookRole('manager');
    $folder = PhonebookFolder::factory()->create(['name' => 'Old Name']);

    $response = $this->actingAs($manager)->patchJson("/api/v1/phonebook-folders/{$folder->uuid}", [
        'name' => 'New Name',
    ]);

    $response->assertOk()->assertJsonPath('data.name', 'New Name');
});

it('forbids an agent from deleting a phonebook folder', function () {
    $agent = actingAsPhonebookRole('agent');
    $folder = PhonebookFolder::factory()->create();

    $this->actingAs($agent)->deleteJson("/api/v1/phonebook-folders/{$folder->uuid}")->assertForbidden();
});

it('allows an admin to delete a phonebook folder', function () {
    $admin = actingAsPhonebookRole('admin');
    $folder = PhonebookFolder::factory()->create();

    $this->actingAs($admin)->deleteJson("/api/v1/phonebook-folders/{$folder->uuid}")->assertOk();

    expect(PhonebookFolder::query()->count())->toBe(0);
});

it('allows adding existing contacts to a folder', function () {
    $admin = actingAsPhonebookRole('admin');
    $folder = PhonebookFolder::factory()->create();
    $contact = Contact::factory()->create();

    $response = $this->actingAs($admin)->postJson("/api/v1/phonebook-folders/{$folder->uuid}/contacts", [
        'contactIds' => [$contact->uuid],
    ]);

    $response->assertOk()->assertJsonPath('data.contactCount', 1);
    expect($folder->contacts()->where('contact_id', $contact->id)->exists())->toBeTrue();
});

it('paginates a folder contacts listing via the dedicated endpoint', function () {
    $admin = actingAsPhonebookRole('admin');
    $folder = PhonebookFolder::factory()->create();
    $contacts = Contact::factory()->count(3)->create();
    $folder->contacts()->attach($contacts->pluck('id'));

    $response = $this->actingAs($admin)->getJson("/api/v1/phonebook-folders/{$folder->uuid}/contacts?per_page=2");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('meta.last_page'))->toBe(2);
    expect($response->json('meta.total'))->toBe(3);
});

it('does not eager-load contacts on folder show', function () {
    $admin = actingAsPhonebookRole('admin');
    $folder = PhonebookFolder::factory()->create();
    $contact = Contact::factory()->create();
    $folder->contacts()->attach($contact->id);

    $response = $this->actingAs($admin)->getJson("/api/v1/phonebook-folders/{$folder->uuid}");

    $response->assertOk();
    expect($response->json('data.contactCount'))->toBe(1);
    expect($response->json('data.contacts'))->toBeNull();
});

it('allows removing a contact from a folder', function () {
    $admin = actingAsPhonebookRole('admin');
    $folder = PhonebookFolder::factory()->create();
    $contact = Contact::factory()->create();
    $folder->contacts()->attach($contact->id);

    $response = $this->actingAs($admin)->deleteJson("/api/v1/phonebook-folders/{$folder->uuid}/contacts/{$contact->uuid}");

    $response->assertOk()->assertJsonPath('data.contactCount', 0);
});

it('allows the same contact to belong to multiple folders', function () {
    $admin = actingAsPhonebookRole('admin');
    $folderA = PhonebookFolder::factory()->create();
    $folderB = PhonebookFolder::factory()->create();
    $contact = Contact::factory()->create();

    $this->actingAs($admin)->postJson("/api/v1/phonebook-folders/{$folderA->uuid}/contacts", [
        'contactIds' => [$contact->uuid],
    ])->assertOk();

    $this->actingAs($admin)->postJson("/api/v1/phonebook-folders/{$folderB->uuid}/contacts", [
        'contactIds' => [$contact->uuid],
    ])->assertOk();

    expect($contact->fresh()->phonebookFolders()->count())->toBe(2);
});

it('scopes phonebook folders to the caller company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = User::factory()->create(['company_id' => $companyA->id]);
    $userA->assignRole('manager');

    PhonebookFolder::factory()->create(['company_id' => $companyA->id]);
    PhonebookFolder::factory()->create(['company_id' => $companyB->id]);

    $response = $this->actingAs($userA)->getJson('/api/v1/phonebook-folders');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('404s when accessing a phonebook folder belonging to another company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = User::factory()->create(['company_id' => $companyA->id]);
    $userA->assignRole('manager');

    $foreignFolder = PhonebookFolder::factory()->create(['company_id' => $companyB->id]);

    $this->actingAs($userA)->getJson("/api/v1/phonebook-folders/{$foreignFolder->uuid}")->assertNotFound();
});
