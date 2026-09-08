import { Link } from '@inertiajs/react';
import {
    Activity,
    BarChart3,
    Bot,
    Building2,
    Cog,
    LayoutGrid,
    MessageCircle,
    Repeat,
    Send,
    Server,
    ShieldCheck,
    Sparkles,
    Store,
    UserCog,
    Users,
    Wallet,
} from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import AppLogo from '@/components/app-logo';
import { NavMainCollapsible } from '@/components/nav-main-collapsible';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavGroup, NavItem } from '@/types';

/**
 * Construye los items y grupos de navegación con etiquetas traducidas.
 *
 * Misma estructura que VetSaaS: singles + grupos colapsables. Las rutas
 * que aún no existen se ocultan vía `nav-implemented.ts`.
 */
function useNavConfig(): { singles: NavItem[]; groups: NavGroup[] } {
    const { t } = useTranslation('nav');

    return useMemo(
        () => ({
            singles: [
                {
                    title: t('items.dashboard'),
                    href: dashboard(),
                    icon: LayoutGrid,
                    permission: 'dashboard.view',
                },
            ],
            groups: [
                {
                    title: t('groups.bandeja'),
                    icon: MessageCircle,
                    context: 'tenant',
                    items: [
                        {
                            title: t('items.conversaciones'),
                            href: '/bandeja/conversaciones',
                            icon: MessageCircle,
                            permission: 'conversations.view',
                        },
                    ],
                },
                {
                    title: t('groups.contactos'),
                    icon: Users,
                    context: 'tenant',
                    items: [
                        {
                            title: t('items.contactos'),
                            href: '/contactos',
                            icon: Users,
                            permission: 'contacts.view',
                        },
                    ],
                },
                {
                    title: t('groups.campanas'),
                    icon: Send,
                    context: 'tenant',
                    items: [
                        {
                            title: t('items.campanas'),
                            href: '/campanas',
                            icon: Send,
                            permission: 'campaigns.view',
                        },
                        {
                            title: t('items.automatizaciones'),
                            href: '/automatizaciones',
                            icon: Repeat,
                            permission: 'automations.view',
                        },
                    ],
                },
                {
                    title: t('groups.reportes'),
                    icon: Activity,
                    context: 'tenant',
                    items: [
                        {
                            title: t('items.reportes'),
                            href: '/reportes',
                            icon: BarChart3,
                            permission: 'reports.view',
                        },
                    ],
                },
                {
                    title: t('groups.configuracion'),
                    icon: Cog,
                    context: 'both',
                    items: [
                        {
                            title: t('items.sedes'),
                            href: '/configuracion/sedes',
                            icon: Building2,
                            permission: 'sedes.view',
                        },
                        {
                            title: t('items.roles'),
                            href: '/configuracion/roles',
                            icon: ShieldCheck,
                            permission: 'roles.view',
                        },
                        {
                            title: t('items.usuarios'),
                            href: '/configuracion/usuarios',
                            icon: UserCog,
                            permission: 'usuarios.view',
                        },
                    ],
                },
                {
                    title: t('groups.plataforma_sistema'),
                    icon: Server,
                    context: 'central',
                    items: [
                        {
                            title: t('items.operaciones'),
                            href: '/plataforma/operaciones',
                            icon: Activity,
                            permission: 'plataforma-tenants.view',
                        },
                    ],
                },
                {
                    title: t('groups.plataforma_empresas'),
                    icon: Building2,
                    context: 'central',
                    items: [
                        {
                            title: t('items.tenants'),
                            href: '/plataforma/tenants',
                            icon: Store,
                            permission: 'plataforma-tenants.view',
                        },
                        {
                            title: t('items.planes'),
                            href: '/plataforma/planes',
                            icon: Sparkles,
                            permission: 'plataforma-planes.view',
                        },
                    ],
                },
                {
                    title: t('groups.plataforma_cobros'),
                    icon: Wallet,
                    context: 'central',
                    items: [
                        {
                            title: t('items.cobros'),
                            href: '/plataforma/cobros',
                            icon: Wallet,
                            permission: 'plataforma-planes.view',
                        },
                    ],
                },
                {
                    title: t('groups.plataforma_producto'),
                    icon: Bot,
                    context: 'central',
                    items: [
                        {
                            title: t('items.whatsapp'),
                            href: '/plataforma/whatsapp',
                            icon: MessageCircle,
                            permission: 'plataforma-openwa.view',
                        },
                    ],
                },
            ],
        }),
        [t],
    );
}

export function AppSidebar() {
    const { t } = useTranslation('nav');
    const { singles, groups } = useNavConfig();

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()}>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMainCollapsible
                    label={t('section')}
                    singles={singles}
                    groups={groups}
                />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
