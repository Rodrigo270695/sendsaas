import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
import {
    GeoCascadeFields,
    type GeoCascadeValue,
} from '@/components/geo/geo-cascade-fields';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import sedes from '@/routes/configuracion/sedes';
import type { GeoOption, Sede } from '../types';

export type SedeFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    sede: Sede | null;
    departamentos: readonly GeoOption[];
};

type SedeFormData = {
    nombre: string;
    direccion: string;
    telefono: string;
    email: string;
    distrito_id: number | null;
    activa: boolean;
    plan_limit?: string;
};

const emptyForm: SedeFormData = {
    nombre: '',
    direccion: '',
    telefono: '',
    email: '',
    distrito_id: null,
    activa: true,
    plan_limit: '',
};

const emptyGeo: GeoCascadeValue = {
    departamento_id: null,
    provincia_id: null,
    distrito_id: null,
};

const chainToGeo = (sede: Sede | null): GeoCascadeValue => {
    const chain = sede?.distrito_model;
    if (!chain) {
        return {
            ...emptyGeo,
            distrito_id: sede?.distrito_id ?? null,
        };
    }

    return {
        departamento_id: chain.provincia.departamento.id,
        provincia_id: chain.provincia.id,
        distrito_id: chain.id,
    };
};

const buildInitialData = (sede: Sede | null): SedeFormData => ({
    nombre: sede?.nombre ?? '',
    direccion: sede?.direccion ?? '',
    telefono: sede?.telefono ?? '',
    email: sede?.email ?? '',
    distrito_id: sede?.distrito_id ?? null,
    activa: sede?.activa ?? true,
});

export function SedeFormModal({
    open,
    onOpenChange,
    sede,
    departamentos,
}: SedeFormModalProps) {
    const { t } = useTranslation(['sedes', 'common']);
    const isEdit = sede !== null;
    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<SedeFormData>(emptyForm);
    const [geo, setGeo] = useState<GeoCascadeValue>(emptyGeo);

    useEffect(() => {
        if (!open) {
            return;
        }

        const initial = buildInitialData(sede);
        (Object.keys(initial) as Array<keyof SedeFormData>).forEach((key) => {
            setData(key, initial[key] as never);
        });
        setGeo(chainToGeo(sede));
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, sede?.id]);

    const canSubmit =
        data.nombre.trim().length >= 2 &&
        data.distrito_id !== null &&
        !processing;

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const onSuccess = () => {
            reset();
            clearErrors();
            onOpenChange(false);
        };
        const opts = { preserveScroll: true, onSuccess };
        if (isEdit && sede) {
            put(sedes.update(sede.id).url, opts);
        } else {
            post(sedes.store().url, opts);
        }
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={
                isEdit
                    ? t('sedes:form.title_edit')
                    : t('sedes:form.title_create')
            }
            description={
                isEdit
                    ? t('sedes:form.description_edit')
                    : t('sedes:form.description_create')
            }
            size="lg"
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
                        disabled={!canSubmit}
                        className="cursor-pointer gap-2"
                    >
                        {processing && (
                            <Loader2
                                className="size-4 animate-spin"
                                aria-hidden
                            />
                        )}
                        {isEdit
                            ? t('sedes:form.submit_edit')
                            : t('sedes:form.submit_create')}
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
                    title={t('sedes:form.section_basic')}
                    description={
                        isEdit
                            ? t('sedes:form.section_basic_hint_edit')
                            : t('sedes:form.section_basic_hint_create')
                    }
                    columns={2}
                >
                    <FormField
                        id="sede-nombre"
                        label={t('sedes:form.fields.nombre')}
                        error={errors.nombre}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="sede-nombre"
                            value={data.nombre}
                            onChange={(event) =>
                                setData('nombre', event.target.value)
                            }
                            placeholder={t(
                                'sedes:form.fields.nombre_placeholder',
                            )}
                        />
                    </FormField>
                    <FormField
                        id="sede-telefono"
                        label={t('sedes:form.fields.telefono')}
                        error={errors.telefono}
                    >
                        <Input
                            id="sede-telefono"
                            value={data.telefono}
                            onChange={(event) =>
                                setData('telefono', event.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        id="sede-email"
                        label={t('sedes:form.fields.email')}
                        error={errors.email}
                    >
                        <Input
                            id="sede-email"
                            type="email"
                            value={data.email}
                            onChange={(event) =>
                                setData('email', event.target.value)
                            }
                        />
                    </FormField>
                </FormSection>

                <FormSection
                    index={1}
                    title={t('sedes:form.section_location')}
                    description={t('sedes:form.section_location_hint')}
                    columns={1}
                >
                    <GeoCascadeFields
                        departamentos={departamentos}
                        value={geo}
                        onChange={(next) => {
                            setGeo(next);
                            setData('distrito_id', next.distrito_id);
                        }}
                        errors={{ distrito_id: errors.distrito_id }}
                        disabled={processing}
                        required
                        labels={{
                            departamento: t('sedes:form.fields.departamento'),
                            provincia: t('sedes:form.fields.provincia'),
                            distrito: t('sedes:form.fields.distrito'),
                        }}
                    />
                    <FormField
                        id="sede-direccion"
                        label={t('sedes:form.fields.direccion')}
                        error={errors.direccion}
                    >
                        <Input
                            id="sede-direccion"
                            value={data.direccion}
                            onChange={(event) =>
                                setData('direccion', event.target.value)
                            }
                        />
                    </FormField>
                </FormSection>

                <FormSection
                    index={2}
                    title={t('sedes:form.section_status')}
                    columns={1}
                >
                    <label className="flex items-start gap-3 rounded-lg border border-border/70 px-3 py-2.5">
                        <Checkbox
                            checked={data.activa}
                            onCheckedChange={(checked) =>
                                setData('activa', checked === true)
                            }
                            className="mt-0.5"
                        />
                        <span>
                            <span className="block text-sm font-medium">
                                {t('sedes:form.fields.activa')}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {t('sedes:form.fields.activa_hint')}
                            </span>
                        </span>
                    </label>
                </FormSection>
            </div>
        </FormModal>
    );
}
