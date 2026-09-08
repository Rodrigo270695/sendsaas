<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Iguala users al modelo VetSaaS: UUID, tenant_id, perfil, lifecycle y soft delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->usersIdIsInteger()) {
            $this->rebuildUsersAsUuid();
        } else {
            $this->addMissingUserColumns();
        }

        $this->alignRelatedUserKeys();
        $this->addPasswordResetTenantColumn();
    }

    public function down(): void
    {
        // Irreversible a propósito: el id ya no es bigint.
    }

    private function usersIdIsInteger(): bool
    {
        $type = Schema::getColumnType('users', 'id');

        return in_array($type, ['integer', 'bigint', 'int', 'tinyint', 'int4', 'int8'], true);
    }

    private function rebuildUsersAsUuid(): void
    {
        $users = DB::table('users')->get();
        $idMap = [];

        foreach ($users as $user) {
            $idMap[(string) $user->id] = (string) Str::uuid();
        }

        Schema::disableForeignKeyConstraints();

        $this->dropForeignKeysOnColumn('passkeys', 'user_id');

        // Postgres no acepta UUID en columnas bigint: primero text, luego remap.
        $this->castRelatedUserKeysToText();
        $this->remapRelatedUserIds($idMap);

        Schema::drop('users');

        $this->createSaasUsersTable();

        foreach ($users as $user) {
            $newId = $idMap[(string) $user->id];
            $createdBy = $user->created_by_id ?? null;

            DB::table('users')->insert([
                'id' => $newId,
                'tenant_id' => $user->tenant_id ?? null,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
                'documento_tipo' => $user->documento_tipo ?? null,
                'documento_numero' => $user->documento_numero ?? null,
                'colegiatura' => $user->colegiatura ?? null,
                'cv_path' => $user->cv_path ?? null,
                'dni_file_path' => $user->dni_file_path ?? null,
                'firma_path' => $user->firma_path ?? null,
                'email_verified_at' => $user->email_verified_at,
                'password' => $user->password,
                'two_factor_secret' => $user->two_factor_secret ?? null,
                'two_factor_recovery_codes' => $user->two_factor_recovery_codes ?? null,
                'two_factor_confirmed_at' => $user->two_factor_confirmed_at ?? null,
                'is_active' => $user->is_active ?? true,
                'must_change_password' => $user->must_change_password ?? false,
                'bootstrap_login_token' => $user->bootstrap_login_token ?? null,
                'bootstrap_login_expires_at' => $user->bootstrap_login_expires_at ?? null,
                'last_login_at' => $user->last_login_at ?? null,
                'last_seen_at' => $user->last_seen_at ?? null,
                'last_path' => $user->last_path ?? null,
                'last_module' => $user->last_module ?? null,
                'last_path_at' => $user->last_path_at ?? null,
                'created_by_id' => $createdBy !== null ? ($idMap[(string) $createdBy] ?? null) : null,
                'remember_token' => $user->remember_token,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'deleted_at' => $user->deleted_at ?? null,
            ]);
        }

        Schema::enableForeignKeyConstraints();
    }

    private function createSaasUsersTable(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->string('documento_tipo', 10)->nullable();
            $table->string('documento_numero', 32)->nullable();
            $table->string('colegiatura', 40)->nullable();
            $table->string('cv_path', 500)->nullable();
            $table->string('dni_file_path', 500)->nullable();
            $table->string('firma_path', 500)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->string('bootstrap_login_token', 64)->nullable();
            $table->timestamp('bootstrap_login_expires_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_path', 512)->nullable();
            $table->string('last_module', 64)->nullable();
            $table->timestamp('last_path_at')->nullable();
            $table->uuid('created_by_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id', 'idx_users_tenant');
            $table->index('is_active');
            $table->index('created_by_id');
            $table->index('last_seen_at');
            $table->index('bootstrap_login_token');
            $table->unique('email');
        });

        if (Schema::hasTable('tenants')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
                $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    /**
     * @param  array<string, string>  $idMap
     */
    private function remapRelatedUserIds(array $idMap): void
    {
        if (Schema::hasTable('sessions')) {
            foreach (DB::table('sessions')->whereNotNull('user_id')->get() as $session) {
                $newId = $idMap[(string) $session->user_id] ?? null;
                DB::table('sessions')->where('id', $session->id)->update(['user_id' => $newId]);
            }
        }

        if (Schema::hasTable('passkeys')) {
            foreach (DB::table('passkeys')->get() as $passkey) {
                $newId = $idMap[(string) $passkey->user_id] ?? null;
                if ($newId === null) {
                    DB::table('passkeys')->where('id', $passkey->id)->delete();

                    continue;
                }

                DB::table('passkeys')->where('id', $passkey->id)->update(['user_id' => $newId]);
            }
        }

        foreach (['model_has_roles', 'model_has_permissions'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (DB::table($table)->where('model_type', 'App\\Models\\User')->get() as $row) {
                $newId = $idMap[(string) $row->model_id] ?? null;
                if ($newId === null) {
                    continue;
                }

                DB::table($table)
                    ->where('model_type', $row->model_type)
                    ->where('model_id', $row->model_id)
                    ->update(['model_id' => $newId]);
            }
        }
    }

    private function addMissingUserColumns(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'tenant_id')) {
                $table->uuid('tenant_id')->nullable()->after('id');
                $table->index('tenant_id', 'idx_users_tenant');
            }
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 32)->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'documento_tipo')) {
                $table->string('documento_tipo', 10)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('users', 'documento_numero')) {
                $table->string('documento_numero', 32)->nullable()->after('documento_tipo');
            }
            if (! Schema::hasColumn('users', 'colegiatura')) {
                $table->string('colegiatura', 40)->nullable()->after('documento_numero');
            }
            if (! Schema::hasColumn('users', 'cv_path')) {
                $table->string('cv_path', 500)->nullable()->after('colegiatura');
            }
            if (! Schema::hasColumn('users', 'dni_file_path')) {
                $table->string('dni_file_path', 500)->nullable()->after('cv_path');
            }
            if (! Schema::hasColumn('users', 'firma_path')) {
                $table->string('firma_path', 500)->nullable()->after('dni_file_path');
            }
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('password');
            }
            if (! Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('users', 'bootstrap_login_token')) {
                $table->string('bootstrap_login_token', 64)->nullable()->after('must_change_password');
                $table->index('bootstrap_login_token');
            }
            if (! Schema::hasColumn('users', 'bootstrap_login_expires_at')) {
                $table->timestamp('bootstrap_login_expires_at')->nullable()->after('bootstrap_login_token');
            }
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->index();
            }
            if (! Schema::hasColumn('users', 'last_path')) {
                $table->string('last_path', 512)->nullable();
            }
            if (! Schema::hasColumn('users', 'last_module')) {
                $table->string('last_module', 64)->nullable();
            }
            if (! Schema::hasColumn('users', 'last_path_at')) {
                $table->timestamp('last_path_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'created_by_id')) {
                $table->uuid('created_by_id')->nullable();
                $table->index('created_by_id');
            }
            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    private function alignRelatedUserKeys(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        $this->castColumnToUuid('sessions', 'user_id');
        $this->castColumnToUuid('passkeys', 'user_id', foreignToUsers: true);
        $this->castColumnToUuid('model_has_roles', 'model_id');
        $this->castColumnToUuid('model_has_permissions', 'model_id');
    }

    private function castRelatedUserKeysToText(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        $this->castColumnToText('sessions', 'user_id');
        $this->castColumnToText('passkeys', 'user_id');
        $this->castColumnToText('model_has_roles', 'model_id');
        $this->castColumnToText('model_has_permissions', 'model_id');
    }

    private function castColumnToText(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $type = Schema::getColumnType($table, $column);
        if (in_array($type, ['string', 'text', 'guid', 'uuid'], true)) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE %s ALTER COLUMN %s TYPE text USING %s::text',
            $this->quoteIdent($table),
            $this->quoteIdent($column),
            $this->quoteIdent($column),
        ));
    }

    private function castColumnToUuid(string $table, string $column, bool $foreignToUsers = false): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $type = Schema::getColumnType($table, $column);
        if (in_array($type, ['guid', 'uuid'], true)) {
            return;
        }

        if ($foreignToUsers) {
            $this->dropForeignKeysOnColumn($table, $column);
        }

        $this->castColumnToText($table, $column);

        $quotedTable = $this->quoteIdent($table);
        $quotedColumn = $this->quoteIdent($column);

        DB::statement("
            UPDATE {$quotedTable}
            SET {$quotedColumn} = NULL
            WHERE {$quotedColumn} IS NOT NULL
              AND {$quotedColumn} !~* '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$'
        ");

        if (! $this->columnIsNullable($table, $column)) {
            DB::table($table)->whereNull($column)->delete();
        }

        DB::statement("ALTER TABLE {$quotedTable} ALTER COLUMN {$quotedColumn} TYPE uuid USING {$quotedColumn}::uuid");

        if ($foreignToUsers) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->foreign($column)->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    private function columnIsNullable(string $table, string $column): bool
    {
        $columns = Schema::getColumns($table);

        foreach ($columns as $meta) {
            if (($meta['name'] ?? null) === $column) {
                return (bool) ($meta['nullable'] ?? true);
            }
        }

        return true;
    }

    private function quoteIdent(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }

    private function addPasswordResetTenantColumn(): void
    {
        if (! Schema::hasTable('password_reset_tokens') || Schema::hasColumn('password_reset_tokens', 'tenant_id')) {
            return;
        }

        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('email');
        });
    }

    private function dropForeignKeysOnColumn(string $table, string $column): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (! in_array($column, $foreignKey['columns'] ?? [], true)) {
                continue;
            }

            $name = $foreignKey['name'] ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($name) {
                $blueprint->dropForeign($name);
            });
        }
    }
};
