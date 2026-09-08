import { router, usePage } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { isTenantImpersonating } from '@/components/tenant-impersonation-banner';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import tenantImpersonation from '@/routes/impersonate';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { t } = useTranslation('common');
    const { tenant_impersonation: imp } = usePage().props;
    const impersonating = isTenantImpersonating(imp);

    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-border/60 bg-white px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4 dark:bg-background">
            <div className="flex min-w-0 flex-1 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            {impersonating ? (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="hidden h-8 shrink-0 cursor-pointer border-destructive/70 px-2.5 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive md:inline-flex"
                    onClick={() => router.post(tenantImpersonation.leave.url())}
                >
                    <ShieldAlert className="size-3.5" aria-hidden />
                    {t('impersonation.banner_leave_short')}
                </Button>
            ) : null}
        </header>
    );
}
