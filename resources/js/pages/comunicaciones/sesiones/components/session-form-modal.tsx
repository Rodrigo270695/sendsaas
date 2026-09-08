import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { SedeOption, WhatsappSession } from '../types';

export type SessionFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    session: WhatsappSession | null;
    sedes: readonly SedeOption[];
};

type SessionFormData = {
    alias: string;
    sede_id: string;
    auto_reconnect: boolean;
    plan_limit?: string;
};

const emptyForm: SessionFormData = {
    alias: '',
    sede_id: 'none',
    auto_reconnect: true,
    plan_limit: '',
};

const buildInitialData = (session: WhatsappSession | null): SessionFormData => ({
    alias: session?.alias ?? '',
    sede_id: session?.sede_id ?? 'none',
    auto_reconnect: session?.auto_reconnect ?? true,
});

export function SessionFormModal({
    open,
    onOpenChange,
    session,
    sedes,
}: SessionFormModalProps) {
    const { t } = useTranslation(['comunicaciones', 'common']);
    const isEdit = session !== null;

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<SessionFormData>(emptyForm);

    useEffect(() => {
        if (!open) {
            return;
        }

        const initial = buildInitialData(session);
        (Object.keys(initial) as Array<keyof SessionFormData>).forEach((key) => {
            setData(key, initial[key] as never);
        });
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, session?.id]);

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const opts = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                clearErrors();
                onOpenChange(false);
            },
        };

        if (isEdit && session) {
            put(`/comunicaciones/sesiones/${session.id}`, opts);
        } else {
            post('/comunicaciones/sesiones', opts);
        }
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={
                isEdit
                    ? t('comunicaciones:sesiones.form.title_edit')
                    : t('comunicaciones:sesiones.form.title_create')
            }
            description={
                isEdit
                    ? t('comunicaciones:sesiones.form.description_edit')
                    : t('comunicaciones:sesiones.form.description_create')
            }
            onSubmit={onSubmit}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                        className="cursor-pointer"
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="submit"
                        disabled={processing}
                        className="cursor-pointer gap-2"
                    >
                        {processing && (
                            <Loader2
                                className="size-4 animate-spin"
                                aria-hidden
                            />
                        )}
                        {isEdit
                            ? t('comunicaciones:sesiones.form.submit_edit')
                            : t('comunicaciones:sesiones.form.submit_create')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-5">
                {errors.plan_limit ? (
                    <p
                        className="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                        role="alert"
                    >
                        {errors.plan_limit}
                    </p>
                ) : null}

                <FormSection
                    index={0}
                    title={t('comunicaciones:sesiones.form.section_basic')}
                    description={
                        isEdit
                            ? t(
                                  'comunicaciones:sesiones.form.section_basic_hint_edit',
                              )
                            : t(
                                  'comunicaciones:sesiones.form.section_basic_hint_create',
                              )
                    }
                    columns={2}
                >
                    <FormField
                        id="session-alias"
                        label={t('comunicaciones:sesiones.form.fields.alias')}
                        error={errors.alias}
                        required
                        className="sm:col-span-2"
                    >
                        <Input
                            id="session-alias"
                            value={data.alias}
                            onChange={(event) =>
                                setData('alias', event.target.value)
                            }
                            placeholder={t(
                                'comunicaciones:sesiones.form.fields.alias_placeholder',
                            )}
                            autoComplete="off"
                        />
                    </FormField>

                    <FormField
                        id="session-sede"
                        label={t('comunicaciones:sesiones.form.fields.sede')}
                        error={errors.sede_id}
                        hint={t('comunicaciones:sesiones.form.fields.sede_hint')}
                        className="sm:col-span-2"
                    >
                        <Select
                            value={data.sede_id}
                            onValueChange={(value) => setData('sede_id', value)}
                        >
                            <SelectTrigger
                                id="session-sede"
                                className="w-full cursor-pointer"
                            >
                                <SelectValue
                                    placeholder={t(
                                        'comunicaciones:sesiones.form.fields.sede_none',
                                    )}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">
                                    {t(
                                        'comunicaciones:sesiones.form.fields.sede_none',
                                    )}
                                </SelectItem>
                                {sedes.map((sede) => (
                                    <SelectItem key={sede.id} value={sede.id}>
                                        {sede.nombre} · {sede.codigo}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <label
                        htmlFor="session-auto-reconnect"
                        className="flex items-start gap-2.5 sm:col-span-2"
                    >
                        <Checkbox
                            id="session-auto-reconnect"
                            checked={data.auto_reconnect}
                            onCheckedChange={(checked) =>
                                setData('auto_reconnect', checked === true)
                            }
                            className="mt-0.5"
                        />
                        <span className="flex flex-col gap-0.5 text-sm">
                            <span className="font-medium">
                                {t(
                                    'comunicaciones:sesiones.form.fields.auto_reconnect',
                                )}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {t(
                                    'comunicaciones:sesiones.form.fields.auto_reconnect_hint',
                                )}
                            </span>
                        </span>
                    </label>
                </FormSection>
            </div>
        </FormModal>
    );
}
