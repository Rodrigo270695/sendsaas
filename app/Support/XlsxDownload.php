<?php

declare(strict_types=1);

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Throwable;

/**
 * Genera un XLSX en un stream seekable y lo descarga.
 *
 * ZipStream (PhpSpreadsheet 5) no puede cerrar el ZIP sobre `php://output`
 * ni sobre archivos abiertos en modo `wb` no seekable: Chrome/PHP-FPM
 * responden 500 o "El sitio no se encontraba disponible".
 */
final class XlsxDownload
{
    /**
     * @param  callable(mixed): void  $writer  Recibe un stream seekable (php://temp) o una ruta.
     */
    public static function from(callable $writer, string $filename): BinaryFileResponse
    {
        self::disableCompression();

        $dir = storage_path('app/tmp');
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            abort(500, 'No se pudo crear el directorio temporal de Excel.');
        }

        $path = $dir.DIRECTORY_SEPARATOR.str_replace('.', '', uniqid('xlsx-', true)).'.xlsx';

        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            abort(500, 'No se pudo crear el buffer temporal de Excel.');
        }

        try {
            try {
                $writer($stream);
                rewind($stream);
                $bytes = stream_get_contents($stream);
            } catch (Throwable $e) {
                if (! $e instanceof \TypeError) {
                    throw $e;
                }

                $writer($path);
                $bytes = is_file($path) ? file_get_contents($path) : false;
            }
        } catch (Throwable $e) {
            report($e);
            abort(500, 'No se pudo generar el Excel.');
        } finally {
            fclose($stream);
        }

        if (! is_string($bytes) || $bytes === '' || ! str_starts_with($bytes, 'PK')) {
            if (is_file($path)) {
                @unlink($path);
            }

            abort(500, 'El Excel quedó vacío.');
        }

        if (file_put_contents($path, $bytes) === false) {
            abort(500, 'No se pudo guardar el Excel temporal.');
        }

        return self::existing($path, $filename, deleteAfterSend: true);
    }

    public static function existing(string $path, string $filename, bool $deleteAfterSend = false): BinaryFileResponse
    {
        self::disableCompression();

        abort_unless(is_readable($path) && (int) filesize($path) > 0, 500, 'No se encontró el Excel.');

        $safeName = basename(str_replace(['"', "\r", "\n"], '', $filename));

        $response = response()->download($path, $safeName, self::headers());
        $response->headers->set('Content-Length', (string) filesize($path));
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $safeName,
            $safeName,
        );

        $response->deleteFileAfterSend(false);

        if ($deleteAfterSend) {
            register_shutdown_function(static function () use ($path): void {
                if (is_file($path)) {
                    @unlink($path);
                }
            });
        }

        return $response;
    }

    public static function saveSpreadsheet(Spreadsheet $spreadsheet, mixed $output): void
    {
        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($output);
        $spreadsheet->disconnectWorksheets();
    }

    /** @return array<string, string> */
    private static function headers(): array
    {
        return [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Transfer-Encoding' => 'binary',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, no-transform',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Content-Encoding' => 'identity',
            'X-Accel-Buffering' => 'no',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    private static function disableCompression(): void
    {
        if (\function_exists('ini_set')) {
            @ini_set('zlib.output_compression', '0');
        }
    }
}
