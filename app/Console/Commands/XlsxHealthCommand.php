<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exports\ContactsImportTemplateXlsx;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;
use ZipArchive;

class XlsxHealthCommand extends Command
{
    protected $signature = 'sendsaas:xlsx-health';

    protected $description = 'Diagnostica plantilla e import de Excel (zip, archivo estático, lectura).';

    public function handle(): int
    {
        $template = resource_path('templates/plantilla-contactos.xlsx');
        $tmpDir = storage_path('app/tmp');
        $log = storage_path('logs/laravel.log');

        $this->info('Diagnóstico Excel / plantilla de contactos');
        $this->newLine();

        $this->line('php:        '.PHP_VERSION);
        $this->line('ext-zip:    '.(class_exists(ZipArchive::class) ? 'OK' : 'FALTA (php-zip)'));
        $this->line('ext-gd:     '.(extension_loaded('gd') ? 'OK' : 'no'));
        $this->line('temp dir:   '.sys_get_temp_dir());
        $this->line('storage tmp: '.$tmpDir.(is_dir($tmpDir) && is_writable($tmpDir) ? ' (escribible)' : ' (NO escribible o no existe)'));
        $this->line('template:   '.$template);
        $this->line('  exists:   '.(is_file($template) ? 'sí ('.filesize($template).' bytes)' : 'NO'));
        $this->line('  readable: '.(is_readable($template) ? 'sí' : 'no'));
        if (is_file($template)) {
            $magic = (string) @file_get_contents($template, false, null, 0, 4);
            $this->line('  magic:    '.$this->describeMagic($magic));
        }

        $this->newLine();
        $this->line('Lectura de la plantilla estática:');
        if (! is_readable($template)) {
            $this->error('  no hay archivo estático → GET /contactos/plantilla intenta generarlo (ahí suele ir el 500).');
        } else {
            try {
                $reader = IOFactory::createReader(IOFactory::READER_XLSX);
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($template);
                $sheet = $spreadsheet->getSheet(0);
                $this->info('  OK  hoja='.$sheet->getTitle().'  A1='.(string) $sheet->getCell('A1')->getValue());
                $spreadsheet->disconnectWorksheets();
            } catch (Throwable $e) {
                $this->error('  FALLO '.$e::class.': '.$e->getMessage());
            }
        }

        $this->newLine();
        $this->line('Generación en caliente (PhpSpreadsheet + ZipStream):');
        $probe = $tmpDir.DIRECTORY_SEPARATOR.'health-probe.xlsx';
        if (! is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }
        try {
            (new ContactsImportTemplateXlsx)->streamTo($probe);
            $ok = is_file($probe) && str_starts_with((string) file_get_contents($probe), 'PK');
            $this->line($ok
                ? '  OK  '.filesize($probe).' bytes  '.$this->describeMagic((string) file_get_contents($probe, false, null, 0, 4))
                : '  FALLO archivo vacío o sin firma ZIP');
        } catch (Throwable $e) {
            $this->error('  FALLO '.$e::class.': '.$e->getMessage());
            $this->line('  '.$e->getFile().':'.$e->getLine());
        } finally {
            if (is_file($probe)) {
                @unlink($probe);
            }
        }

        $this->newLine();
        $this->line('Últimas líneas de storage/logs/laravel.log con [xlsx] o Excel:');
        if (! is_file($log)) {
            $this->warn('  no existe '.$log);
        } else {
            $lines = file($log, FILE_IGNORE_NEW_LINES) ?: [];
            $hits = [];
            foreach ($lines as $line) {
                if (str_contains($line, '[xlsx]') || str_contains($line, 'Excel') || str_contains($line, 'plantilla') || str_contains($line, 'ZipArchive') || str_contains($line, 'Spreadsheet')) {
                    $hits[] = $line;
                }
            }
            $tail = array_slice($hits, -15);
            if ($tail === []) {
                $this->line('  (sin coincidencias; revisa el log crudo con tail)');
            } else {
                foreach ($tail as $line) {
                    $this->line('  '.mb_substr($line, 0, 240));
                }
            }
        }

        return self::SUCCESS;
    }

    private function describeMagic(string $bytes): string
    {
        $hex = bin2hex($bytes);
        if (str_starts_with($bytes, 'PK')) {
            return 'PK (zip/xlsx) hex='.$hex;
        }
        if (str_starts_with(ltrim($bytes), '<')) {
            return 'HTML hex='.$hex;
        }

        return 'otro hex='.$hex;
    }
}
