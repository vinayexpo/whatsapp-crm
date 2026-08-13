<?php

namespace App\Services\Phonebook;

use App\Models\Contact;
use App\Models\PhonebookFolder;
use App\Models\PipelineStage;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ContactImportService
{
    private const HEADER_MAP = [
        'name' => 'name',
        'channel' => 'channel',
        'handle' => 'handle',
        'phone' => 'phone',
        'email' => 'email',
    ];

    /**
     * @return array{created: int, attached: int, skipped: int, errors: array<int, array{row: int, message: string}>}
     */
    public function import(UploadedFile $file, PhonebookFolder $folder): array
    {
        $rows = $this->parseRows($file);

        $summary = ['created' => 0, 'attached' => 0, 'skipped' => 0, 'errors' => []];
        $defaultPipelineStageId = PipelineStage::query()->orderBy('position')->value('id');

        foreach ($rows as $row) {
            $error = $this->validateRow($row);

            if ($error !== null) {
                $summary['errors'][] = ['row' => $row->rowNumber, 'message' => $error];

                continue;
            }

            $contact = Contact::query()
                ->where('channel', $row->channel)
                ->where('handle', $row->handle)
                ->first();

            if ($contact) {
                $summary['skipped']++;
            } else {
                $contact = Contact::query()->create([
                    'name' => $row->name,
                    'channel' => $row->channel,
                    'handle' => $row->handle,
                    'phone' => $row->phone,
                    'email' => $row->email,
                    'pipeline_stage_id' => $defaultPipelineStageId,
                    'deal_value' => 0,
                    'last_interaction_at' => now(),
                    'notes' => [],
                    'purchases' => [],
                ]);
                $summary['created']++;
            }

            if ($folder->contacts()->where('contact_id', $contact->id)->doesntExist()) {
                $folder->contacts()->attach($contact->id);
                $summary['attached']++;
            }
        }

        return $summary;
    }

    /**
     * @return array<int, ContactImportRow>
     */
    private function parseRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        $sheetRows = $extension === 'xlsx'
            ? $this->parseXlsx($file)
            : $this->parseCsv($file);

        if (empty($sheetRows)) {
            return [];
        }

        $header = array_map(
            fn ($value) => strtolower(trim((string) $value)),
            array_shift($sheetRows)
        );

        $columnIndex = [];
        foreach (self::HEADER_MAP as $expected => $field) {
            $index = array_search($expected, $header, true);
            if ($index !== false) {
                $columnIndex[$field] = $index;
            }
        }

        $rows = [];
        foreach ($sheetRows as $offset => $line) {
            if (array_filter($line, fn ($value) => trim((string) $value) !== '') === []) {
                continue;
            }

            $get = fn (string $field): ?string => isset($columnIndex[$field], $line[$columnIndex[$field]])
                ? trim((string) $line[$columnIndex[$field]]) ?: null
                : null;

            $rows[] = new ContactImportRow(
                rowNumber: $offset + 2,
                name: $get('name'),
                channel: $get('channel') ? strtolower($get('channel')) : null,
                handle: $get('handle'),
                phone: $get('phone'),
                email: $get('email'),
            );
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function parseCsv(UploadedFile $file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');

        while (($line = fgetcsv($handle)) !== false) {
            $rows[] = $line;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function parseXlsx(UploadedFile $file): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file->getRealPath());

        return $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
    }

    private function validateRow(ContactImportRow $row): ?string
    {
        if (! $row->name) {
            return 'Name is required.';
        }

        if (! in_array($row->channel, ['whatsapp', 'instagram'], true)) {
            return 'Channel must be whatsapp or instagram.';
        }

        if (! $row->handle) {
            return 'Handle is required.';
        }

        return null;
    }
}
