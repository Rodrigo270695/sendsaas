import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import ResetPasswordForm from '@/components/auth/reset-password-form';

type Props = {
    token: string;
    email: string;
};

export default function ResetPassword({ token, email }: Props) {
    const { t } = useTranslation('auth');

    return (
        <>
            <Head title={t('reset.head')} />

            <ResetPasswordForm token={token} email={email} />
        </>
    );
}

ResetPassword.layout = {
    title: 'reset.title',
    description: 'reset.description',
};
