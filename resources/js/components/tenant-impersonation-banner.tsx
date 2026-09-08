import { router, usePage } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import tenantImpersonation from '@/routes/impersonate';

export function TenantImpersonationBanner() {
    const { t } = useTranslation(['common']);
    const { tenant_impersonation: imp } = usePage().props;

    if (!imp || typeof imp !== 'object' || !('tenant_id' in imp)) {
        return null;
    }

    const label =
        typeof imp.tenant_label === 'string' && imp.tenant_label.trim() !== ''
            ? imp.tenant_label
            : t('impersonation.banner_fallback_clinic');

    const onLeave = (): void => {
        try {
            sessionStorage.removeItem('sendsaas:impersonation-banner-minimized');
        } catch {
            // ignore
        }
        router.post(tenantImpersonation.leave.url());
    };

    return (
        <div className="shrink-0 border-b border-[#8c2f30] bg-[#AB3C3D] px-3 py-2 text-white md:px-4">
            <div className="flex items-center gap-2">
                <ShieldAlert className="size-4 shrink-0" aria-hidden />
                <p className="min-w-0 flex-1 truncate text-xs sm:text-sm">
                    <span className="font-semibold">
                        {t('impersonation.banner_title')}
                    </span>
                    <span className="mx-1.5 opacity-60">·</span>
                    <span className="opacity-95">{label}</span>
                </p>
                <Button
                    type="button"
                    size="sm"
                    className="h-7 shrink-0 cursor-pointer bg-white px-3 text-xs font-semibold text-[#AB3C3D] hover:bg-white/90 hover:text-[#8c2f30]"
                    onClick={onLeave}
                >
                    {t('impersonation.banner_leave_short')}
                </Button>
            </div>
        </div>
    );
}
