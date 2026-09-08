<?php

declare(strict_types=1);

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ContactsImportTemplateXlsx
{
    public function streamTo(string $output = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('SendSaaS')
            ->setTitle('Plantilla contactos');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Contactos');

        $headers = ['telefono*', 'nombre*', 'email', 'notas', 'sede', 'etiquetas', 'pedido', 'fecha'];
        foreach ($headers as $i => $header) {
            $col = chr(ord('A') + $i);
            $sheet->setCellValue($col.'1', $header);
        }

        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'AB3C3D'],
            ],
        ]);

        $example = ['987654321', 'Ana Pérez', 'ana@empresa.pe', 'Cliente frecuente', 'SEDE-001', 'VIP, Lima', 'A-102', '12/09'];
        foreach ($example as $i => $value) {
            $col = chr(ord('A') + $i);
            $sheet->setCellValueExplicit($col.'2', $value, DataType::TYPE_STRING);
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $guide = $spreadsheet->createSheet();
        $guide->setTitle('Guia');
        $guide->setCellValue('A1', 'Cómo importar contactos');
        $guide->setCellValue('A3', 'Obligatorios: telefono* y nombre*.');
        $guide->setCellValue('A4', 'Teléfono: 9 dígitos (987654321) o con código de país (51987654321).');
        $guide->setCellValue('A5', 'Si el número ya existe, se actualiza. No se duplica.');
        $guide->setCellValue('A6', 'sede: código de sucursal (SEDE-001). Déjalo vacío si no aplica.');
        $guide->setCellValue('A7', 'etiquetas: separadas por coma (VIP, Lima).');
        $guide->setCellValue('A8', 'Cualquier otra columna (pedido, fecha, monto…) se guarda como variable para campañas {{pedido}}.');
        $guide->setCellValue('A9', 'Borra la fila de ejemplo antes de subir, o déjala: se omite sola.');
        $guide->getColumnDimension('A')->setWidth(90);
        $guide->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $spreadsheet->setActiveSheetIndex(0);
        $writer = new Xlsx($spreadsheet);
        $writer->save($output);
        $spreadsheet->disconnectWorksheets();
    }
}
