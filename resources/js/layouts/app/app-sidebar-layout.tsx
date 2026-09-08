import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { TenantImpersonationBanner } from '@/components/tenant-impersonation-banner';
import type { AppLayoutProps } from '@/types';

/**
 * Layout principal con sidebar lateral.
 *
 * Header de breadcrumbs fijo + contenido scrollable internamente.
 *
 * `Sidebar variant="inset"` aplica `md:m-2` al `SidebarInset`, por lo que
 * ocupar `h-svh` exacto provocaba overflow del viewport. En md+ restamos
 * `--spacing(4)` (= 1rem = margin top + bottom).
 */
export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className="h-svh max-h-svh overflow-hidden md:h-[calc(100svh-(--spacing(4)))] md:max-h-[calc(100svh-(--spacing(4)))]"
            >
                <TenantImpersonationBanner />
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <div className="flex min-h-0 flex-1 flex-col overflow-y-auto overflow-x-hidden has-data-fixed-viewport:overflow-hidden">
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}
