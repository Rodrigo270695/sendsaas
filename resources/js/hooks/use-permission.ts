import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';

export type PermissionInput = string | string[];

export function usePermission() {
    const { auth } = usePage().props;
    const permissions = useMemo(
        () => auth.permissions ?? [],
        [auth.permissions],
    );
    const roles = useMemo(() => auth.roles ?? [], [auth.roles]);
    const permissionSet = useMemo(() => new Set(permissions), [permissions]);
    const isSuperadmin = roles.includes('superadmin');

    const can = (input: PermissionInput): boolean => {
        if (isSuperadmin) {
            return true;
        }

        const list = Array.isArray(input) ? input : [input];

        return list.some((permission) => permissionSet.has(permission));
    };

    const canAll = (list: string[]): boolean => {
        if (isSuperadmin) {
            return true;
        }

        return list.every((permission) => can(permission));
    };

    const hasRole = (role: string): boolean => roles.includes(role);

    return {
        can,
        canAll,
        hasRole,
        isSuperadmin,
        permissions,
        roles,
    };
}
