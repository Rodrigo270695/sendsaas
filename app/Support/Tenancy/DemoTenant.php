<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

/**
 * Tenant público de demostración. Ahí el admin semilla y los roles
 * no se editan (tampoco en modo soporte). El resto de empresas sí.
 */
final class DemoTenant
{
    public const SLUG = 'demo';

    public const ADMIN_EMAIL = 'demo@sendsaas.pe';

    public static function isSlug(?string $slug): bool
    {
        return $slug === self::SLUG;
    }

    public static function isProtectedUserEmail(?string $email): bool
    {
        return strtolower(trim((string) $email)) === self::ADMIN_EMAIL;
    }
}
