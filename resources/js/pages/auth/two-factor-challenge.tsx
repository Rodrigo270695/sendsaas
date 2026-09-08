import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import TwoFactorForm from '@/components/auth/two-factor-form';

export default function TwoFactorChallenge() {
    const { t } = useTranslation('auth');

    return (
        <>
            <Head title={t('two_factor.head')} />

            <TwoFactorForm />
        </>
    );
}
