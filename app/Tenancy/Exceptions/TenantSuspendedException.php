<?php

declare(strict_types=1);

namespace App\Tenancy\Exceptions;

use App\Models\Tenant;
use RuntimeException;

class TenantSuspendedException extends RuntimeException
{
    public function __construct(public readonly Tenant $tenant)
    {
        parent::__construct('Esta empresa no está disponible.');
    }
}
