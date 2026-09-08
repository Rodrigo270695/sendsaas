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
import type { WhatsappSession } from '../types';

export type SessionRowActionsProps = {
    session: WhatsappSession;
    onEdit: (session: WhatsappSession) => void;
    onDelete: (session: WhatsappSession) => void;
    canUpdate?: boolean;
    canDelete?: boolean;
};

export function SessionRowActions({
    session,
    onEdit,
    onDelete,
    canUpdate = true,
    canDelete = true,
}: SessionRowActionsProps) {
    const { t } = useTranslation(['comunicaciones', 'common']);

    const handleCopy = async () => {
        try {
            await navigator.clipboard.writeText(session.openwa_session_name);
            toastManager.success({
                title: t('comunicaciones:sesiones.toast.name_copied'),
                description: session.openwa_session_name,
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
                    aria-label={t('comunicaciones:sesiones.row.actions_for', {
                        name: session.alias,
                    })}
                    className="size-8 cursor-pointer"
                >
                    <MoreHorizontal className="size-4" strokeWidth={2.5} />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuItem
                    onSelect={handleCopy}
                    className="cursor-pointer gap-2"
                >
                    <Copy className="size-4" strokeWidth={2.25} />
                    {t('comunicaciones:sesiones.row.copy_name')}
                </DropdownMenuItem>
                {(canUpdate || canDelete) && <DropdownMenuSeparator />}
                {canUpdate && (
                    <DropdownMenuItem
                        onSelect={() => onEdit(session)}
                        className="cursor-pointer gap-2"
                    >
                        <Pencil className="size-4" strokeWidth={2.25} />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                )}
                {canDelete && (
                    <DropdownMenuItem
                        onSelect={() => onDelete(session)}
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
