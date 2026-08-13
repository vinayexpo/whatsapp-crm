<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\PhonebookFolder;
use App\Models\User;
use Database\Seeders\PipelineStagesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PipelineStagesSeeder::class);
});

function actingAsImportRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function makeCsvUpload(array $rows, string $filename = 'contacts.csv'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
    $handle = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);

    return new UploadedFile($path, $filename, 'text/csv', null, true);
}

it('imports new contacts into a newly named folder', function () {
    $admin = actingAsImportRole('admin');

    $file = makeCsvUpload([
        ['name', 'channel', 'handle', 'phone', 'email'],
        ['Ada Lovelace', 'whatsapp', '+15550001111', '+15550001111', 'ada@example.com'],
        ['Grace Hopper', 'instagram', '@gracehopper', '', ''],
    ]);

    $response = $this->actingAs($admin)->postJson('/api/v1/phonebook-folders/import', [
        'file' => $file,
        'folderName' => 'Imported Leads',
    ]);

    $response->assertOk();
    expect($response->json('summary.created'))->toBe(2);
    expect($response->json('summary.attached'))->toBe(2);
    expect($response->json('summary.skipped'))->toBe(0);
    expect($response->json('summary.errors'))->toBe([]);
    expect($response->json('data.name'))->toBe('Imported Leads');
    expect($response->json('data.contactCount'))->toBe(2);

    $this->assertDatabaseHas('contacts', ['handle' => '+15550001111']);
    $this->assertDatabaseHas('phonebook_folders', ['name' => 'Imported Leads']);
});

function makeXlsxUpload(array $rows, string $filename = 'contacts.xlsx'): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();

    foreach ($rows as $rowIndex => $row) {
        foreach ($row as $colIndex => $value) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->setCellValueExplicit(
                $column.($rowIndex + 1),
                (string) $value,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
        }
    }

    $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('imports new contacts from an xlsx file', function () {
    $admin = actingAsImportRole('admin');

    $file = makeXlsxUpload([
        ['name', 'channel', 'handle', 'phone', 'email'],
        ['Ada Lovelace', 'whatsapp', '+15550001111', '+15550001111', 'ada@example.com'],
    ]);

    $response = $this->actingAs($admin)->postJson('/api/v1/phonebook-folders/import', [
        'file' => $file,
        'folderName' => 'Xlsx Import',
    ]);

    $response->assertOk();
    expect($response->json('summary.created'))->toBe(1);
    $this->assertDatabaseHas('contacts', ['handle' => '+15550001111']);
});

it('imports contacts into an existing folder', function () {
    $admin = actingAsImportRole('admin');
    $folder = PhonebookFolder::factory()->create(['name' => 'Existing Folder']);

    $file = makeCsvUpload([
        ['name', 'channel', 'handle', 'phone', 'email'],
        ['Ada Lovelace', 'whatsapp', '+15550001111', '', ''],
    ]);

    $response = $this->actingAs($admin)->postJson('/api/v1/phonebook-folders/import', [
        'file' => $file,
        'folderId' => $folder->uuid,
    ]);

    $response->assertOk();
    expect($response->json('data.id'))->toBe($folder->uuid);
    expect($folder->fresh()->contacts()->count())->toBe(1);
});

it('skips and attaches when a matching contact already exists', function () {
    $admin = actingAsImportRole('admin');
    $folder = PhonebookFolder::factory()->create();
    $existing = Contact::factory()->create(['channel' => 'whatsapp', 'handle' => '+15550001111', 'name' => 'Original Name']);

    $file = makeCsvUpload([
        ['name', 'channel', 'handle', 'phone', 'email'],
        ['Duplicate Name', 'whatsapp', '+15550001111', '', ''],
    ]);

    $response = $this->actingAs($admin)->postJson('/api/v1/phonebook-folders/import', [
        'file' => $file,
        'folderId' => $folder->uuid,
    ]);

    $response->assertOk();
    expect($response->json('summary.created'))->toBe(0);
    expect($response->json('summary.skipped'))->toBe(1);
    expect($response->json('summary.attached'))->toBe(1);

    expect($existing->fresh()->name)->toBe('Original Name');
    expect($folder->contacts()->where('contact_id', $existing->id)->exists())->toBeTrue();
});

it('reports per-row errors for invalid rows without failing the whole import', function () {
    $admin = actingAsImportRole('admin');
    $folder = PhonebookFolder::factory()->create();

    $file = makeCsvUpload([
        ['name', 'channel', 'handle', 'phone', 'email'],
        ['Missing Channel', '', '+15550002222', '', ''],
        ['Valid Contact', 'whatsapp', '+15550003333', '', ''],
    ]);

    $response = $this->actingAs($admin)->postJson('/api/v1/phonebook-folders/import', [
        'file' => $file,
        'folderId' => $folder->uuid,
    ]);

    $response->assertOk();
    expect($response->json('summary.created'))->toBe(1);
    expect($response->json('summary.errors'))->toHaveCount(1);
    expect($response->json('summary.errors.0.row'))->toBe(2);
});

it('rejects import when both folderId and folderName are provided', function () {
    $admin = actingAsImportRole('admin');
    $folder = PhonebookFolder::factory()->create();

    $file = makeCsvUpload([
        ['name', 'channel', 'handle', 'phone', 'email'],
        ['Ada Lovelace', 'whatsapp', '+15550001111', '', ''],
    ]);

    $this->actingAs($admin)->postJson('/api/v1/phonebook-folders/import', [
        'file' => $file,
        'folderId' => $folder->uuid,
        'folderName' => 'Another Name',
    ])->assertStatus(422);
});

it('forbids an agent from importing contacts', function () {
    $agent = actingAsImportRole('agent');

    $file = makeCsvUpload([
        ['name', 'channel', 'handle', 'phone', 'email'],
        ['Ada Lovelace', 'whatsapp', '+15550001111', '', ''],
    ]);

    $this->actingAs($agent)->postJson('/api/v1/phonebook-folders/import', [
        'file' => $file,
        'folderName' => 'Agent Folder',
    ])->assertForbidden();
});

it('scopes imported contacts to the caller company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $adminA = User::factory()->create(['company_id' => $companyA->id]);
    $adminA->assignRole('admin');

    $foreignFolder = PhonebookFolder::factory()->create(['company_id' => $companyB->id]);

    $file = makeCsvUpload([
        ['name', 'channel', 'handle', 'phone', 'email'],
        ['Ada Lovelace', 'whatsapp', '+15550001111', '', ''],
    ]);

    $this->actingAs($adminA)->postJson('/api/v1/phonebook-folders/import', [
        'file' => $file,
        'folderId' => $foreignFolder->uuid,
    ])->assertStatus(422);
});
