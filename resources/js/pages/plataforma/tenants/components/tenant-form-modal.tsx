import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useMemo, useRef, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTenancy } from '@/lib/tenancy-url';
import tenants from '@/routes/plataforma/tenants';
import type { Tenant, TenantPlanOption } from '../types';

export type TenantFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    tenant: Tenant | null;
    plansCatalog: readonly TenantPlanOption[];
};

type TenantFormData = {
    slug: string;
    razon_social: string;
    nombre_comercial: string;
    email_admin: string;
    telefono: string;
    plan_id: string;
    timezone: string;
    locale: string;
    trial_days: number;
    admin_password: string;
};

const PLAN_NONE = '__none__';
const DEFAULT_TRIAL_DAYS = 14;

const emptyForm: TenantFormData = {
    slug: '',
    razon_social: '',
    nombre_comercial: '',
    email_admin: '',
    telefono: '',
    plan_id: PLAN_NONE,
    timezone: 'America/Lima',
    locale: 'es_PE',
    trial_days: DEFAULT_TRIAL_DAYS,
    admin_password: '',
};

const buildInitialData = (tenant: Tenant | null): TenantFormData => ({
    slug: tenant?.slug ?? '',
    razon_social: tenant?.razon_social ?? '',
    nombre_comercial: tenant?.nombre_comercial ?? '',
    email_admin: tenant?.email_admin ?? '',
    telefono: tenant?.telefono ?? '',
    plan_id: tenant?.plan?.id ?? PLAN_NONE,
    timezone: tenant?.timezone ?? 'America/Lima',
    locale: tenant?.locale ?? 'es_PE',
    trial_days: DEFAULT_TRIAL_DAYS,
    admin_password: '',
});

const SLUG_REGEX = /^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/;
const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const isFormValid = (data: TenantFormData, isEdit: boolean): boolean => {
    if (!SLUG_REGEX.test(data.slug) || data.slug.length < 3) return false;
    if (data.razon_social.trim().length < 2) return false;
    if (!EMAIL_REGEX.test(data.email_admin.trim())) return false;
    if (data.timezone.trim() === '') return false;
    if (data.locale.trim() === '') return false;
    if (!isEdit && data.admin_password.length < 8) return false;

    return true;
};

export function TenantFormModal({
    open,
    onOpenChange,
    tenant,
    plansCatalog,
}: TenantFormModalProps) {
    const { t } = useTranslation(['tenants', 'common']);
    const tenancy = useTenancy();
    const isEdit = tenant !== null;

    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
        reset,
        clearErrors,
        transform,
    } = useForm<TenantFormData>(emptyForm);

    const subdomainVars = {
        slug: data.slug.trim() || 'mi-empresa',
        domain: tenancy.root_domain,
    };

    const canSubmit = isFormValid(data, isEdit) && !processing;
    const initialSnapshotRef = useRef<TenantFormData>(emptyForm);

    useEffect(() => {
        if (open) {
            const initial = buildInitialData(tenant);
            initialSnapshotRef.current = initial;
            (Object.keys(initial) as Array<keyof TenantFormData>).forEach(
                (key) => {
                    setData(key, initial[key] as never);
                },
            );
            clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, tenant?.id]);

    const isDirty = useMemo(() => {
        const initial = initialSnapshotRef.current;
        return JSON.stringify(initial) !== JSON.stringify(data);
    }, [data]);

    const confirmDiscard = (): boolean => {
        if (!isDirty) return true;
        return window.confirm(t('common:form.unsaved_changes'));
    };

    const handleClose = (next: boolean) => {
        if (!next) {
            if (!confirmDiscard()) return;
            reset();
            clearErrors();
        }
        onOpenChange(next);
    };

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        transform((current) => ({
            ...current,
            plan_id: current.plan_id === PLAN_NONE ? null : current.plan_id,
        }));

        const onSuccess = () => {
            reset();
            clearErrors();
            onOpenChange(false);
        };

        if (isEdit && tenant) {
            put(tenants.update(tenant.id).url, {
                preserveScroll: true,
                onSuccess,
            });
        } else {
            post(tenants.store().url, {
                preserveScroll: true,
                onSuccess,
            });
        }
    };

    const onPlanChange = (value: string) => {
        setData('plan_id', value);
        if (!isEdit) {
            const plan = plansCatalog.find((item) => item.id === value);
            if (plan) {
                setData('trial_days', plan.trial_days);
            }
        }
    };

    return (
        <FormModal
            open={open}
            onOpenChange={handleClose}
            title={
                isEdit
                    ? t('tenants:form.title_edit')
                    : t('tenants:form.title_create')
            }
            description={
                isEdit
                    ? t('tenants:form.description_edit')
                    : t('tenants:form.description_create', subdomainVars)
            }
            size="lg"
            onSubmit={onSubmit}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => handleClose(false)}
                        disabled={processing}
                        className="cursor-pointer"
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="submit"
                        disabled={!canSubmit}
                        className="cursor-pointer gap-2 disabled:cursor-not-allowed"
                    >
                        {processing && (
                            <Loader2
                                className="size-4 animate-spin"
                                aria-hidden="true"
                            />
                        )}
                        {isEdit
                            ? t('tenants:form.submit_edit')
                            : t('tenants:form.submit_create')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-5">
                <FormSection
                    index={0}
                    title={t('tenants:form.section_identity')}
                    description={t('tenants:form.section_identity_hint')}
                >
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField
                            id="tenant-slug"
                            label={t('tenants:form.fields.slug')}
                            required
                            hint={t('tenants:form.fields.slug_hint', subdomainVars)}
                            error={errors.slug}
                        >
                            <Input
                                id="tenant-slug"
                                value={data.slug}
                                onChange={(e) =>
                                    setData(
                                        'slug',
                                        e.target.value
                                            .toLowerCase()
                                            .replace(/[^a-z0-9-]/g, '-'),
                                    )
                                }
                                placeholder={t(
                                    'tenants:form.fields.slug_placeholder',
                                )}
                                disabled={
                                    isEdit &&
                                    ['active', 'suspended'].includes(
                                        tenant?.estado ?? '',
                                    )
                                }
                                autoComplete="off"
                                autoFocus={!isEdit}
                                className="font-mono"
                            />
                        </FormField>

                        <FormField
                            id="tenant-razon-social"
                            label={t('tenants:form.fields.razon_social')}
                            required
                            error={errors.razon_social}
                        >
                            <Input
                                id="tenant-razon-social"
                                value={data.razon_social}
                                onChange={(e) =>
                                    setData('razon_social', e.target.value)
                                }
                                placeholder={t(
                                    'tenants:form.fields.razon_social_placeholder',
                                )}
                                autoComplete="off"
                                autoFocus={isEdit}
                            />
                        </FormField>

                        <div className="sm:col-span-2">
                            <FormField
                                id="tenant-nombre-comercial"
                                label={t('tenants:form.fields.nombre_comercial')}
                                error={errors.nombre_comercial}
                            >
                                <Input
                                    id="tenant-nombre-comercial"
                                    value={data.nombre_comercial}
                                    onChange={(e) =>
                                        setData(
                                            'nombre_comercial',
                                            e.target.value,
                                        )
                                    }
                                    placeholder={t(
                                        'tenants:form.fields.nombre_comercial_placeholder',
                                    )}
                                    autoComplete="off"
                                />
                            </FormField>
                        </div>
                    </div>
                </FormSection>

                <FormSection
                    index={1}
                    title={t('tenants:form.section_contact')}
                    description={t('tenants:form.section_contact_hint')}
                >
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField
                            id="tenant-email"
                            label={t('tenants:form.fields.email_admin')}
                            required
                            hint={t('tenants:form.fields.email_admin_hint')}
                            error={errors.email_admin}
                        >
                            <Input
                                id="tenant-email"
                                type="email"
                                value={data.email_admin}
                                onChange={(e) =>
                                    setData('email_admin', e.target.value)
                                }
                                autoComplete="off"
                            />
                        </FormField>

                        <FormField
                            id="tenant-telefono"
                            label={t('tenants:form.fields.telefono')}
                            error={errors.telefono}
                        >
                            <Input
                                id="tenant-telefono"
                                type="tel"
                                value={data.telefono}
                                onChange={(e) =>
                                    setData('telefono', e.target.value)
                                }
                                autoComplete="off"
                            />
                        </FormField>

                        {!isEdit && (
                            <div className="sm:col-span-2">
                                <FormField
                                    id="tenant-admin-password"
                                    label={t(
                                        'tenants:form.fields.admin_password',
                                    )}
                                    required
                                    hint={t(
                                        'tenants:form.fields.admin_password_hint',
                                    )}
                                    error={errors.admin_password}
                                >
                                    <Input
                                        id="tenant-admin-password"
                                        type="password"
                                        value={data.admin_password}
                                        onChange={(e) =>
                                            setData(
                                                'admin_password',
                                                e.target.value,
                                            )
                                        }
                                        autoComplete="new-password"
                                    />
                                </FormField>
                            </div>
                        )}
                    </div>
                </FormSection>

                <FormSection
                    index={2}
                    title={t('tenants:form.section_platform')}
                    description={t('tenants:form.section_platform_hint')}
                >
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField
                            id="tenant-plan"
                            label={t('tenants:form.fields.plan')}
                            error={errors.plan_id}
                        >
                            <Select
                                value={data.plan_id}
                                onValueChange={onPlanChange}
                            >
                                <SelectTrigger
                                    id="tenant-plan"
                                    className="w-full"
                                >
                                    <SelectValue
                                        placeholder={t(
                                            'tenants:form.fields.plan_placeholder',
                                        )}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        value={PLAN_NONE}
                                        className="cursor-pointer"
                                    >
                                        {t(
                                            'tenants:form.fields.plan_placeholder',
                                        )}
                                    </SelectItem>
                                    {plansCatalog.map((plan) => (
                                        <SelectItem
                                            key={plan.id}
                                            value={plan.id}
                                            className="cursor-pointer"
                                        >
                                            {plan.nombre}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        {!isEdit && (
                            <FormField
                                id="tenant-trial-days"
                                label={t('tenants:form.fields.trial_days')}
                                hint={t('tenants:form.fields.trial_days_hint')}
                                error={errors.trial_days}
                            >
                                <Input
                                    id="tenant-trial-days"
                                    type="number"
                                    min={0}
                                    max={365}
                                    value={data.trial_days}
                                    onChange={(e) =>
                                        setData(
                                            'trial_days',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </FormField>
                        )}

                        <FormField
                            id="tenant-timezone"
                            label={t('tenants:form.fields.timezone')}
                            required
                            hint={t('tenants:form.fields.timezone_hint')}
                            error={errors.timezone}
                        >
                            <Input
                                id="tenant-timezone"
                                value={data.timezone}
                                onChange={(e) =>
                                    setData('timezone', e.target.value)
                                }
                                className="font-mono"
                                autoComplete="off"
                            />
                        </FormField>

                        <FormField
                            id="tenant-locale"
                            label={t('tenants:form.fields.locale')}
                            required
                            hint={t('tenants:form.fields.locale_hint')}
                            error={errors.locale}
                        >
                            <Input
                                id="tenant-locale"
                                value={data.locale}
                                onChange={(e) =>
                                    setData('locale', e.target.value)
                                }
                                className="font-mono"
                                autoComplete="off"
                            />
                        </FormField>
                    </div>
                </FormSection>
            </div>
        </FormModal>
    );
}
