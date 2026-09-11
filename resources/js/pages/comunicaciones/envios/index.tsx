import { Head, router } from '@inertiajs/react';
import { Clock3, SendHorizontal, TriangleAlert } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import {
    DataPagination,
    DataTable,
    EmptyState,
    FilterChips,
    PageHeader,
} from '@/components/data-page';
import type { DataTableColumn, FilterChip } from '@/components/data-page';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

type QueueStatus = 'queued' | 'reserved' | 'sent' | 'failed' | 'cancelled';

type QueueKind = 'campaign' | 'reminder' | 'automation' | 'manual';

type OutboundQueueRow = {
    id: string;
    kind: QueueKind;
    status: QueueStatus;
    contact_phone: string;
    body: string;
    last_error: string | null;
    available_at: string;
    sent_at: string | null;
};

type StatusFilter = 'todas' | QueueStatus;

type EnviosIndexProps = {
    items: Paginated<OutboundQueueRow>;
    filters: { status: StatusFilter };
    stats: {
        queued: number;
        reserved: number;
        sent: number;
        failed: number;
    };
};

const STATUS_FILTERS: StatusFilter[] = [
    'todas',
    'queued',
    'reserved',
    'sent',
    'failed',
];

const statusBadge: Record<QueueStatus, string> = {
    queued: 'bg-sky-100 text-sky-800 dark:bg-sky-950/40 dark:text-sky-200',
    reserved: 'bg-amber-100 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
    sent: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200',
    failed: 'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-200',
    cancelled: 'bg-muted text-muted-foreground',
};

export default function Index({ items, filters, stats }: EnviosIndexProps) {
    const { t, i18n } = useTranslation('comunicaciones');
    const hasFilter = filters.status !== 'todas';

    const chips: FilterChip<StatusFilter>[] = useMemo(
        () =>
            STATUS_FILTERS.map((value) => ({
                value,
                label: t(`envios.filters.${value}`),
            })),
        [t],
    );

    const columns = useMemo<DataTableColumn<OutboundQueueRow>[]>(
        () => [
            {
                key: 'destinatario',
                header: t('envios.columns.destinatario'),
                cell: (row) => (
                    <span className="font-medium tabular-nums">{row.contact_phone}</span>
                ),
            },
            {
                key: 'mensaje',
                header: t('envios.columns.mensaje'),
                cell: (row) => (
                    <div className="min-w-0">
                        <p className="truncate text-sm">{row.body}</p>
                        {row.last_error ? (
                            <p className="mt-0.5 truncate text-[11px] text-destructive">
                                {row.last_error}
                            </p>
                        ) : null}
                    </div>
                ),
            },
            {
                key: 'kind',
                header: t('envios.columns.kind'),
                cell: (row) => t(`envios.kinds.${row.kind}`),
            },
            {
                key: 'estado',
                header: t('envios.columns.estado'),
                cell: (row) => (
                    <span
                        className={cn(
                            'inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold',
                            statusBadge[row.status],
                        )}
                    >
                        {t(`envios.status.${row.status}`)}
                    </span>
                ),
            },
            {
                key: 'cuando',
                header: t('envios.columns.cuando'),
                cell: (row) => (
                    <span className="text-xs tabular-nums text-muted-foreground">
                        {formatDate(row.sent_at ?? row.available_at, i18n.language)}
                    </span>
                ),
            },
        ],
        [i18n.language, t],
    );

    return (
        <>
            <Head title={t('envios.title')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={t('envios.title')}
                    description={t('envios.description')}
                    stats={[
                        {
                            label: t('envios.stats.queued'),
                            value: stats.queued,
                            variant: 'info',
                            icon: Clock3,
                        },
                        {
                            label: t('envios.stats.sent'),
                            value: stats.sent,
                            variant: 'success',
                            icon: SendHorizontal,
                        },
                        {
                            label: t('envios.stats.failed'),
                            value: stats.failed,
                            variant: 'danger',
                            icon: TriangleAlert,
                        },
                    ]}
                />

                <DataTable
                    columns={columns}
                    data={items.data}
                    rowKey={(row) => row.id}
                    toolbar={
                        <FilterChips
                            ariaLabel={t('envios.filter_label')}
                            value={filters.status}
                            options={chips}
                            onChange={(status) =>
                                router.get(
                                    '/comunicaciones/envios',
                                    { status },
                                    { preserveState: true, replace: true },
                                )
                            }
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={SendHorizontal}
                            title={
                                hasFilter
                                    ? t('envios.empty.no_results_title')
                                    : t('envios.empty.no_records_title')
                            }
                            description={
                                hasFilter
                                    ? t('envios.empty.no_results_description')
                                    : t('envios.empty.no_records_description')
                            }
                        />
                    }
                    footer={
                        items.data.length > 0 ? (
                            <DataPagination
                                meta={items}
                                preservedQuery={{
                                    status:
                                        filters.status === 'todas'
                                            ? undefined
                                            : filters.status,
                                }}
                            />
                        ) : null
                    }
                />
            </div>
        </>
    );
}

function formatDate(value: string, locale: string): string {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString(locale === 'en' ? 'en-US' : 'es-PE', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Comunicaciones' },
            { title: 'Envíos programados', href: '/comunicaciones/envios' },
        ]}
    >
        {page}
    </AppLayout>
);
