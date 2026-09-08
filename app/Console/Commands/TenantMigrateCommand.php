<?php

namespace App\Console\Commands;

use App\Tenancy\TenantSchemaMigrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TenantMigrateCommand extends Command
{
    protected $signature = 'sendsaas:tenant-migrate
                            {schema : Nombre del schema PostgreSQL (ej. od_demo)}
                            {--replay : Reinicia el historial tenant y vuelve a migrar (solo desarrollo)}
                            {--wipe : DROP SCHEMA CASCADE + recrear vacío (solo desarrollo)}';

    protected $description = 'Ejecuta las migraciones de database/migrations/tenant en el schema indicado.';

    public function handle(TenantSchemaMigrator $migrator): int
    {
        $schema = (string) $this->argument('schema');

        if (DB::getDriverName() !== 'pgsql') {
            $this->error('Solo está soportado PostgreSQL para multi-schema tenant.');

            return self::FAILURE;
        }

        $wipe = (bool) $this->option('wipe');
        $replay = (bool) $this->option('replay');

        if (($wipe || $replay) && app()->isProduction()) {
            $this->error('En producción --wipe/--replay están deshabilitados.');

            return self::FAILURE;
        }

        $code = $migrator->migrate($schema, $this->output, $wipe, $replay);

        return $code === TenantSchemaMigrator::EXIT_SUCCESS ? self::SUCCESS : self::FAILURE;
    }
}
