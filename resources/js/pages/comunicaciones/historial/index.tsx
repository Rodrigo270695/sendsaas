import { Head } from '@inertiajs/react';
import { History } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState, PageHeader } from '@/components/data-page';
import AppLayout from '@/layouts/app-layout';

export default function Index() {
    const { t } = useTranslation('comunicaciones');

    return (
        <>
            <Head title={t('historial.title')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={t('historial.title')}
                    description={t('historial.description')}
                />
                <div className="rounded-xl border border-dashed border-border/80 bg-muted/20 px-4 py-16">
                    <EmptyState
                        icon={History}
                        title={t('historial.empty_title')}
                        description={t('historial.empty_description')}
                    />
                </div>
            </div>
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Comunicaciones' },
            { title: 'Historial de envíos', href: '/comunicaciones/historial' },
        ]}
    >
        {page}
    </AppLayout>
);
