<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\User;
use App\Support\XlsxDownload;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export XLSX de Usuarios. Misma estructura que VetSaaS
 * (título, metadatos, cabecera, tabla nativa, freeze, autosize)
 * con la paleta burdeos de SendSaaS.
 */
class UsersXlsxExport
{
    /**
     * @var array<int, array{label: string, value: \Closure(User): mixed}>
     */
    private array $columns;

    public function __construct()
    {
        $this->columns = [
            [
                'label' => 'Nombre',
                'value' => fn (User $user) => (string) $user->name,
            ],
            [
                'label' => 'Email',
                'value' => fn (User $user) => (string) $user->email,
            ],
            [
                'label' => 'Teléfono',
                'value' => fn (User $user) => (string) ($user->phone ?? ''),
            ],
            [
                'label' => 'Rol',
                'value' => fn (User $user) => (string) ($user->roles->first()?->name ?? '—'),
            ],
            [
                'label' => 'Estado',
                'value' => fn (User $user) => $user->is_active ? 'Activo' : 'Suspendido',
            ],
            [
                'label' => 'Email verificado',
                'value' => fn (User $user) => $user->email_verified_at !== null ? 'Sí' : 'No',
            ],
            [
                'label' => 'Último acceso',
                'value' => fn (User $user) => optional($user->last_login_at)->format('Y-m-d H:i') ?? '—',
            ],
            [
                'label' => 'Creado por',
                'value' => fn (User $user) => (string) ($user->createdBy?->name ?? '—'),
            ],
            [
                'label' => 'Creado en',
                'value' => fn (User $user) => optional($user->created_at)->format('Y-m-d H:i'),
            ],
        ];
    }

    /**
     * @param  Builder<User>  $query
     */
    public function streamTo(Builder $query, mixed $output = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('SendSaaS')
            ->setTitle('Usuarios')
            ->setSubject('Listado de usuarios');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Usuarios');

        $columnCount = count($this->columns);
        $lastColumnLetter = Coordinate::stringFromColumnIndex($columnCount);

        $sheet->setCellValue('A1', 'Usuarios');
        $sheet->mergeCells("A1:{$lastColumnLetter}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => '8C2F30'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $sheet->setCellValue(
            'A2',
            sprintf(
                'Exportado el %s · %d registros',
                now()->format('d/m/Y H:i'),
                (clone $query)->toBase()->getCountForPagination(),
            ),
        );
        $sheet->mergeCells("A2:{$lastColumnLetter}2");
        $sheet->getStyle('A2')->applyFromArray([
            'font' => [
                'italic' => true,
                'size' => 10,
                'color' => ['rgb' => '6B7280'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $headerRow = 4;
        $dataStartRow = $headerRow + 1;

        foreach ($this->columns as $index => $col) {
            $colLetter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue("{$colLetter}{$headerRow}", $col['label']);
        }

        $row = $dataStartRow;
        /** @var User $user */
        foreach ($query->cursor() as $user) {
            foreach ($this->columns as $index => $col) {
                $colLetter = Coordinate::stringFromColumnIndex($index + 1);
                $value = ($col['value'])($user);
                $sheet->setCellValueExplicit(
                    "{$colLetter}{$row}",
                    $value,
                    DataType::TYPE_STRING,
                );
            }
            $row++;
        }

        $lastDataRow = max($dataStartRow, $row - 1);

        $this->styleTable($sheet, $lastColumnLetter, $headerRow, $lastDataRow);

        $sheet->freezePane('A'.($headerRow + 1));

        foreach (range('A', $lastColumnLetter) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        XlsxDownload::saveSpreadsheet($spreadsheet, $output);
        unset($spreadsheet);
    }

    private function styleTable(
        Worksheet $sheet,
        string $lastColumn,
        int $headerRow,
        int $lastDataRow,
    ): void {
        $headerRange = "A{$headerRow}:{$lastColumn}{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'AB3C3D'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '8C2F30'],
                ],
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(28);

        if ($lastDataRow >= $headerRow + 1) {
            $dataRange = 'A'.($headerRow + 1).":{$lastColumn}{$lastDataRow}";
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'E5E7EB'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => false,
                ],
            ]);
        }

        $tableRange = "A{$headerRow}:{$lastColumn}".max($headerRow + 1, $lastDataRow);
        $table = new Table($tableRange, 'TablaUsuarios');
        $table->setStyle(new TableStyle(TableStyle::TABLE_STYLE_MEDIUM2));
        $sheet->addTable($table);
    }
}
