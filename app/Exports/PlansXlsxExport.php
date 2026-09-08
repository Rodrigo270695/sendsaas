<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Plan;
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

class PlansXlsxExport
{
    /**
     * @var array<int, array{label: string, value: \Closure(Plan): mixed}>
     */
    private array $columns;

    public function __construct()
    {
        $this->columns = [
            [
                'label' => 'Código',
                'value' => fn (Plan $plan) => (string) $plan->codigo,
            ],
            [
                'label' => 'Nombre',
                'value' => fn (Plan $plan) => (string) $plan->nombre,
            ],
            [
                'label' => 'Badge',
                'value' => fn (Plan $plan) => (string) ($plan->badge ?? ''),
            ],
            [
                'label' => 'Usuarios',
                'value' => fn (Plan $plan) => $this->intLabel($plan, 'max_usuarios'),
            ],
            [
                'label' => 'Sesiones de WhatsApp',
                'value' => fn (Plan $plan) => $this->intLabel($plan, 'max_whatsapp_sessions'),
            ],
            [
                'label' => 'Mensajes/día',
                'value' => fn (Plan $plan) => $this->intLabel($plan, 'max_outbound_per_day'),
            ],
            [
                'label' => 'Precio mensual',
                'value' => fn (Plan $plan) => 'S/. '.number_format((float) $plan->precio_mensual, 2, '.', ','),
            ],
            [
                'label' => 'Precio anual',
                'value' => fn (Plan $plan) => 'S/. '.number_format(
                    (float) ($plan->precio_anual ?? Plan::precioAnualDesdeMensual($plan->precio_mensual)),
                    2,
                    '.',
                    ',',
                ),
            ],
            [
                'label' => 'Público',
                'value' => fn (Plan $plan) => $plan->es_publico ? 'Sí' : 'No',
            ],
            [
                'label' => 'Activo',
                'value' => fn (Plan $plan) => $plan->activo ? 'Sí' : 'No',
            ],
            [
                'label' => 'Features',
                'value' => fn (Plan $plan) => (string) $plan->features()->count(),
            ],
            [
                'label' => 'Orden',
                'value' => fn (Plan $plan) => (string) $plan->orden,
            ],
            [
                'label' => 'Creado en',
                'value' => fn (Plan $plan) => optional($plan->created_at)->format('Y-m-d H:i'),
            ],
        ];
    }

    /**
     * @param  Builder<Plan>  $query
     */
    public function streamTo(Builder $query, mixed $output = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('SendSaaS')
            ->setTitle('Planes')
            ->setSubject('Catálogo de planes y cupos');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Planes');

        $columnCount = count($this->columns);
        $lastColumnLetter = Coordinate::stringFromColumnIndex($columnCount);

        $sheet->setCellValue('A1', 'Planes');
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
        /** @var Plan $plan */
        foreach ($query->with('features')->cursor() as $plan) {
            foreach ($this->columns as $index => $col) {
                $colLetter = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValueExplicit(
                    "{$colLetter}{$row}",
                    ($col['value'])($plan),
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

    private function intLabel(Plan $plan, string $feature): string
    {
        $value = $plan->resolveFeature($feature);

        if (! is_int($value) || $value < 0) {
            return 'Ilimitado';
        }

        return (string) $value;
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
        $table = new Table($tableRange, 'TablaPlanes');
        $table->setStyle(new TableStyle(TableStyle::TABLE_STYLE_MEDIUM2));
        $sheet->addTable($table);
    }
}
