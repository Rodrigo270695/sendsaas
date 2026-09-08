import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthStatus from '@/components/auth/auth-status';
import LoginFlipContent from '@/components/auth/login-flip-content';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    const { t } = useTranslation('auth');

    return (
        <>
            <Head title={t('login.head')} />

            {status && <AuthStatus>{status}</AuthStatus>}

            <LoginFlipContent canResetPassword={canResetPassword} />
        </>
    );
}

Login.layout = {
    title: 'SendSaaS.',
    description: 'login.platform_description',
};
