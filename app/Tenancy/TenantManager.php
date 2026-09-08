<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Tenant;
use App\Tenancy\Exceptions\TenantNotFoundException;
use App\Tenancy\Exceptions\TenantSuspendedException;
use Illuminate\Support\Facades\DB;

class TenantManager
{
    protected ?TenantContext $current = null;

    public function current(): ?TenantContext
    {
        return $this->current;
    }

    public function check(): bool
    {
        return $this->current !== null;
    }

    public function id(): ?string
    {
        return $this->current?->id();
    }

    public function resolveBySlug(string $slug): TenantContext
    {
        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null) {
            throw new TenantNotFoundException($slug);
        }

        return $this->bootstrap($tenant);
    }

    public function resolveById(string $id): TenantContext
    {
        $tenant = Tenant::query()->whereKey($id)->first();

        if ($tenant === null) {
            throw new TenantNotFoundException($id);
        }

        return $this->bootstrap($tenant);
    }

    public function forget(): void
    {
        $this->current = null;

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }
    }

    protected function bootstrap(Tenant $tenant): TenantContext
    {
        $allowed = (array) config('tenant.allowed_states', ['active', 'trial', 'grace']);

        if (! in_array((string) $tenant->estado, $allowed, true)) {
            throw new TenantSuspendedException($tenant);
        }

        $schema = $this->safeSchemaName($tenant);

        if ($schema === null) {
            throw new TenantNotFoundException((string) ($tenant->slug ?? $tenant->getKey()));
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO "'.$schema.'", public');
        }

        return $this->current = new TenantContext($tenant, $schema, (string) $tenant->slug);
    }

    public static function schemaFromSlug(string $slug): string
    {
        $prefix = (string) config('tenant.schema_prefix', 'od_');
        $normalized = str_replace('-', '_', strtolower($slug));

        return $prefix.$normalized;
    }

    private function safeSchemaName(Tenant $tenant): ?string
    {
        $schema = strtolower(trim((string) $tenant->schema_name));
        $prefix = (string) config('tenant.schema_prefix', 'od_');

        if ($schema === '' || strlen($schema) > 63) {
            return null;
        }

        if ($prefix !== '' && ! str_starts_with($schema, $prefix)) {
            return null;
        }

        if (! preg_match('/^[a-z0-9_]+$/', $schema)) {
            return null;
        }

        return $schema;
    }
}
