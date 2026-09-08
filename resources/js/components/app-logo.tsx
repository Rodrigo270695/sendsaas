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
            <AppLogoIcon className="size-8 shrink-0" />
            <div className="ml-1 grid flex-1 text-left text-sm group-data-[collapsible=icon]:hidden">
                <span className="truncate leading-tight font-semibold tracking-tight">
                    {title}
                </span>
            </div>
        </>
    );
}
