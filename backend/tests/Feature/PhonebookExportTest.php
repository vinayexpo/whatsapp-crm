<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\PhonebookFolder;
use App\Models\User;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

function actingAsExportRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function readXlsxContent(string $content): array
{
    $path = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    file_put_contents($path, $content);

    $spreadsheet = IOFactory::createReader('Xlsx')->load($path);
    $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);

    unlink($path);

    return $rows;
}

it('rejects unauthenticated template download', function () {
    $this->getJson('/api/v1/phonebook-folders/template')->assertUnauthorized();
});

it('downloads a blank xlsx template with the expected headers', function () {
    $user = actingAsExportRole('manager');

    $response = $this->actingAs($user)->get('/api/v1/phonebook-folders/template');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $rows = readXlsxContent($response->streamedContent());
    expect($rows[0])->toBe(['name', 'channel', 'handle', 'phone', 'email']);
    expect($rows)->toHaveCount(1);
});

it('exports a folder as xlsx containing only its contacts', function () {
    $user = actingAsExportRole('manager');
    $folder = PhonebookFolder::factory()->create(['name' => 'My Folder']);
    $inFolder = Contact::factory()->create(['name' => 'In Folder', 'channel' => 'whatsapp', 'handle' => '+15551112222']);
    $outsideFolder = Contact::factory()->create(['name' => 'Outside Folder']);
    $folder->contacts()->attach($inFolder->id);

    $response = $this->actingAs($user)->get("/api/v1/phonebook-folders/{$folder->uuid}/export");

    $response->assertOk();
    $rows = readXlsxContent($response->streamedContent());

    expect($rows)->toHaveCount(2);
    expect($rows[1][0])->toBe('In Folder');
    expect(collect($rows)->pluck(0)->contains('Outside Folder'))->toBeFalse();
});

it('exports a folder as csv when format=csv is requested', function () {
    $user = actingAsExportRole('manager');
    $folder = PhonebookFolder::factory()->create();
    $contact = Contact::factory()->create(['name' => 'Csv Contact']);
    $folder->contacts()->attach($contact->id);

    $response = $this->actingAs($user)->get("/api/v1/phonebook-folders/{$folder->uuid}/export?format=csv");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($response->streamedContent())->toContain('Csv Contact');
});

it('exports all contacts regardless of folder membership', function () {
    $user = actingAsExportRole('manager');
    Contact::factory()->create(['name' => 'Contact One']);
    Contact::factory()->create(['name' => 'Contact Two']);

    $response = $this->actingAs($user)->get('/api/v1/contacts/export');

    $response->assertOk();
    $rows = readXlsxContent($response->streamedContent());

    expect($rows)->toHaveCount(3);
    $names = collect($rows)->pluck(0)->all();
    expect($names)->toContain('Contact One', 'Contact Two');
});

it('scopes contacts export for an agent to only those with a conversation assigned to them', function () {
    $agent = actingAsExportRole('agent');

    $assignedContact = Contact::factory()->create(['name' => 'Assigned To Me']);
    Conversation::factory()->create(['contact_id' => $assignedContact->id, 'assigned_to' => $agent->id]);

    $otherContact = Contact::factory()->create(['name' => 'Not Assigned']);
    Conversation::factory()->create(['contact_id' => $otherContact->id, 'assigned_to' => null]);

    $response = $this->actingAs($agent)->get('/api/v1/contacts/export');

    $response->assertOk();
    $names = collect(readXlsxContent($response->streamedContent()))->pluck(0)->all();

    expect($names)->toContain('Assigned To Me');
    expect($names)->not->toContain('Not Assigned');
});

it('scopes folder export to the caller company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = User::factory()->create(['company_id' => $companyA->id]);
    $userA->assignRole('manager');

    $foreignFolder = PhonebookFolder::factory()->create(['company_id' => $companyB->id]);

    $this->actingAs($userA)->get("/api/v1/phonebook-folders/{$foreignFolder->uuid}/export")->assertNotFound();
});

it('scopes all-contacts export to the caller company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = User::factory()->create(['company_id' => $companyA->id]);
    $userA->assignRole('manager');

    Contact::factory()->create(['company_id' => $companyA->id, 'name' => 'Mine']);
    Contact::factory()->create(['company_id' => $companyB->id, 'name' => 'Theirs']);

    $response = $this->actingAs($userA)->get('/api/v1/contacts/export');

    $rows = readXlsxContent($response->streamedContent());
    $names = collect($rows)->pluck(0)->all();

    expect($names)->toContain('Mine');
    expect($names)->not->toContain('Theirs');
});
