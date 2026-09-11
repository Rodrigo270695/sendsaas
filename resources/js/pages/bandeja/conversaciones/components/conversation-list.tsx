import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';
import { conversationInitials, formatInboxWhen } from '../lib';
import type { ConversationListItem } from '../types';

type ConversationListProps = {
    items: ConversationListItem[];
    selectedId: string | null;
};

export function ConversationList({ items, selectedId }: ConversationListProps) {
    const { t } = useTranslation('bandeja');

    return (
        <ul className="divide-y divide-border/60">
            {items.map((row) => {
                const active = row.id === selectedId;
                const unread = row.unread_count > 0;

                return (
                    <li key={row.id}>
                        <Link
                            href={`/bandeja/conversaciones/${row.id}`}
                            preserveState
                            preserveScroll
                            only={['conversations', 'selected', 'filters', 'stats']}
                            className={cn(
                                'flex gap-3 px-3 py-3 transition-colors',
                                active
                                    ? 'bg-brand-50 dark:bg-brand-950/40'
                                    : 'hover:bg-muted/50',
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-10 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                    unread
                                        ? 'bg-brand-600 text-white'
                                        : 'bg-muted text-muted-foreground',
                                )}
                            >
                                {conversationInitials(row.contact.name)}
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="flex items-center gap-2">
                                    <span
                                        className={cn(
                                            'truncate text-sm',
                                            unread
                                                ? 'font-semibold text-foreground'
                                                : 'font-medium text-foreground',
                                        )}
                                    >
                                        {row.contact.name}
                                    </span>
                                    <span className="ml-auto shrink-0 text-[11px] text-muted-foreground">
                                        {formatInboxWhen(row.last_message_at)}
                                    </span>
                                </span>
                                <span className="mt-0.5 flex items-center gap-2">
                                    <span className="truncate text-xs text-muted-foreground">
                                        {row.preview ?? row.contact.phone_display}
                                    </span>
                                    {unread && (
                                        <span className="ml-auto flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-brand-600 px-1.5 text-[10px] font-semibold text-white">
                                            {row.unread_count}
                                        </span>
                                    )}
                                </span>
                                <span className="sr-only">
                                    {t(`status.${row.status}`)}
                                </span>
                            </span>
                        </Link>
                    </li>
                );
            })}
        </ul>
    );
}
