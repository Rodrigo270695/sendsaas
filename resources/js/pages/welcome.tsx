import { Head, Link, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard, login } from '@/routes';
import { register } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;
    const { t } = useTranslation('welcome');

    return (
        <>
            <Head title={t('head')} />
            <div className="flex min-h-screen flex-col items-center justify-center bg-background p-6">
                <header className="absolute top-0 right-0 left-0 flex justify-end p-6">
                    <nav className="flex items-center gap-4 text-sm">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-flex rounded-md border border-border px-5 py-1.5 hover:bg-muted"
                            >
                                {t('dashboard')}
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="inline-flex rounded-md px-5 py-1.5 hover:bg-muted"
                                >
                                    {t('log_in')}
                                </Link>
                                <Link
                                    href={register()}
                                    className="inline-flex rounded-md border border-border px-5 py-1.5 hover:bg-muted"
                                >
                                    {t('register')}
                                </Link>
                            </>
                        )}
                    </nav>
                </header>
                <main className="flex max-w-md flex-col items-center text-center">
                    <AppLogoIcon className="mb-6 size-20" />
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {t('title')}
                    </h1>
                    <p className="mt-2 text-muted-foreground">{t('subtitle')}</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t('suggest')}
                    </p>
                </main>
            </div>
        </>
    );
}
