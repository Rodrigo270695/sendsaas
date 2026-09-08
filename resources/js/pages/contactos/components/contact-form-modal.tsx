import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, type FormEvent } from 'react';
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
import { Textarea } from '@/components/ui/textarea';
import type { Contact, SedeOption } from '../types';

export type ContactFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    contact: Contact | null;
    sedes: readonly SedeOption[];
};

type ContactFormData = {
    name: string;
    phone: string;
    email: string;
    notes: string;
    sede_id: string;
    plan_limit?: string;
};

const emptyForm: ContactFormData = {
    name: '',
    phone: '',
    email: '',
    notes: '',
    sede_id: 'none',
    plan_limit: '',
};

export function ContactFormModal({
    open,
    onOpenChange,
    contact,
    sedes,
}: ContactFormModalProps) {
    const { t } = useTranslation(['contactos', 'common']);
    const isEdit = contact !== null;
    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<ContactFormData>(emptyForm);

    useEffect(() => {
        if (!open) {
            return;
        }
        clearErrors();
        setData({
            name: contact?.name ?? '',
            phone: contact?.phone ?? '',
            email: contact?.email ?? '',
            notes: contact?.notes ?? '',
            sede_id: contact?.sede_id ?? 'none',
            plan_limit: '',
        });
    }, [open, contact, clearErrors, setData]);

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        const opts = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        };
        if (isEdit && contact) {
            put(`/contactos/${contact.id}`, opts);
        } else {
            post('/contactos', opts);
        }
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={
                isEdit
                    ? t('contactos:form.title_edit')
                    : t('contactos:form.title_create')
            }
            description={
                isEdit
                    ? t('contactos:form.description_edit')
                    : t('contactos:form.description_create')
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
                            <Loader2 className="size-4 animate-spin" aria-hidden />
                        )}
                        {isEdit
                            ? t('contactos:form.submit_edit')
                            : t('contactos:form.submit_create')}
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
                    title={t('contactos:form.section_basic')}
                    description={t('contactos:form.section_basic_hint')}
                    columns={2}
                >
                    <FormField
                        id="contact-name"
                        label={t('contactos:form.fields.name')}
                        error={errors.name}
                        required
                    >
                        <Input
                            id="contact-name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder={t('contactos:form.fields.name_placeholder')}
                        />
                    </FormField>
                    <FormField
                        id="contact-phone"
                        label={t('contactos:form.fields.phone')}
                        error={errors.phone}
                        required
                    >
                        <Input
                            id="contact-phone"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            placeholder="987654321"
                            inputMode="tel"
                        />
                    </FormField>
                    <FormField
                        id="contact-email"
                        label={t('contactos:form.fields.email')}
                        error={errors.email}
                    >
                        <Input
                            id="contact-email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                    </FormField>
                    <FormField
                        id="contact-sede"
                        label={t('contactos:form.fields.sede')}
                        error={errors.sede_id}
                    >
                        <Select
                            value={data.sede_id}
                            onValueChange={(value) => setData('sede_id', value)}
                        >
                            <SelectTrigger id="contact-sede" className="w-full">
                                <SelectValue
                                    placeholder={t('contactos:form.fields.sede_none')}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">
                                    {t('contactos:form.fields.sede_none')}
                                </SelectItem>
                                {sedes.map((sede) => (
                                    <SelectItem key={sede.id} value={sede.id}>
                                        {sede.nombre} · {sede.codigo}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        id="contact-notes"
                        label={t('contactos:form.fields.notes')}
                        error={errors.notes}
                        className="sm:col-span-2"
                    >
                        <Textarea
                            id="contact-notes"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            rows={3}
                        />
                    </FormField>
                </FormSection>
            </div>
        </FormModal>
    );
}
