import { Copy, LogOut, MoreHorizontal, Pencil, QrCode, Trash2 } from 'lucide-react';
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
    onConnect: (session: WhatsappSession) => void;
    onDisconnect: (session: WhatsappSession) => void;
    onEdit: (session: WhatsappSession) => void;
    onDelete: (session: WhatsappSession) => void;
    canConnect?: boolean;
    canUpdate?: boolean;
    canDelete?: boolean;
};

export function SessionRowActions({
    session,
    onConnect,
    onDisconnect,
    onEdit,
    onDelete,
    canConnect = false,
    canUpdate = true,
    canDelete = true,
}: SessionRowActionsProps) {
    const { t } = useTranslation(['comunicaciones', 'common']);
    const connected = session.status === 'ready';
    const hasMenuActions = canConnect || canUpdate || canDelete;

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

    if (!hasMenuActions) {
        return null;
    }

    return (
        <div className="flex items-center justify-end gap-1">
            {canConnect && !connected ? (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => onConnect(session)}
                    className="h-8 cursor-pointer gap-1.5 px-2.5"
                >
                    <QrCode className="size-3.5" strokeWidth={2.25} />
                    <span className="hidden sm:inline">
                        {t('comunicaciones:sesiones.row.connect')}
                    </span>
                </Button>
            ) : null}
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
                    {canConnect && !connected ? (
                        <DropdownMenuItem
                            onSelect={() => onConnect(session)}
                            className="cursor-pointer gap-2"
                        >
                            <QrCode className="size-4" strokeWidth={2.25} />
                            {t('comunicaciones:sesiones.row.connect')}
                        </DropdownMenuItem>
                    ) : null}
                    {canConnect && connected ? (
                        <DropdownMenuItem
                            onSelect={() => onDisconnect(session)}
                            className="cursor-pointer gap-2"
                        >
                            <LogOut className="size-4" strokeWidth={2.25} />
                            {t('comunicaciones:sesiones.row.disconnect')}
                        </DropdownMenuItem>
                    ) : null}
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
        </div>
    );
}
