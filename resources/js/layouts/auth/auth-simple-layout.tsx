import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import AuthGreeting from '@/components/auth/auth-greeting';
import { login } from '@/routes';
import type { AuthLayoutProps } from '@/types';
import type { TenantShared } from '@/types/tenant';

function tenantHeadline(tenant: TenantShared): string {
    const label = (
        tenant.nombre_comercial ||
        tenant.razon_social ||
        tenant.slug
    ).trim();

    return label.endsWith('.') ? label : `${label}.`;
}

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name, tenant } = usePage().props;
    const headline = tenant
        ? tenantHeadline(tenant)
        : `${String(name || 'SendSaaS').replace(/\.$/, '')}.`;

    return (
        <div className="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={login()}
                            className="flex flex-col items-center gap-2 font-medium"
                        >
                            <div className="mb-1 flex h-9 w-9 items-center justify-center rounded-md">
                                <AppLogoIcon className="size-9" />
                            </div>
                            <span className="sr-only">{headline}</span>
                        </Link>

                        <AuthGreeting
                            title={headline}
                            description={description ?? title}
                        />
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
