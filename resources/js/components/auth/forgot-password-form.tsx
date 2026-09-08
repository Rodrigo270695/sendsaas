import { Form } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import AuthBackToLogin from '@/components/auth/auth-back-to-login';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { FieldWithIcon } from '@/components/ui/field-with-icon';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { email } from '@/routes/password';

type ForgotPasswordFormProps = {
    onBackToLogin?: () => void;
};

export default function ForgotPasswordForm({
    onBackToLogin,
}: ForgotPasswordFormProps = {}) {
    const { t } = useTranslation('auth');

    return (
        <>
            <Form {...email.form()} className="flex flex-col">
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="email">{t('fields.email')}</Label>
                            <FieldWithIcon
                                id="email"
                                type="email"
                                name="email"
                                icon={Mail}
                                required
                                autoFocus
                                autoComplete="email"
                                placeholder={t('fields.email_placeholder')}
                                className="auth-field h-11"
                                aria-invalid={!!errors.email}
                            />
                            <InputError message={errors.email} />
                        </div>

                        <Button
                            type="submit"
                            size="lg"
                            className="auth-shine-button relative h-11 w-full overflow-hidden text-base font-medium"
                            disabled={processing}
                            data-test="email-password-reset-link-button"
                        >
                            {processing && <Spinner />}
                            {processing
                                ? t('fields.submit_loading')
                                : t('forgot.submit')}
                        </Button>
                    </div>
                )}
            </Form>
            <AuthBackToLogin onClick={onBackToLogin} />
        </>
    );
}
