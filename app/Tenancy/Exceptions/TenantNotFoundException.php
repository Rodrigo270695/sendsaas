<?php

declare(strict_types=1);

namespace App\Tenancy\Exceptions;

use RuntimeException;

class TenantNotFoundException extends RuntimeException
{
    public function __construct(public readonly string $slug)
    {
        parent::__construct("Tenant no encontrado: {$slug}");
    }
}
