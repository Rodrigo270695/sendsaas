import { usePage } from '@inertiajs/react';
import AuthAuroraBackground from '@/components/auth/auth-aurora-background';
import AuthChatScene from '@/components/auth/auth-chat-scene';
import AuthFooter from '@/components/auth/auth-footer';
import AuthFormCard from '@/components/auth/auth-form-card';
import AuthGreeting from '@/components/auth/auth-greeting';
import AuthHeader from '@/components/auth/auth-header';
import type { AuthLayoutProps } from '@/types';
import type { TenantShared } from '@/types/tenant';

const PAGES_WITH_OWN_CARD = new Set(['auth/login']);

function withPeriod(label: string): string {
    const trimmed = label.trim();
    if (trimmed === '') {
        return 'SendSaaS.';
    }

    return trimmed.endsWith('.') ? trimmed : `${trimmed}.`;
}

function tenantHeadline(tenant: TenantShared): string {
    return withPeriod(
        tenant.nombre_comercial || tenant.razon_social || tenant.slug,
    );
}

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const page = usePage();
    const { name, tenant, greeting_name } = page.props;
    const brandName = String(name || 'SendSaaS');
    const skipFormCard = PAGES_WITH_OWN_CARD.has(page.component);
    const identityHeadline = tenant
        ? tenantHeadline(tenant)
        : withPeriod(String(greeting_name || brandName));
    const editorialTitle =
        title && title !== 'SendSaaS.' ? title : null;
    const headline = tenant
        ? identityHeadline
        : (editorialTitle ?? identityHeadline);

    return (
        <div className="relative isolate flex min-h-svh flex-col overflow-hidden bg-background text-foreground">
            <AuthAuroraBackground />
            <AuthChatScene />
            <AuthHeader brandName={brandName} />

            <main className="relative z-10 flex flex-1 items-center justify-center px-4 py-8 sm:py-12">
                <article className="relative z-10 mx-auto w-full max-w-md">
                    <AuthGreeting
                        title={headline}
                        description={description ?? title}
                    />
                    {skipFormCard ? children : <AuthFormCard>{children}</AuthFormCard>}
                </article>
            </main>

            <AuthFooter brandName={brandName} />
        </div>
    );
}
