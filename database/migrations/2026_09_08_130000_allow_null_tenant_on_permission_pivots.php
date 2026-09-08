<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replica el esquema de VetSaaS (`2026_07_19_110000_add_tenant_id_to_permission_teams_tables`):
 * tenant_id UUID nullable en los pivotes, fuera de la PRIMARY KEY.
 * PostgreSQL no permite NULL en columnas de PK; el superadmin usa tenant_id null.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        $this->alignPivotLikeVetsaas(
            table: 'model_has_roles',
            primary: 'model_has_roles_role_model_type_primary',
            primaryColumns: ['role_id', 'model_id', 'model_type'],
            tenantIndex: 'model_has_roles_tenant_id_index',
        );

        $this->alignPivotLikeVetsaas(
            table: 'model_has_permissions',
            primary: 'model_has_permissions_permission_model_type_primary',
            primaryColumns: ['permission_id', 'model_id', 'model_type'],
            tenantIndex: 'model_has_permissions_tenant_id_index',
        );
    }

    public function down(): void
    {
        // Irreversible: volver a exigir tenant_id en la PK rompería el superadmin.
    }

    /**
     * @param  list<string>  $primaryColumns
     */
    private function alignPivotLikeVetsaas(
        string $table,
        string $primary,
        array $primaryColumns,
        string $tenantIndex,
    ): void {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
            return;
        }

        $this->dropPrimaryKey($table);

        DB::statement(sprintf(
            'ALTER TABLE %s ALTER COLUMN %s DROP NOT NULL',
            $this->quoteIdent($table),
            $this->quoteIdent('tenant_id'),
        ));

        $quotedPrimaryCols = implode(', ', array_map(
            fn (string $column) => $this->quoteIdent($column),
            $primaryColumns,
        ));

        DB::statement(sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s PRIMARY KEY (%s)',
            $this->quoteIdent($table),
            $this->quoteIdent($primary),
            $quotedPrimaryCols,
        ));

        if (! $this->indexExists($tenantIndex)) {
            Schema::table($table, function (Blueprint $blueprint) use ($tenantIndex): void {
                $blueprint->index('tenant_id', $tenantIndex);
            });
        }
    }

    private function dropPrimaryKey(string $table): void
    {
        $row = DB::selectOne(
            'SELECT c.conname
             FROM pg_constraint c
             JOIN pg_class t ON t.oid = c.conrelid
             JOIN pg_namespace n ON n.oid = t.relnamespace
             WHERE c.contype = \'p\'
               AND n.nspname = current_schema()
               AND t.relname = ?',
            [$table],
        );

        if ($row === null || ! is_string($row->conname) || $row->conname === '') {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE %s DROP CONSTRAINT %s',
            $this->quoteIdent($table),
            $this->quoteIdent($row->conname),
        ));
    }

    private function indexExists(string $name): bool
    {
        $row = DB::selectOne(
            'SELECT 1 AS ok FROM pg_indexes WHERE schemaname = current_schema() AND indexname = ? LIMIT 1',
            [$name],
        );

        return $row !== null;
    }

    private function quoteIdent(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }
};
