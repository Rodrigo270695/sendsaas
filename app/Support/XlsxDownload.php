<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Genera un XLSX en un archivo temporal (seekable) y lo descarga.
 *
 * ZipStream (PhpSpreadsheet 5) no puede cerrar el ZIP sobre `php://output`
 * en PHP-FPM: el stream no es seekable y Chrome aborta con
 * "El sitio no se encontraba disponible".
 */
final class XlsxDownload
{
    /**
     * @param  callable(string): void  $writer  Recibe la ruta temporal y escribe el xlsx.
     */
    public static function from(callable $writer, string $filename): BinaryFileResponse
    {
        $base = tempnam(sys_get_temp_dir(), 'sendsaas-xlsx-');
        if ($base === false) {
            abort(500, 'No se pudo crear el archivo temporal de Excel.');
        }

        $path = $base.'.xlsx';
        unlink($base);

        try {
            $writer($path);
        } catch (Throwable $e) {
            if (is_file($path)) {
                unlink($path);
            }

            throw $e;
        }

        if (! is_file($path) || filesize($path) === 0) {
            if (is_file($path)) {
                unlink($path);
            }

            abort(500, 'El Excel quedó vacío.');
        }

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ])->deleteFileAfterSend();
    }
}
