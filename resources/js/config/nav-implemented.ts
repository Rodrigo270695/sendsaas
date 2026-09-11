const IMPLEMENTED_PREFIXES = [
    '/dashboard',
    '/configuracion/roles',
    '/configuracion/usuarios',
    '/configuracion/sedes',
    '/configuracion/suscripcion',
    '/comunicaciones/sesiones',
    '/comunicaciones/envios',
    '/comunicaciones/historial',
    '/bandeja',
    '/contactos',
    '/plataforma/planes',
    '/plataforma/tenants',
    '/settings',
];

export function isNavRouteImplemented(href: string): boolean {
    const path = href.split('?')[0] ?? href;

    return IMPLEMENTED_PREFIXES.some(
        (prefix) => path === prefix || path.startsWith(`${prefix}/`),
    );
}
