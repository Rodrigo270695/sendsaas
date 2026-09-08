import { Head } from '@inertiajs/react';
import { SendHorizontal } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState, PageHeader } from '@/components/data-page';
import AppLayout from '@/layouts/app-layout';

export default function Index() {
    const { t } = useTranslation('comunicaciones');

    return (
        <>
            <Head title={t('envios.title')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={t('envios.title')}
                    description={t('envios.description')}
                />
                <div className="rounded-xl border border-dashed border-border/80 bg-muted/20 px-4 py-16">
                    <EmptyState
                        icon={SendHorizontal}
                        title={t('envios.empty_title')}
                        description={t('envios.empty_description')}
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
            { title: 'Envíos programados', href: '/comunicaciones/envios' },
        ]}
    >
        {page}
    </AppLayout>
);
