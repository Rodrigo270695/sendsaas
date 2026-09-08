import { useTranslation } from 'react-i18next';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    const { t } = useTranslation('auth');

    return (
        <AuthLayoutTemplate
            title={title.includes('.') ? t(title) : title}
            description={
                description.includes('.') ? t(description) : description
            }
        >
            {children}
        </AuthLayoutTemplate>
    );
}
