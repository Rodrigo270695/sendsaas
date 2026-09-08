<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ContactsXlsxExport
{
    /**
     * @param  Builder<Contact>  $query
     */
    public function streamTo(Builder $query, string $output = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Contactos');

        $headers = ['telefono', 'nombre', 'email', 'notas', 'sede', 'etiquetas', 'variables'];
        foreach ($headers as $i => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $header);
        }
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'AB3C3D'],
            ],
        ]);

        $row = 2;
        /** @var Contact $contact */
        foreach ($query->with(['sede:id,codigo', 'tags:id,name'])->cursor() as $contact) {
            $custom = $contact->custom_fields ?? [];
            $vars = [];
            foreach ($custom as $key => $value) {
                $vars[] = $key.'='.$value;
            }

            $values = [
                (string) $contact->phone,
                (string) $contact->name,
                (string) ($contact->email ?? ''),
                (string) ($contact->notes ?? ''),
                (string) ($contact->sede?->codigo ?? ''),
                $contact->tags->pluck('name')->implode(', '),
                implode(' | ', $vars),
            ];

            foreach ($values as $i => $value) {
                $sheet->setCellValueExplicit(
                    Coordinate::stringFromColumnIndex($i + 1).$row,
                    $value,
                    DataType::TYPE_STRING,
                );
            }
            $row++;
        }

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($output);
        $spreadsheet->disconnectWorksheets();
    }
}
