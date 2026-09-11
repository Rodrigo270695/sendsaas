import { Head, router } from '@inertiajs/react';
import { MessageCircle, Search } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/data-page';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { ConversationList } from './components/conversation-list';
import { ConversationThread } from './components/conversation-thread';
import type { ConversationFilters, InboxPageProps } from './types';

const STATUS_CHIPS: ConversationFilters['status'][] = [
    'todas',
    'OPEN',
    'PENDING',
    'RESOLVED',
    'CLOSED',
];

export default function Index({
    conversations,
    selected,
    filters,
    stats,
    reply,
}: InboxPageProps) {
    const { t } = useTranslation('bandeja');
    const [search, setSearch] = useState(filters.search);
    const hasFilters = filters.search !== '' || filters.status !== 'todas' || filters.unread;
    const empty = conversations.data.length === 0;

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => {
        const handle = window.setTimeout(() => {
            if (search === filters.search) {
                return;
            }

            visitInbox({ ...filters, search });
        }, 300);

        return () => window.clearTimeout(handle);
    }, [search, filters]);

    return (
        <>
            <Head title={t('title')} />
            <div
                data-fixed-viewport=""
                className="flex h-full min-h-0 flex-col gap-3 p-3 md:p-4"
            >
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-lg font-semibold tracking-tight">
                            {t('title')}
                        </h1>
                        <p className="text-xs text-muted-foreground md:text-sm">
                            {t('description')}
                        </p>
                    </div>
                    <div className="flex gap-3 text-xs text-muted-foreground">
                        <span>
                            {t('stats.total')}:{' '}
                            <strong className="text-foreground">{stats.total}</strong>
                        </span>
                        <span>
                            {t('stats.open')}:{' '}
                            <strong className="text-foreground">{stats.open}</strong>
                        </span>
                        <span>
                            {t('stats.unread')}:{' '}
                            <strong className="text-foreground">{stats.unread}</strong>
                        </span>
                    </div>
                </div>

                <div className="flex min-h-0 flex-1 overflow-hidden rounded-xl border border-border/70 bg-card">
                    <aside
                        className={cn(
                            'flex w-full min-h-0 flex-col border-r border-border/60 md:w-88 lg:w-104',
                            selected ? 'hidden md:flex' : 'flex',
                        )}
                    >
                        <div className="space-y-2 border-b border-border/60 p-3">
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder={t('search_placeholder')}
                                    className="h-9 pl-8"
                                />
                            </div>
                            <div className="flex flex-wrap gap-1.5">
                                {STATUS_CHIPS.map((status) => (
                                    <FilterChip
                                        key={status}
                                        active={filters.status === status && !filters.unread}
                                        onClick={() =>
                                            visitInbox({
                                                ...filters,
                                                status,
                                                unread: false,
                                                search,
                                            })
                                        }
                                    >
                                        {t(`filters.${status}`)}
                                    </FilterChip>
                                ))}
                                <FilterChip
                                    active={filters.unread}
                                    onClick={() =>
                                        visitInbox({
                                            ...filters,
                                            unread: !filters.unread,
                                            search,
                                        })
                                    }
                                >
                                    {t('filters.unread')}
                                </FilterChip>
                            </div>
                        </div>

                        <div className="min-h-0 flex-1 overflow-y-auto">
                            {empty ? (
                                <EmptyState
                                    icon={MessageCircle}
                                    title={
                                        hasFilters
                                            ? t('empty.no_results_title')
                                            : t('empty.no_records_title')
                                    }
                                    description={
                                        hasFilters
                                            ? t('empty.no_results_description')
                                            : t('empty.no_records_description')
                                    }
                                />
                            ) : (
                                <ConversationList
                                    items={conversations.data}
                                    selectedId={selected?.id ?? null}
                                />
                            )}
                        </div>
                    </aside>

                    <section
                        className={cn(
                            'min-h-0 min-w-0 flex-1',
                            selected ? 'flex' : 'hidden md:flex',
                        )}
                    >
                        <ConversationThread selected={selected} reply={reply} />
                    </section>
                </div>
            </div>
        </>
    );
}

function FilterChip({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'cursor-pointer rounded-full px-2.5 py-1 text-[11px] font-medium transition-colors',
                active
                    ? 'bg-brand-600 text-white'
                    : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground',
            )}
        >
            {children}
        </button>
    );
}

function visitInbox(next: ConversationFilters): void {
    const url = selectedInboxUrl();
    router.get(
        url,
        {
            search: next.search || undefined,
            status: next.status === 'todas' ? undefined : next.status,
            unread: next.unread ? 1 : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['conversations', 'selected', 'filters', 'stats'],
        },
    );
}

function selectedInboxUrl(): string {
    const path = window.location.pathname;
    return path.startsWith('/bandeja/conversaciones/')
        ? path
        : '/bandeja/conversaciones';
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Bandeja' },
            { title: 'Conversaciones', href: '/bandeja/conversaciones' },
        ]}
    >
        {page}
    </AppLayout>
);
