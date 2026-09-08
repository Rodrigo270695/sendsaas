<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Tenant;

final class TenantContext
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $schema,
        public readonly string $slug,
    ) {}

    public function id(): string
    {
        return (string) $this->tenant->getKey();
    }
}
