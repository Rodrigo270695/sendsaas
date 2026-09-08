import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    /**
     * Si se omite, el item se renderiza como texto plano (no clickeable).
     * Útil para niveles intermedios que no tienen página propia, como
     * "Configuración" cuando es solo una sección del sidebar.
     */
    href?: NonNullable<InertiaLinkProps['href']>;
};

/**
 * Contexto de hosting en el que un item del sidebar es relevante.
 *
 * - `central`: solo aparece en el dominio central (panel SaaS sin tenant).
 * - `tenant`: solo aparece dentro de un subdominio de empresa.
 * - `both` (default): siempre disponible cuando el usuario tenga permisos.
 */
export type NavContext = 'central' | 'tenant' | 'both';

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /**
     * Permiso requerido para que el item aparezca en el menú.
     * Si es array → basta con tener uno (OR). El rol `superadmin` lo ignora.
     */
    permission?: string | string[];
    /**
     * Host(s) donde tiene sentido mostrar este item. Default `'both'`.
     */
    context?: NavContext;
    /**
     * Si es `false`, el item no se muestra en el sidebar (módulo aún no implementado).
     * También puedes centralizar la ruta en `config/nav-implemented.ts`.
     */
    implemented?: boolean;
    /** Contador numérico (ej. mensajes sin leer). */
    badgeCount?: number;
};

/**
 * Grupo de navegación con items hijos desplegables.
 * El grupo aparece si AL MENOS UNO de sus items pasa el chequeo de permisos
 * y contexto.
 */
export type NavGroup = {
    title: string;
    icon?: LucideIcon;
    defaultOpen?: boolean;
    permission?: string | string[];
    context?: NavContext;
    items: NavItem[];
};
