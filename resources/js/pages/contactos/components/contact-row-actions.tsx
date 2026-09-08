import { Copy, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { toastManager } from '@/lib/toast';
import type { Contact } from '../types';

export type ContactRowActionsProps = {
    contact: Contact;
    onEdit: (contact: Contact) => void;
    onDelete: (contact: Contact) => void;
    canUpdate?: boolean;
    canDelete?: boolean;
};

export function ContactRowActions({
    contact,
    onEdit,
    onDelete,
    canUpdate = true,
    canDelete = true,
}: ContactRowActionsProps) {
    const { t } = useTranslation(['contactos', 'common']);

    const handleCopy = async () => {
        try {
            await navigator.clipboard.writeText(contact.phone);
            toastManager.success({
                title: t('contactos:toast.phone_copied'),
                description: contact.phone,
                duration: 2000,
            });
        } catch {
            toastManager.error({
                title: t('common:feedback.copy_error'),
            });
        }
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={t('contactos:row.actions_for', {
                        name: contact.name,
                    })}
                    className="size-8 cursor-pointer"
                >
                    <MoreHorizontal className="size-4" strokeWidth={2.5} />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuItem onSelect={handleCopy} className="cursor-pointer gap-2">
                    <Copy className="size-4" strokeWidth={2.25} />
                    {t('contactos:row.copy_phone')}
                </DropdownMenuItem>
                {(canUpdate || canDelete) && <DropdownMenuSeparator />}
                {canUpdate && (
                    <DropdownMenuItem
                        onSelect={() => onEdit(contact)}
                        className="cursor-pointer gap-2"
                    >
                        <Pencil className="size-4" strokeWidth={2.25} />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                )}
                {canDelete && (
                    <DropdownMenuItem
                        onSelect={() => onDelete(contact)}
                        className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                    >
                        <Trash2 className="size-4" strokeWidth={2.25} />
                        {t('common:actions.delete')}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
