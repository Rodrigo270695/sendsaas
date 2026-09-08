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
    const isI18nKey = (value: string): boolean =>
        /^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]+)+$/i.test(value);

    return (
        <AuthLayoutTemplate
            title={isI18nKey(title) ? t(title) : title}
            description={
                isI18nKey(description) ? t(description) : description
            }
        >
            {children}
        </AuthLayoutTemplate>
    );
}
