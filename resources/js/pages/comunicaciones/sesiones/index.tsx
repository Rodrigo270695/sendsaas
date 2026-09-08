import { Head } from '@inertiajs/react';
import {
    Activity,
    CheckCircle2,
    Filter,
    PauseCircle,
    Plus,
    Radio,
    ScreenShare,
    Smartphone,
} from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    DataPagination,
    DataTable,
    DataToolbar,
    EmptyState,
    FilterChips,
    PageHeader,
    StatBadge,
} from '@/components/data-page';
import type { DataTableColumn, FilterChip } from '@/components/data-page';
import { PlanLimitCreateButton } from '@/components/plan-limit-create-button';
import { Button } from '@/components/ui/button';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import { usePlanLimitReached } from '@/hooks/use-plan-limits';
import AppLayout from '@/layouts/app-layout';
import type { Paginated } from '@/types';
import { SessionConnectDialog } from './components/session-connect-dialog';
import { SessionDeleteDialog } from './components/session-delete-dialog';
import { SessionDisconnectDialog } from './components/session-disconnect-dialog';
import { SessionFormModal } from './components/session-form-modal';
import { SessionRowActions } from './components/session-row-actions';
import type {
    SedeOption,
    SessionEstadoFilter,
    SessionFilters,
    SessionStats,
    WhatsappSession,
    WhatsappSessionStatus,
} from './types';

type SessionsIndexProps = {
    sessions: Paginated<WhatsappSession>;
    filters: SessionFilters;
    stats: SessionStats;
    sedes: readonly SedeOption[];
    openwa: {
        configured: boolean;
    };
};

type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; session: WhatsappSession }
    | { type: 'delete'; session: WhatsappSession }
    | { type: 'connect'; session: WhatsappSession }
    | { type: 'disconnect'; session: WhatsappSession };

const DEFAULT_PER_PAGE = 10;
const DEFAULT_ESTADO: SessionEstadoFilter = 'todas';

const STATUS_VARIANT: Record<
    WhatsappSessionStatus,
    'success' | 'info' | 'warning' | 'danger'
> = {
    created: 'info',
    initializing: 'info',
    qr_ready: 'info',
    authenticating: 'info',
    ready: 'success',
    disconnected: 'warning',
    failed: 'danger',
};

export default function Index({
    sessions: paginated,
    filters,
    stats,
    sedes,
    openwa = { configured: false },
}: SessionsIndexProps) {
    const { t } = useTranslation(['comunicaciones', 'common']);
    const { can } = usePermission();
    const canCreate = can('whatsapp.connect');
    const canConnect = can('whatsapp.connect');
    const canUpdate = can('whatsapp.update');
    const canDelete = can('whatsapp.delete');
    const showRowActions = canConnect || canUpdate || canDelete;
    const limitReached = usePlanLimitReached('max_whatsapp_sessions');

    const {
        search,
        setSearch,
        isLoading,
        sort,
        setSort,
        setPerPage,
        applyFilter,
    } = useDataTablePage<{
        estado: SessionEstadoFilter;
    }>({
        routeUrl: '/comunicaciones/sesiones',
        initialFilters: filters,
        only: ['sessions', 'filters', 'stats'],
        errorMessage: t('comunicaciones:sesiones.toast.load_error'),
        storageKey: 'sendsaas.sesiones.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const estadoOptions: readonly FilterChip<SessionEstadoFilter>[] = useMemo(
        () => [
            { value: 'todas', label: t('comunicaciones:sesiones.filters.all') },
            {
                value: 'conectada',
                label: t('comunicaciones:sesiones.filters.connected'),
            },
            {
                value: 'pendiente',
                label: t('comunicaciones:sesiones.filters.pending'),
            },
            {
                value: 'desconectada',
                label: t('comunicaciones:sesiones.filters.disconnected'),
            },
        ],
        [t],
    );

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openCreate = useCallback(() => {
        if (limitReached) {
            return;
        }
        setModal({ type: 'create' });
    }, [limitReached]);
    const openEdit = useCallback(
        (session: WhatsappSession) => setModal({ type: 'edit', session }),
        [],
    );
    const openDelete = useCallback(
        (session: WhatsappSession) => setModal({ type: 'delete', session }),
        [],
    );
    const openConnect = useCallback(
        (session: WhatsappSession) => setModal({ type: 'connect', session }),
        [],
    );
    const openDisconnect = useCallback(
        (session: WhatsappSession) => setModal({ type: 'disconnect', session }),
        [],
    );

    const activeFiltersCount = useMemo(() => {
        let count = 0;
        if (filters.search) count += 1;
        if (filters.sort) count += 1;
        if (filters.estado !== DEFAULT_ESTADO) count += 1;
        if (filters.per_page !== DEFAULT_PER_PAGE) count += 1;
        return count;
    }, [filters.search, filters.sort, filters.estado, filters.per_page]);

    const columns = useMemo<DataTableColumn<WhatsappSession>[]>(() => {
        const base: DataTableColumn<WhatsappSession>[] = [
            {
                key: 'alias',
                header: t('comunicaciones:sesiones.columns.canal'),
                sortable: true,
                cell: (row) => (
                    <div className="flex min-w-0 flex-col leading-tight">
                        <span className="truncate text-sm font-semibold">
                            {row.alias}
                        </span>
                        <span className="truncate font-mono text-[11px] text-muted-foreground">
                            {row.openwa_session_name}
                        </span>
                    </div>
                ),
            },
            {
                key: 'phone',
                header: t('comunicaciones:sesiones.columns.numero'),
                sortable: true,
                cell: (row) =>
                    row.phone ? (
                        <div className="flex min-w-0 flex-col leading-tight">
                            <span className="font-mono text-xs">{row.phone}</span>
                            {row.push_name ? (
                                <span className="truncate text-[11px] text-muted-foreground">
                                    {row.push_name}
                                </span>
                            ) : null}
                        </div>
                    ) : (
                        <span className="text-xs text-muted-foreground italic">
                            {t('comunicaciones:sesiones.row.no_phone')}
                        </span>
                    ),
            },
            {
                key: 'sede',
                header: t('comunicaciones:sesiones.columns.sede'),
                cell: (row) =>
                    row.sede ? (
                        <span className="text-xs">
                            {row.sede.nombre}{' '}
                            <span className="font-mono text-muted-foreground">
                                · {row.sede.codigo}
                            </span>
                        </span>
                    ) : (
                        <span className="text-xs text-muted-foreground italic">
                            {t('comunicaciones:sesiones.row.no_sede')}
                        </span>
                    ),
            },
            {
                key: 'status',
                header: t('comunicaciones:sesiones.columns.estado'),
                sortable: true,
                cell: (row) => (
                    <StatBadge
                        label={t(
                            `comunicaciones:sesiones.row.status.${row.status}`,
                        )}
                        value=""
                        variant={STATUS_VARIANT[row.status]}
                    />
                ),
            },
        ];

        if (showRowActions) {
            base.push({
                key: 'acciones',
                header: t('comunicaciones:sesiones.columns.acciones'),
                className: 'w-36',
                cell: (row) => (
                    <SessionRowActions
                        session={row}
                        onConnect={openConnect}
                        onDisconnect={openDisconnect}
                        onEdit={openEdit}
                        onDelete={openDelete}
                        canConnect={canConnect}
                        canUpdate={canUpdate}
                        canDelete={canDelete}
                    />
                ),
            });
        }

        return base;
    }, [
        t,
        showRowActions,
        canConnect,
        canUpdate,
        canDelete,
        openConnect,
        openDisconnect,
        openEdit,
        openDelete,
    ]);

    return (
        <>
            <Head title={t('comunicaciones:sesiones.title')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={t('comunicaciones:sesiones.title')}
                    description={t('comunicaciones:sesiones.description')}
                    stats={[
                        {
                            label: t('comunicaciones:sesiones.stats.total'),
                            value: stats.total,
                            variant: 'info',
                            icon: Smartphone,
                        },
                        {
                            label: t('comunicaciones:sesiones.stats.connected'),
                            value: stats.conectadas,
                            variant: 'success',
                            icon: CheckCircle2,
                        },
                        {
                            label: t('comunicaciones:sesiones.stats.pending'),
                            value: stats.pendientes,
                            variant: 'info',
                            icon: Radio,
                        },
                        {
                            label: t(
                                'comunicaciones:sesiones.stats.disconnected',
                            ),
                            value: stats.desconectadas,
                            variant: 'warning',
                            icon: PauseCircle,
                        },
                        {
                            label: t('comunicaciones:sesiones.stats.filters'),
                            value: activeFiltersCount,
                            variant: 'warning',
                            icon: Filter,
                        },
                        {
                            label: t('comunicaciones:sesiones.stats.matches'),
                            value: stats.coincidencias,
                            variant: 'primary',
                            icon: ScreenShare,
                        },
                    ]}
                    action={
                        <PlanLimitCreateButton
                            permission="whatsapp.connect"
                            reached={limitReached}
                            tooltip={t(
                                'comunicaciones:sesiones.plan_limit.max_whatsapp_sessions',
                            )}
                            onClick={openCreate}
                        >
                            <Plus className="size-4" strokeWidth={2.5} />
                            <span className="hidden sm:inline">
                                {t('comunicaciones:sesiones.actions.new')}
                            </span>
                            <span className="sm:hidden">
                                {t('comunicaciones:sesiones.actions.new_short')}
                            </span>
                        </PlanLimitCreateButton>
                    }
                />

                <DataTable
                    columns={columns}
                    data={paginated.data}
                    rowKey={(row) => row.id}
                    sort={sort}
                    onSortChange={setSort}
                    isLoading={isLoading}
                    ariaLiveMessage={t(
                        'comunicaciones:sesiones.aria.results_count_other',
                        { count: stats.coincidencias },
                    )}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder={t(
                                'comunicaciones:sesiones.search_placeholder',
                            )}
                        >
                            <FilterChips
                                ariaLabel={t(
                                    'comunicaciones:sesiones.filter_label',
                                )}
                                value={filters.estado}
                                onChange={(estado) => applyFilter({ estado })}
                                options={estadoOptions}
                            />
                        </DataToolbar>
                    }
                    footer={
                        <DataPagination
                            meta={paginated}
                            onPerPageChange={setPerPage}
                            preservedQuery={{
                                search: filters.search || undefined,
                                per_page: filters.per_page,
                                sort: filters.sort ?? undefined,
                                direction: filters.direction ?? undefined,
                                estado:
                                    filters.estado !== DEFAULT_ESTADO
                                        ? filters.estado
                                        : undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={
                                activeFiltersCount > 0 ? Activity : Smartphone
                            }
                            title={
                                activeFiltersCount > 0
                                    ? t(
                                          'comunicaciones:sesiones.empty.no_results_title',
                                      )
                                    : t(
                                          'comunicaciones:sesiones.empty.no_records_title',
                                      )
                            }
                            description={
                                activeFiltersCount > 0
                                    ? t(
                                          'comunicaciones:sesiones.empty.no_results_description',
                                      )
                                    : t(
                                          'comunicaciones:sesiones.empty.no_records_description',
                                      )
                            }
                            action={
                                activeFiltersCount === 0 &&
                                canCreate &&
                                !limitReached ? (
                                    <Button
                                        type="button"
                                        onClick={openCreate}
                                        className="cursor-pointer gap-2"
                                    >
                                        <Plus
                                            className="size-4"
                                            strokeWidth={2.5}
                                        />
                                        {t(
                                            'comunicaciones:sesiones.actions.create_first',
                                        )}
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />
            </div>

            <SessionFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                session={modal.type === 'edit' ? modal.session : null}
                sedes={sedes}
            />

            <SessionDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                session={modal.type === 'delete' ? modal.session : null}
            />

            <SessionConnectDialog
                open={modal.type === 'connect'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                session={modal.type === 'connect' ? modal.session : null}
                configured={openwa.configured}
            />

            <SessionDisconnectDialog
                open={modal.type === 'disconnect'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                session={modal.type === 'disconnect' ? modal.session : null}
            />
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Comunicaciones' },
            { title: 'Sesiones WhatsApp', href: '/comunicaciones/sesiones' },
        ]}
    >
        {page}
    </AppLayout>
);
