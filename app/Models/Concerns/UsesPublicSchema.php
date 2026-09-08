<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Fuerza consultas al schema `public` aunque el tenant haya fijado search_path.
 *
 * Necesario para modelos globales (tenants, users, planes, roles…) cuando
 * un request de empresa ejecuta con `SET search_path TO "od_xxx", public`.
 */
trait UsesPublicSchema
{
    public function getTable(): string
    {
        $table = parent::getTable();

        if (DB::getDriverName() === 'pgsql' && ! str_contains($table, '.')) {
            return 'public.'.$table;
        }

        return $table;
    }
}
