import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-full bg-brand-50 ring-1 ring-brand-200/70 dark:bg-brand-950 dark:ring-brand-800">
                <AppLogoIcon className="size-8" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold tracking-tight">
                    {name}
                </span>
                <span className="truncate text-xs text-muted-foreground">
                    Bandeja WhatsApp para equipos
                </span>
            </div>
        </>
    );
}
