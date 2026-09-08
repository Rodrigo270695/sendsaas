<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Importa el dump de ubigeo (`database/data/ubieo.md`) y corrige tildes/ñ.
 *
 * El archivo viene de VetSaaS/TallerSaaS con mojibake (`PerÃº`, `BREÃ‘A`).
 * Tras insertar se repara con `sendsaas:geo-fix-encoding`.
 */
class GeoImportCommand extends Command
{
    protected $signature = 'sendsaas:geo-import
                            {--force : Reimporta aunque ya existan países}
                            {--skip-fix : No corrige tildes/ñ después de importar}';

    protected $description = 'Carga el catálogo geográfico (ubigeo) y repara tildes y eñes';

    public function handle(): int
    {
        $dumpPath = $this->resolveDumpPath();

        if ($dumpPath === null) {
            $this->error('No se encontró el dump de ubigeo. Esperado: database/data/ubieo.md');

            return self::FAILURE;
        }

        if (! Schema::hasTable('paises')) {
            $this->error('Primero ejecuta las migraciones (faltan las tablas de ubigeo).');

            return self::FAILURE;
        }

        $alreadyLoaded = DB::table('paises')->exists();

        if ($alreadyLoaded && ! $this->option('force')) {
            $this->info('El catálogo geográfico ya está cargado. Usa --force para reimportar.');

            if (! $this->option('skip-fix')) {
                return Artisan::call('sendsaas:geo-fix-encoding', [], $this->output);
            }

            return self::SUCCESS;
        }

        $this->info('Importando ubigeo desde '.$dumpPath.'…');

        if ($alreadyLoaded) {
            $this->warn('Vaciando catálogo existente (--force)…');
            $this->truncateCatalog();
        }

        $inserted = $this->importDump($dumpPath);
        $this->resetSequences();

        $this->info("Insertadas {$inserted} fila(s).");

        if (! $this->option('skip-fix')) {
            $this->newLine();
            $this->info('Corrigiendo tildes y eñes…');

            return Artisan::call('sendsaas:geo-fix-encoding', [], $this->output);
        }

        return self::SUCCESS;
    }

    private function resolveDumpPath(): ?string
    {
        foreach ([
            database_path('data/ubieo.md'),
            base_path('ubieo.md'),
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function truncateCatalog(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('TRUNCATE TABLE distritos, provincias, departamentos, paises RESTART IDENTITY CASCADE');

            return;
        }

        Schema::disableForeignKeyConstraints();
        DB::table('distritos')->delete();
        DB::table('provincias')->delete();
        DB::table('departamentos')->delete();
        DB::table('paises')->delete();
        Schema::enableForeignKeyConstraints();
    }

    private function importDump(string $path): int
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException("No se pudo leer {$path}");
        }

        $inserted = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                $sql = trim($line);

                if ($sql === '' || ! str_starts_with(strtoupper($sql), 'INSERT INTO')) {
                    continue;
                }

                DB::unprepared(rtrim($sql, ';').';');
                $inserted++;
            }
        } finally {
            fclose($handle);
        }

        return $inserted;
    }

    private function resetSequences(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['paises', 'departamentos', 'provincias', 'distritos'] as $table) {
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1))",
            );
        }
    }
}
