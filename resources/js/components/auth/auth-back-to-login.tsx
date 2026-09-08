import { ArrowLeft } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import TextLink from '@/components/text-link';
import { cn } from '@/lib/utils';
import { login } from '@/routes';

type AuthBackToLoginProps = {
    label?: string;
    onClick?: () => void;
};

const baseClasses =
    'inline-flex items-center gap-1.5 cursor-pointer transition-transform hover:-translate-x-px';

export default function AuthBackToLogin({
    label,
    onClick,
}: AuthBackToLoginProps) {
    const { t } = useTranslation('auth');
    const resolvedLabel = label ?? t('forgot.back_to_login');

    return (
        <div className="mt-6 text-center text-sm text-muted-foreground">
            {onClick ? (
                <button
                    type="button"
                    onClick={onClick}
                    className={cn(
                        baseClasses,
                        'rounded-sm font-medium text-primary underline-offset-4 hover:text-brand-700 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                    )}
                >
                    <ArrowLeft className="size-3.5" />
                    {resolvedLabel}
                </button>
            ) : (
                <TextLink href={login()} className={baseClasses}>
                    <ArrowLeft className="size-3.5" />
                    {resolvedLabel}
                </TextLink>
            )}
        </div>
    );
}
