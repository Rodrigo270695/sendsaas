import { Link } from '@inertiajs/react';
import { Fragment } from 'react';
import { useTranslation } from 'react-i18next';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

function breadcrumbLabel(title: string, t: (key: string) => string): string {
    if (title.includes(':') && !title.includes(' ')) {
        const translated = t(title);

        return translated === title ? title : translated;
    }

    return title;
}

export function Breadcrumbs({
    breadcrumbs,
}: {
    breadcrumbs: BreadcrumbItemType[];
}) {
    const { t } = useTranslation();

    return (
        <>
            {breadcrumbs.length > 0 && (
                <Breadcrumb>
                    <BreadcrumbList>
                        {breadcrumbs.map((item, index) => {
                            const isLast = index === breadcrumbs.length - 1;
                            const label = breadcrumbLabel(item.title, t);

                            return (
                                <Fragment key={index}>
                                    <BreadcrumbItem>
                                        {isLast ? (
                                            <BreadcrumbPage>
                                                {label}
                                            </BreadcrumbPage>
                                        ) : item.href ? (
                                            <BreadcrumbLink asChild>
                                                <Link href={item.href}>
                                                    {label}
                                                </Link>
                                            </BreadcrumbLink>
                                        ) : (
                                            <span className="text-muted-foreground select-none">
                                                {label}
                                            </span>
                                        )}
                                    </BreadcrumbItem>
                                    {!isLast && <BreadcrumbSeparator />}
                                </Fragment>
                            );
                        })}
                    </BreadcrumbList>
                </Breadcrumb>
            )}
        </>
    );
}
