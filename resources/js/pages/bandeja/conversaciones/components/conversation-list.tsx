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
        <ul>
            {items.map((row) => {
                const active = row.id === selectedId;
                const unread = row.unread_count > 0;

                return (
                    <li key={row.id}>
                        <Link
                            href={`/bandeja/conversaciones/${row.id}`}
                            preserveState
                            preserveScroll
                            only={[
                                'conversations',
                                'selected',
                                'filters',
                                'stats',
                                'assignees',
                                'tag_catalog',
                                'quick_replies',
                                'capabilities',
                            ]}
                            className={cn(
                                'flex gap-3 border-l-2 px-3 py-3.5 transition-colors lg:py-3',
                                active
                                    ? 'border-l-brand-600 bg-brand-50/80 dark:bg-brand-950/35'
                                    : 'border-l-transparent hover:bg-background/80',
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-11 shrink-0 items-center justify-center rounded-full border border-border/50 text-xs font-semibold shadow-sm lg:size-10',
                                    unread
                                        ? 'bg-brand-600 text-white'
                                        : 'bg-brand-100 text-brand-800 dark:bg-brand-950/60 dark:text-brand-100',
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
                                    <span className="ml-auto shrink-0 text-[10px] text-muted-foreground">
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
                                {(row.assigned_user || row.tags.length > 0) && (
                                    <span className="mt-1 flex flex-wrap items-center gap-1">
                                        {row.assigned_user ? (
                                            <span className="rounded-full bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">
                                                {row.assigned_user.name}
                                            </span>
                                        ) : null}
                                        {row.tags.slice(0, 2).map((tag) => (
                                            <span
                                                key={tag.id}
                                                className="rounded-full px-1.5 py-0.5 text-[10px] font-semibold text-white"
                                                style={{
                                                    backgroundColor: tag.color || '#AB3C3D',
                                                }}
                                            >
                                                {tag.name}
                                            </span>
                                        ))}
                                    </span>
                                )}
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
