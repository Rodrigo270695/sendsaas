import { Head } from '@inertiajs/react';
import {
    Activity,
    Building2,
    CheckCircle2,
    Download,
    Filter,
    MapPin,
    PauseCircle,
    Plus,
    ScreenShare,
    Trash2,
} from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import {
    BulkAction,
    BulkActionBar,
    DataPagination,
    DataTable,
    DataToolbar,
    EmptyState,
    FilterChips,
    PageHeader,
    StatBadge,
} from '@/components/data-page';
import type { DataTableColumn, FilterChip } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import { useRowSelection } from '@/hooks/use-row-selection';
import AppLayout from '@/layouts/app-layout';
import sedes from '@/routes/configuracion/sedes';
import type { Paginated } from '@/types';
import { SedeBulkDeleteDialog } from './components/sede-bulk-delete-dialog';
import { SedeDeleteDialog } from './components/sede-delete-dialog';
import { SedeFormModal } from './components/sede-form-modal';
import { SedeRowActions } from './components/sede-row-actions';
import type {
    GeoOption,
    Sede,
    SedeEstadoFilter,
    SedeFilters,
    SedeStats,
} from './types';

type SedesIndexProps = {
    sedes: Paginated<Sede>;
    filters: SedeFilters;
    stats: SedeStats;
    departamentos: readonly GeoOption[];
};

type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; sede: Sede }
    | { type: 'delete'; sede: Sede }
    | { type: 'bulk-delete' };

const DEFAULT_PER_PAGE = 10;
const DEFAULT_ESTADO: SedeEstadoFilter = 'todas';

export default function Index({
    sedes: paginated,
    filters,
    stats,
    departamentos,
}: SedesIndexProps) {
    const { t } = useTranslation(['sedes', 'common']);
    const { can } = usePermission();
    const canCreate = can('sedes.create');
    const canUpdate = can('sedes.update');
    const canDelete = can('sedes.delete');
    const canExport = can('sedes.export');
    const canBulkDelete = can('sedes.bulk-delete');
    const showRowActions = canUpdate || canDelete;

    const {
        search,
        setSearch,
        isLoading,
        sort,
        setSort,
        setPerPage,
        applyFilter,
    } = useDataTablePage<{
        estado: SedeEstadoFilter;
    }>({
        routeUrl: sedes.index().url,
        initialFilters: filters,
        only: ['sedes', 'filters', 'stats'],
        errorMessage: t('toast.load_error'),
        storageKey: 'sendsaas.sedes.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const estadoOptions: readonly FilterChip<SedeEstadoFilter>[] = useMemo(
        () => [
            { value: 'todas', label: t('sedes:filters.all') },
            { value: 'activa', label: t('sedes:filters.active') },
            { value: 'inactiva', label: t('sedes:filters.inactive') },
        ],
        [t],
    );

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openCreate = useCallback(() => setModal({ type: 'create' }), []);
    const openEdit = useCallback(
        (sede: Sede) => setModal({ type: 'edit', sede }),
        [],
    );
    const openDelete = useCallback(
        (sede: Sede) => setModal({ type: 'delete', sede }),
        [],
    );
    const openBulkDelete = useCallback(
        () => setModal({ type: 'bulk-delete' }),
        [],
    );

    const selection = useRowSelection<Sede, string>({
        rows: paginated.data,
        rowKey: (sede) => sede.id,
    });

    const activeFiltersCount = useMemo(() => {
        let count = 0;
        if (filters.search) count += 1;
        if (filters.sort) count += 1;
        if (filters.estado !== DEFAULT_ESTADO) count += 1;
        if (filters.per_page !== DEFAULT_PER_PAGE) count += 1;
        return count;
    }, [filters.search, filters.sort, filters.estado, filters.per_page]);

    const exportUrl = useMemo(() => {
        const params = new URLSearchParams();
        if (filters.search) params.set('search', filters.search);
        if (filters.sort) params.set('sort', filters.sort);
        if (filters.direction) params.set('direction', filters.direction);
        if (filters.estado !== DEFAULT_ESTADO) {
            params.set('estado', filters.estado);
        }
        const qs = params.toString();
        return qs.length > 0
            ? `${sedes.export().url}?${qs}`
            : sedes.export().url;
    }, [filters.search, filters.sort, filters.direction, filters.estado]);

    const columns = useMemo<DataTableColumn<Sede>[]>(() => {
        const base: DataTableColumn<Sede>[] = [
            {
                key: 'codigo',
                header: t('sedes:columns.codigo'),
                sortable: true,
                cell: (sede) => (
                    <span className="font-mono text-xs text-foreground/80">
                        {sede.codigo}
                    </span>
                ),
            },
            {
                key: 'nombre',
                header: t('sedes:columns.nombre'),
                sortable: true,
                cell: (sede) => (
                    <div className="flex min-w-0 flex-col leading-tight">
                        <span className="truncate text-sm font-semibold">
                            {sede.nombre}
                        </span>
                        {sede.email ? (
                            <span className="truncate text-xs text-muted-foreground">
                                {sede.email}
                            </span>
                        ) : null}
                    </div>
                ),
            },
            {
                key: 'distrito',
                header: t('sedes:columns.ubicacion'),
                sortable: true,
                cell: (sede) => {
                    const parts = [
                        sede.distrito,
                        sede.provincia,
                        sede.departamento,
                    ].filter(Boolean);
                    if (parts.length === 0) {
                        return (
                            <span className="text-xs text-muted-foreground italic">
                                {t('sedes:row.no_location')}
                            </span>
                        );
                    }
                    return (
                        <span className="inline-flex items-center gap-1.5 text-xs">
                            <MapPin
                                className="size-3.5 shrink-0 text-muted-foreground"
                                strokeWidth={2.25}
                            />
                            <span className="truncate">{parts.join(' · ')}</span>
                        </span>
                    );
                },
            },
            {
                key: 'telefono',
                header: t('sedes:columns.telefono'),
                sortable: true,
                cell: (sede) =>
                    sede.telefono ? (
                        <span className="font-mono text-xs">
                            {sede.telefono}
                        </span>
                    ) : (
                        <span className="text-muted-foreground">—</span>
                    ),
            },
            {
                key: 'activa',
                header: t('sedes:columns.estado'),
                sortable: true,
                cell: (sede) => (
                    <StatBadge
                        label={
                            sede.activa
                                ? t('sedes:row.active')
                                : t('sedes:row.inactive')
                        }
                        value=""
                        variant={sede.activa ? 'success' : 'warning'}
                    />
                ),
            },
        ];

        if (showRowActions) {
            base.push({
                key: 'acciones',
                header: t('sedes:columns.acciones'),
                className: 'w-12',
                cell: (sede) => (
                    <SedeRowActions
                        sede={sede}
                        onEdit={openEdit}
                        onDelete={openDelete}
                        canUpdate={canUpdate}
                        canDelete={canDelete}
                    />
                ),
            });
        }

        return base;
    }, [t, showRowActions, canUpdate, canDelete, openEdit, openDelete]);

    return (
        <>
            <Head title={t('sedes:title')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={t('sedes:title')}
                    description={t('sedes:description')}
                    stats={[
                        {
                            label: t('sedes:stats.total'),
                            value: stats.total,
                            variant: 'info',
                            icon: Building2,
                        },
                        {
                            label: t('sedes:stats.active'),
                            value: stats.activas,
                            variant: 'success',
                            icon: CheckCircle2,
                        },
                        {
                            label: t('sedes:stats.inactive'),
                            value: stats.inactivas,
                            variant: 'warning',
                            icon: PauseCircle,
                        },
                        {
                            label: t('sedes:stats.filters'),
                            value: activeFiltersCount,
                            variant: 'warning',
                            icon: Filter,
                        },
                        {
                            label: t('sedes:stats.matches'),
                            value: stats.coincidencias,
                            variant: 'primary',
                            icon: ScreenShare,
                        },
                    ]}
                    action={
                        <div className="flex flex-row items-center gap-2">
                            {canExport && (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="cursor-pointer gap-2"
                                >
                                    <a href={exportUrl} download="sedes.xlsx">
                                        <Download
                                            className="size-4"
                                            strokeWidth={2.5}
                                        />
                                        <span className="hidden sm:inline">
                                            {t('common:actions.export_xlsx')}
                                        </span>
                                    </a>
                                </Button>
                            )}
                            <Can permission="sedes.create">
                                <Button
                                    type="button"
                                    onClick={openCreate}
                                    className="cursor-pointer gap-2"
                                >
                                    <Plus
                                        className="size-4"
                                        strokeWidth={2.5}
                                    />
                                    <span className="hidden sm:inline">
                                        {t('sedes:actions.new')}
                                    </span>
                                    <span className="sm:hidden">
                                        {t('sedes:actions.new_short')}
                                    </span>
                                </Button>
                            </Can>
                        </div>
                    }
                />

                <DataTable
                    columns={columns}
                    data={paginated.data}
                    rowKey={(sede) => sede.id}
                    sort={sort}
                    onSortChange={setSort}
                    isLoading={isLoading}
                    selection={canBulkDelete ? selection : undefined}
                    ariaLiveMessage={t('sedes:aria.results_count_other', {
                        count: stats.coincidencias,
                    })}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder={t('sedes:search_placeholder')}
                        >
                            <FilterChips
                                ariaLabel={t('sedes:filter_label')}
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
                            icon={activeFiltersCount > 0 ? Activity : Building2}
                            title={
                                activeFiltersCount > 0
                                    ? t('sedes:empty.no_results_title')
                                    : t('sedes:empty.no_records_title')
                            }
                            description={
                                activeFiltersCount > 0
                                    ? t('sedes:empty.no_results_description')
                                    : t('sedes:empty.no_records_description')
                            }
                            action={
                                activeFiltersCount === 0 && canCreate ? (
                                    <Button
                                        type="button"
                                        onClick={openCreate}
                                        className="cursor-pointer gap-2"
                                    >
                                        <Plus
                                            className="size-4"
                                            strokeWidth={2.5}
                                        />
                                        {t('sedes:actions.create_first')}
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />
            </div>

            <SedeFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                sede={modal.type === 'edit' ? modal.sede : null}
                departamentos={departamentos}
            />

            <SedeDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                sede={modal.type === 'delete' ? modal.sede : null}
            />

            <SedeBulkDeleteDialog
                open={modal.type === 'bulk-delete'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                ids={Array.from(selection.selectedIds)}
                onCompleted={() => selection.clear()}
            />

            {canBulkDelete && (
                <BulkActionBar
                    count={selection.count}
                    labels={{
                        singular: t('sedes:bulk.selected_singular'),
                        plural: t('sedes:bulk.selected_plural'),
                    }}
                    onClear={selection.clear}
                >
                    <BulkAction
                        type="button"
                        variant="destructive"
                        size="sm"
                        onClick={openBulkDelete}
                        className="cursor-pointer gap-1.5"
                    >
                        <Trash2 className="size-4" strokeWidth={2.5} />
                        <span className="hidden sm:inline">
                            {t('sedes:actions.delete_selected')}
                        </span>
                    </BulkAction>
                </BulkActionBar>
            )}
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Configuración' },
            { title: 'Sedes', href: '/configuracion/sedes' },
        ]}
    >
        {page}
    </AppLayout>
);
