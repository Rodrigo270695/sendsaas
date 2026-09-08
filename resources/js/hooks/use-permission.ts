import { usePage } from '@inertiajs/react';

export function usePermission() {
    const { auth } = usePage().props;
    const permissions = new Set(auth.permissions ?? []);
    const roles = new Set(auth.roles ?? []);
    const isSuperadmin = roles.has('superadmin');

    const can = (permission: string): boolean => {
        if (isSuperadmin) {
            return true;
        }

        return permissions.has(permission);
    };

    const hasRole = (role: string): boolean => roles.has(role);

    return { can, hasRole, isSuperadmin, permissions, roles };
}
