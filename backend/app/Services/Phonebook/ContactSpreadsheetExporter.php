<?php

namespace App\Services\Phonebook;

use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactSpreadsheetExporter
{
    private const HEADERS = ['name', 'channel', 'handle', 'phone', 'email'];

    public function template(): StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet(self::HEADERS, []);

        return $this->streamXlsx($spreadsheet, 'phonebook-template.xlsx');
    }

    /**
     * @param  Collection<int, \App\Models\Contact>  $contacts
     */
    public function export(Collection $contacts, string $format, string $filenameBase): StreamedResponse
    {
        $rows = $contacts->map(fn ($contact) => [
            $contact->name,
            $contact->channel,
            $contact->handle,
            $contact->phone,
            $contact->email,
        ])->all();

        $spreadsheet = $this->buildSpreadsheet(self::HEADERS, $rows);

        return $format === 'csv'
            ? $this->streamCsv($spreadsheet, "{$filenameBase}.csv")
            : $this->streamXlsx($spreadsheet, "{$filenameBase}.xlsx");
    }

    private function buildSpreadsheet(array $headers, array $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $sheet->setCellValueExplicit(
                    $column.($rowIndex + 2),
                    (string) ($value ?? ''),
                    DataType::TYPE_STRING
                );
            }
        }

        return $spreadsheet;
    }

    private function streamXlsx(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function streamCsv(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $writer = new Csv($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
