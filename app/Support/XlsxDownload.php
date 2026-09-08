<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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
        if (\function_exists('ini_set')) {
            @ini_set('zlib.output_compression', '0');
        }

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

        $safeName = basename(str_replace(['"', "\r", "\n"], '', $filename));

        $response = response()->download($path, $safeName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Transfer-Encoding' => 'binary',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, no-transform',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Content-Encoding' => 'identity',
            'X-Accel-Buffering' => 'no',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $response->headers->set('Content-Length', (string) filesize($path));
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $safeName,
            $safeName,
        );

        // deleteFileAfterSend() corre antes de fastcgi_finish_request: nginx
        // puede cortar el body y Chrome muestra "El sitio no se encontraba disponible".
        $response->deleteFileAfterSend(false);
        register_shutdown_function(static function () use ($path): void {
            if (is_file($path)) {
                @unlink($path);
            }
        });

        return $response;
    }
}
