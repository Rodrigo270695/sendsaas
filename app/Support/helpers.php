<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\DemoTenant;
use App\Tenancy\TenantManager;

if (! function_exists('tenant_id')) {
    /**
     * ID del tenant activo. Null en el panel de plataforma.
     * Se conectará a TenantManager cuando exista tenancy.
     */
    function tenant_id(): ?string
    {
        $manager = 'App\\Tenancy\\TenantManager';

        if (! app()->bound($manager) || ! class_exists($manager)) {
            return null;
        }

        return app($manager)->id();
    }
}

if (! function_exists('current_tenant')) {
    function current_tenant(): ?Tenant
    {
        if (! app()->bound(TenantManager::class)) {
            return null;
        }

        return app(TenantManager::class)->current()?->tenant;
    }
}

if (! function_exists('is_public_demo_tenant')) {
    function is_public_demo_tenant(): bool
    {
        return DemoTenant::isSlug(current_tenant()?->slug);
    }
}

if (! function_exists('is_demo_protected_user')) {
    /**
     * Admin semilla de `demo` (`demo@sendsaas.pe`). No se edita ni se
     * elimina, tampoco desde soporte. Otros usuarios del mismo tenant
     * y el resto de empresas siguen normales.
     */
    function is_demo_protected_user(?User $user): bool
    {
        if ($user === null || ! DemoTenant::isProtectedUserEmail($user->email)) {
            return false;
        }

        if (is_public_demo_tenant()) {
            return true;
        }

        if ($user->tenant_id === null) {
            return false;
        }

        return DemoTenant::isSlug(
            Tenant::query()->whereKey($user->tenant_id)->value('slug'),
        );
    }
}
