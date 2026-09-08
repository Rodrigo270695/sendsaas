import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name, tenant } = usePage().props;
    const title =
        tenant?.nombre_comercial?.trim() ||
        tenant?.razon_social?.trim() ||
        name;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-full bg-brand-50 ring-1 ring-brand-200/70 dark:bg-brand-950 dark:ring-brand-800">
                <AppLogoIcon className="size-8" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate leading-tight font-semibold tracking-tight">
                    {title}
                </span>
            </div>
        </>
    );
}
