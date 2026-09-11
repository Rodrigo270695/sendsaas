import { router, usePage } from '@inertiajs/react';
import { UserRound } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { InboxCapabilities, InboxUser, SelectedConversation } from '../types';

type ConversationAssignmentProps = {
    selected: SelectedConversation;
    assignees: InboxUser[];
    capabilities: InboxCapabilities;
};

export function ConversationAssignment({
    selected,
    assignees,
    capabilities,
}: ConversationAssignmentProps) {
    const { t } = useTranslation('bandeja');
    const userId = usePage().props.auth.user?.id;
    const assigned = selected.assigned_user;
    const mine = assigned?.id === userId;
    const free = assigned === null;

    const assign = (assignedUserId: string | null) => {
        router.put(
            `/bandeja/conversaciones/${selected.id}/asignacion`,
            { assigned_user_id: assignedUserId },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <div className="flex items-center gap-1.5">
            {free && userId ? (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="h-7 cursor-pointer px-2 text-[11px]"
                    onClick={() => assign(userId)}
                >
                    {t('thread.take')}
                </Button>
            ) : null}
            {mine && !capabilities.assign ? (
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    className="h-7 cursor-pointer px-2 text-[11px]"
                    onClick={() => assign(null)}
                >
                    {t('thread.release')}
                </Button>
            ) : null}
            {capabilities.assign ? (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="h-7 cursor-pointer gap-1 px-2 text-[11px]"
                        >
                            <UserRound className="size-3.5" />
                            {assigned?.name ?? t('thread.unassigned')}
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="max-h-64 overflow-y-auto">
                        <DropdownMenuItem
                            className="cursor-pointer"
                            onClick={() => assign(null)}
                        >
                            {t('thread.unassigned')}
                        </DropdownMenuItem>
                        {assignees.map((user) => (
                            <DropdownMenuItem
                                key={user.id}
                                className="cursor-pointer"
                                onClick={() => assign(user.id)}
                            >
                                {user.name}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            ) : (
                <span className="max-w-28 truncate text-[11px] text-muted-foreground">
                    {assigned?.name ?? t('thread.unassigned')}
                </span>
            )}
        </div>
    );
}
