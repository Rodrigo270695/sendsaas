import { Head, resetLayoutProps, setLayoutProps } from '@inertiajs/react';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    const { t } = useTranslation('settings');

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                {
                    title: t('appearance.head'),
                    href: editAppearance(),
                },
            ],
        });

        return () => {
            resetLayoutProps();
        };
    }, [t]);

    return (
        <>
            <Head title={t('appearance.head')} />

            <h1 className="sr-only">{t('appearance.head')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('appearance.title')}
                    description={t('appearance.description')}
                />
                <AppearanceTabs />
            </div>
        </>
    );
}
