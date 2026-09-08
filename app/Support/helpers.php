<?php

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
