import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import ThemeToggle from '@/components/theme-toggle';
import { whatsAppHref } from '@/lib/whatsapp';
import { login } from '@/routes';

type AuthHeaderProps = {
    brandName: string;
    contactWhatsApp?: string | null;
    contactLabel?: string;
};

export default function AuthHeader({
    brandName,
    contactWhatsApp,
    contactLabel = '¿Sin cuenta? Hablemos',
}: AuthHeaderProps) {
    const shared = usePage().props.contact_whatsapp;
    const waHref = whatsAppHref(
        contactWhatsApp ?? shared,
        'Hola, quiero información de SendSaaS.',
    );

    return (
        <header className="relative z-20 flex items-center justify-between px-5 py-5 sm:px-8 sm:py-6 lg:px-12">
            <Link
                href={login()}
                className="inline-flex items-center gap-2.5 rounded-md outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
            >
                <AppLogoIcon className="size-9 rounded-xl" />
                <span className="text-base font-semibold tracking-tight text-foreground">
                    {brandName}
                </span>
            </Link>

            <div className="flex items-center gap-2 sm:gap-3">
                {waHref && (
                    <a
                        href={waHref}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="group inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-border/70 bg-card/70 px-3 py-1.5 text-xs font-medium text-muted-foreground backdrop-blur transition-colors hover:border-primary/40 hover:text-foreground"
                    >
                        <span className="hidden sm:inline">{contactLabel}</span>
                        <span className="sm:hidden">Hablemos</span>
                        <ArrowUpRight className="size-3.5 transition-transform group-hover:-translate-y-px group-hover:translate-x-px" />
                    </a>
                )}
                <ThemeToggle />
            </div>
        </header>
    );
}
