import { Form } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

export default function ConfirmPasswordForm() {
    const { t } = useTranslation('auth');

    return (
        <Form
            {...store.form()}
            resetOnSuccess={['password']}
            className="flex flex-col"
        >
            {({ processing, errors }) => (
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="password">{t('fields.password')}</Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            required
                            autoFocus
                            autoComplete="current-password"
                            placeholder={t('fields.password_placeholder')}
                            className="h-11"
                            aria-invalid={!!errors.password}
                        />
                        <InputError message={errors.password} />
                    </div>

                    <Button
                        type="submit"
                        size="lg"
                        className="h-11 w-full text-base font-medium"
                        disabled={processing}
                        data-test="confirm-password-button"
                    >
                        {processing && <Spinner />}
                        {processing
                            ? t('fields.submit_loading')
                            : t('confirm.submit')}
                    </Button>
                </div>
            )}
        </Form>
    );
}
