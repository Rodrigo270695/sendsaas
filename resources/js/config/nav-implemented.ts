const IMPLEMENTED_PREFIXES = [
    '/dashboard',
    '/configuracion/roles',
    '/configuracion/usuarios',
    '/plataforma/planes',
    '/settings',
];

export function isNavRouteImplemented(href: string): boolean {
    const path = href.split('?')[0] ?? href;

    return IMPLEMENTED_PREFIXES.some(
        (prefix) => path === prefix || path.startsWith(`${prefix}/`),
    );
}
