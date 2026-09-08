import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import ConfirmPasswordForm from '@/components/auth/confirm-password-form';
import PasskeyVerify from '@/components/passkey-verify';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';

export default function ConfirmPassword() {
    const { t } = useTranslation('auth');

    return (
        <>
            <Head title={t('confirm.head')} />

            <PasskeyVerify
                routes={{
                    options: confirmOptions(),
                    submit: confirmStore(),
                }}
                label={t('confirm.passkey_label')}
                loadingLabel={t('confirm.passkey_loading')}
                separator={t('confirm.separator')}
            />

            <ConfirmPasswordForm />
        </>
    );
}

ConfirmPassword.layout = {
    title: 'confirm.title',
    description: 'confirm.description',
};
