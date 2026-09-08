import { Head, router } from '@inertiajs/react';
import {
    Activity,
    Building,
    CheckCircle2,
    Filter,
    PauseCircle,
    Plus,
    ScreenShare,
    Sparkles,
    XCircle,
} from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
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
import { Button } from '@/components/ui/button';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import tenants from '@/routes/plataforma/tenants';
import type { Paginated } from '@/types';
import { TenantDeleteDialog } from './components/tenant-delete-dialog';
import { TenantFormModal } from './components/tenant-form-modal';
import { TenantRowActions } from './components/tenant-row-actions';
import { TenantSuspendDialog } from './components/tenant-suspend-dialog';
import type {
    Tenant,
    TenantEstadoFilter,
    TenantFilters,
    TenantPlanFilterNone,
    TenantPlanOption,
    TenantStats,
} from './types';

type TenantsIndexProps = {
    tenants: Paginated<Tenant>;
    filters: TenantFilters;
    stats: TenantStats;
    plans_catalog: readonly TenantPlanOption[];
};

type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; tenant: Tenant }
    | { type: 'delete'; tenant: Tenant }
    | { type: 'suspend'; tenant: Tenant }
    | { type: 'resume'; tenant: Tenant };

const DEFAULT_PER_PAGE = 10;
const DEFAULT_ESTADO: TenantEstadoFilter = 'todos';
const PLAN_FILTER_ALL = 'todos';
const PLAN_FILTER_NONE: TenantPlanFilterNone = 'sin_plan';

function daysUntil(iso: string): number {
    const end = new Date(iso);
    const now = new Date();
    const startOfEnd = Date.UTC(
        end.getFullYear(),
        end.getMonth(),
        end.getDate(),
    );
    const startOfNow = Date.UTC(
        now.getFullYear(),
        now.getMonth(),
        now.getDate(),
    );

    return Math.round((startOfEnd - startOfNow) / 86_400_000);
}

export default function Index({
    tenants: paginated,
    filters,
    stats,
    plans_catalog,
}: TenantsIndexProps) {
    const { t } = useTranslation(['tenants', 'common']);
    const { can } = usePermission();
    const canCreate = can('plataforma-tenants.create');
    const canUpdate = can('plataforma-tenants.update');
    const canDelete = can('plataforma-tenants.delete');
    const canSuspend = can('plataforma-tenants.suspend');
    const canResume = can('plataforma-tenants.resume');
    const canImpersonate = can('plataforma-tenants.impersonate');
    const showRowActions =
        canUpdate || canDelete || canSuspend || canResume || canImpersonate;

    const enterSupport = useCallback((tenant: Tenant) => {
        router.post(tenants.impersonate.url(tenant.id));
    }, []);

    const {
        search,
        setSearch,
        isLoading,
        sort,
        setSort,
        setPerPage,
        applyFilter,
    } = useDataTablePage<{
        estado: TenantEstadoFilter;
        plan_id: string | TenantPlanFilterNone | null;
    }>({
        routeUrl: tenants.index().url,
        initialFilters: filters,
        only: ['tenants', 'filters', 'stats'],
        errorMessage: t('toast.load_error'),
        storageKey: 'sendsaas.plataforma.tenants.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const estadoOptions: readonly FilterChip<TenantEstadoFilter>[] = useMemo(
        () => [
            { value: 'todos', label: t('tenants:filters.all') },
            { value: 'trial', label: t('tenants:filters.trial') },
            { value: 'active', label: t('tenants:filters.active') },
            { value: 'suspended', label: t('tenants:filters.suspended') },
            { value: 'cancelled', label: t('tenants:filters.cancelled') },
        ],
        [t],
    );

    const planFilterValue = filters.plan_id ?? PLAN_FILTER_ALL;

    const planOptions = useMemo<readonly FilterChip<string>[]>(() => {
        const base: FilterChip<string>[] = [
            { value: PLAN_FILTER_ALL, label: t('tenants:filters.plan_all') },
            { value: PLAN_FILTER_NONE, label: t('tenants:filters.plan_none') },
        ];

        for (const plan of plans_catalog) {
            const hex =
                plan.color_hex && /^#[0-9a-fA-F]{3,6}$/.test(plan.color_hex)
                    ? plan.color_hex
                    : '#AB3C3D';

            base.push({
                value: plan.id,
                label: plan.nombre,
                icon: (
                    <span
                        className="size-2 shrink-0 rounded-full"
                        style={{ backgroundColor: hex }}
                    />
                ),
            });
        }

        return base;
    }, [plans_catalog, t]);

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });

    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openCreate = useCallback(() => setModal({ type: 'create' }), []);
    const openEdit = useCallback(
        (tenant: Tenant) => setModal({ type: 'edit', tenant }),
        [],
    );
    const openDelete = useCallback(
        (tenant: Tenant) => setModal({ type: 'delete', tenant }),
        [],
    );
    const openSuspend = useCallback(
        (tenant: Tenant) => setModal({ type: 'suspend', tenant }),
        [],
    );
    const openResume = useCallback(
        (tenant: Tenant) => setModal({ type: 'resume', tenant }),
        [],
    );

    const activeFiltersCount = useMemo(() => {
        let count = 0;
        if (filters.search) count += 1;
        if (filters.sort) count += 1;
        if (filters.estado !== DEFAULT_ESTADO) count += 1;
        if (filters.plan_id) count += 1;
        if (filters.per_page !== DEFAULT_PER_PAGE) count += 1;
        return count;
    }, [
        filters.search,
        filters.sort,
        filters.estado,
        filters.plan_id,
        filters.per_page,
    ]);

    const formatDate = (iso: string | null): string => {
        if (!iso) return '—';
        return new Date(iso).toLocaleDateString(undefined, {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    };

    const renderEstadoBadge = (tenant: Tenant) => {
        switch (tenant.estado) {
            case 'active':
                return (
                    <StatBadge
                        label={t('tenants:row.active')}
                        value=""
                        variant="success"
                    />
                );
            case 'trial':
                return (
                    <StatBadge
                        label={t('tenants:row.trial')}
                        value=""
                        variant="info"
                    />
                );
            case 'suspended':
                return (
                    <StatBadge
                        label={t('tenants:row.suspended')}
                        value=""
                        variant="warning"
                    />
                );
            case 'cancelled':
                return (
                    <StatBadge
                        label={t('tenants:row.cancelled')}
                        value=""
                        variant="danger"
                    />
                );
        }
    };

    const columns = useMemo<DataTableColumn<Tenant>[]>(() => {
        const base: DataTableColumn<Tenant>[] = [
            {
                key: 'slug',
                header: t('tenants:columns.tenant'),
                sortable: true,
                cell: (tenant) => (
                    <div className="flex items-center gap-2">
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <Building className="size-4" strokeWidth={2.5} />
                        </span>
                        <div className="flex min-w-0 flex-col leading-tight">
                            <span className="truncate text-sm font-semibold text-foreground">
                                {tenant.nombre_comercial || tenant.razon_social}
                            </span>
                            <span className="truncate font-mono text-xs text-muted-foreground">
                                {tenant.slug}
                            </span>
                        </div>
                    </div>
                ),
            },
            {
                key: 'contact',
                header: t('tenants:columns.contact'),
                cell: (tenant) => (
                    <div className="flex flex-col text-xs leading-tight">
                        <span className="truncate text-foreground/90">
                            {tenant.email_admin}
                        </span>
                        {tenant.telefono ? (
                            <span className="truncate font-mono text-muted-foreground">
                                {tenant.telefono}
                            </span>
                        ) : (
                            <span className="text-muted-foreground italic">
                                {t('tenants:row.no_phone')}
                            </span>
                        )}
                    </div>
                ),
            },
            {
                key: 'plan',
                header: t('tenants:columns.plan'),
                cell: (tenant) => {
                    const plan = tenant.plan;
                    if (!plan) {
                        return (
                            <span className="text-xs text-muted-foreground italic">
                                {t('tenants:row.no_plan')}
                            </span>
                        );
                    }
                    const hex =
                        plan.color_hex &&
                        /^#[0-9a-fA-F]{3,6}$/.test(plan.color_hex)
                            ? plan.color_hex
                            : '#AB3C3D';

                    return (
                        <div className="flex items-center gap-1.5">
                            <Sparkles
                                className="size-3.5 shrink-0"
                                strokeWidth={2.5}
                                style={{ color: hex }}
                            />
                            <span className="text-xs font-medium">
                                {plan.nombre}
                            </span>
                        </div>
                    );
                },
            },
            {
                key: 'estado',
                header: t('tenants:columns.status'),
                sortable: true,
                cell: renderEstadoBadge,
            },
            {
                key: 'trial_ends_at',
                header: t('tenants:columns.expiry'),
                sortable: true,
                cell: (tenant) => {
                    if (!tenant.trial_ends_at) {
                        return (
                            <span className="text-xs text-muted-foreground italic">
                                {t('tenants:row.no_expiry')}
                            </span>
                        );
                    }

                    const days = daysUntil(tenant.trial_ends_at);
                    const label =
                        days > 0
                            ? t('tenants:row.expiry_ok', { days })
                            : days === 0
                              ? t('tenants:row.expiry_today')
                              : t('tenants:row.expiry_overdue');

                    return (
                        <div className="flex flex-col text-xs leading-tight">
                            <span className="font-medium text-foreground">
                                {label}
                            </span>
                            <span className="text-muted-foreground">
                                {formatDate(tenant.trial_ends_at)}
                            </span>
                        </div>
                    );
                },
            },
            {
                key: 'created_at',
                header: t('tenants:columns.created_at'),
                sortable: true,
                cell: (tenant) => (
                    <span className="text-xs text-muted-foreground">
                        {formatDate(tenant.created_at)}
                    </span>
                ),
            },
        ];

        if (showRowActions) {
            base.push({
                key: 'acciones',
                header: (
                    <span className="md:sr-only">
                        {t('tenants:columns.acciones')}
                    </span>
                ),
                align: 'right',
                cell: (tenant: Tenant) => (
                    <div className="flex justify-end">
                        <TenantRowActions
                            tenant={tenant}
                            onEdit={openEdit}
                            onDelete={openDelete}
                            onSuspend={openSuspend}
                            onResume={openResume}
                            onEnterSupport={enterSupport}
                            canUpdate={canUpdate}
                            canDelete={canDelete}
                            canSuspend={canSuspend}
                            canResume={canResume}
                            canImpersonate={canImpersonate}
                        />
                    </div>
                ),
                className: 'w-12',
            });
        }

        return base;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        t,
        showRowActions,
        canUpdate,
        canDelete,
        canSuspend,
        canResume,
        canImpersonate,
        enterSupport,
        openEdit,
        openDelete,
        openSuspend,
        openResume,
    ]);

    return (
        <>
            <Head title={t('tenants:title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('tenants:title')}
                    description={t('tenants:description')}
                    stats={[
                        {
                            label: t('tenants:stats.total'),
                            value: stats.total,
                            variant: 'info',
                            icon: Building,
                        },
                        {
                            label: t('tenants:stats.active'),
                            value: stats.active,
                            variant: 'success',
                            icon: CheckCircle2,
                        },
                        {
                            label: t('tenants:stats.trial'),
                            value: stats.trial,
                            variant: 'primary',
                            icon: Sparkles,
                        },
                        {
                            label: t('tenants:stats.suspended'),
                            value: stats.suspended,
                            variant: 'warning',
                            icon: PauseCircle,
                        },
                        {
                            label: t('tenants:stats.cancelled'),
                            value: stats.cancelled,
                            variant: 'danger',
                            icon: XCircle,
                        },
                        {
                            label: t('tenants:stats.filters'),
                            value: activeFiltersCount,
                            variant: 'warning',
                            icon: Filter,
                        },
                        {
                            label: t('tenants:stats.matches'),
                            value: stats.coincidencias,
                            variant: 'primary',
                            icon: ScreenShare,
                        },
                    ]}
                    action={
                        <Can permission="plataforma-tenants.create">
                            <Button
                                type="button"
                                onClick={openCreate}
                                className="cursor-pointer gap-2"
                            >
                                <Plus className="size-4" strokeWidth={2.5} />
                                <span className="hidden sm:inline">
                                    {t('tenants:actions.new')}
                                </span>
                                <span className="sm:hidden">
                                    {t('tenants:actions.new_short')}
                                </span>
                            </Button>
                        </Can>
                    }
                />

                <DataTable
                    columns={columns}
                    data={paginated.data}
                    rowKey={(tenant) => tenant.id}
                    sort={sort}
                    onSortChange={setSort}
                    isLoading={isLoading}
                    ariaLiveMessage={t('tenants:aria.results_count_other', {
                        count: stats.coincidencias,
                    })}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder={t('tenants:search_placeholder')}
                        >
                            <FilterChips
                                ariaLabel={t('tenants:filter_label')}
                                value={filters.estado}
                                onChange={(estado) => applyFilter({ estado })}
                                options={estadoOptions}
                            />
                            <FilterChips
                                ariaLabel={t('tenants:filter_plan_label')}
                                value={planFilterValue}
                                onChange={(planId) =>
                                    applyFilter({
                                        plan_id:
                                            planId === PLAN_FILTER_ALL
                                                ? null
                                                : planId,
                                    })
                                }
                                options={planOptions}
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
                                plan_id: filters.plan_id ?? undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={
                                activeFiltersCount > 0 ? Activity : Building
                            }
                            title={
                                activeFiltersCount > 0
                                    ? t('tenants:empty.no_results_title')
                                    : t('tenants:empty.no_records_title')
                            }
                            description={
                                activeFiltersCount > 0
                                    ? t('tenants:empty.no_results_description')
                                    : t('tenants:empty.no_records_description')
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
                                        {t('tenants:actions.create_first')}
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />
            </div>

            <TenantFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                tenant={modal.type === 'edit' ? modal.tenant : null}
                plansCatalog={plans_catalog}
            />

            <TenantDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                tenant={modal.type === 'delete' ? modal.tenant : null}
            />

            <TenantSuspendDialog
                open={modal.type === 'suspend' || modal.type === 'resume'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                tenant={
                    modal.type === 'suspend' || modal.type === 'resume'
                        ? modal.tenant
                        : null
                }
                mode={modal.type === 'resume' ? 'resume' : 'suspend'}
            />
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma' },
            { title: 'Tenants', href: '/plataforma/tenants' },
        ]}
    >
        {page}
    </AppLayout>
);
