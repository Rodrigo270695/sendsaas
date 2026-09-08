import { Head } from '@inertiajs/react';
import {
    Activity,
    AtSign,
    Download,
    Filter,
    Plus,
    ScreenShare,
    Tags,
    Upload,
    Users,
} from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    BulkImportModal,
    DataPagination,
    DataTable,
    DataToolbar,
    EmptyState,
    PageHeader,
} from '@/components/data-page';
import type { DataTableColumn } from '@/components/data-page';
import { PlanLimitCreateButton } from '@/components/plan-limit-create-button';
import { Button } from '@/components/ui/button';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import { usePlanLimitReached } from '@/hooks/use-plan-limits';
import AppLayout from '@/layouts/app-layout';
import type { Paginated } from '@/types';
import { ContactDeleteDialog } from './components/contact-delete-dialog';
import { ContactFormModal } from './components/contact-form-modal';
import { ContactRowActions } from './components/contact-row-actions';
import type {
    Contact,
    ContactFilters,
    ContactStats,
    SedeOption,
} from './types';

type ContactsIndexProps = {
    contacts: Paginated<Contact>;
    filters: ContactFilters;
    stats: ContactStats;
    sedes: readonly SedeOption[];
};

type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; contact: Contact }
    | { type: 'delete'; contact: Contact }
    | { type: 'import' };

const DEFAULT_PER_PAGE = 10;

export default function Index({
    contacts: paginated,
    filters,
    stats,
    sedes,
}: ContactsIndexProps) {
    const { t } = useTranslation(['contactos', 'common']);
    const { can } = usePermission();
    const canCreate = can('contacts.create');
    const canUpdate = can('contacts.update');
    const canDelete = can('contacts.delete');
    const canExport = can('contacts.export');
    const showRowActions = canUpdate || canDelete;
    const limitReached = usePlanLimitReached('max_contacts');

    const { search, setSearch, isLoading, sort, setSort, setPerPage } =
        useDataTablePage({
            routeUrl: '/contactos',
            initialFilters: filters,
            only: ['contacts', 'filters', 'stats'],
            errorMessage: t('contactos:toast.load_error'),
            storageKey: 'sendsaas.contactos.prefs',
            defaults: {
                per_page: DEFAULT_PER_PAGE,
                sort: null,
                direction: null,
            },
        });

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openCreate = useCallback(() => {
        if (limitReached) {
            return;
        }
        setModal({ type: 'create' });
    }, [limitReached]);

    const activeFiltersCount = useMemo(() => {
        let count = 0;
        if (filters.search) count += 1;
        if (filters.sort) count += 1;
        if (filters.per_page !== DEFAULT_PER_PAGE) count += 1;
        return count;
    }, [filters.search, filters.sort, filters.per_page]);

    const exportUrl = useMemo(() => {
        const qs = new URLSearchParams();
        if (filters.search) qs.set('search', filters.search);
        const query = qs.toString();
        return query ? `/contactos/export?${query}` : '/contactos/export';
    }, [filters.search]);

    const columns = useMemo<DataTableColumn<Contact>[]>(() => {
        const base: DataTableColumn<Contact>[] = [
            {
                key: 'name',
                header: t('contactos:columns.nombre'),
                sortable: true,
                cell: (row) => (
                    <div className="flex min-w-0 flex-col leading-tight">
                        <span className="truncate text-sm font-semibold">
                            {row.name}
                        </span>
                        {row.email ? (
                            <span className="truncate text-[11px] text-muted-foreground">
                                {row.email}
                            </span>
                        ) : null}
                    </div>
                ),
            },
            {
                key: 'phone',
                header: t('contactos:columns.telefono'),
                sortable: true,
                cell: (row) => (
                    <span className="font-mono text-xs">{row.phone}</span>
                ),
            },
            {
                key: 'sede',
                header: t('contactos:columns.sede'),
                cell: (row) =>
                    row.sede ? (
                        <span className="text-xs">
                            {row.sede.nombre}{' '}
                            <span className="font-mono text-muted-foreground">
                                · {row.sede.codigo}
                            </span>
                        </span>
                    ) : (
                        <span className="text-xs italic text-muted-foreground">
                            {t('contactos:row.no_sede')}
                        </span>
                    ),
            },
            {
                key: 'tags',
                header: t('contactos:columns.etiquetas'),
                cell: (row) =>
                    row.tags.length > 0 ? (
                        <div className="flex flex-wrap gap-1">
                            {row.tags.map((tag) => (
                                <span
                                    key={tag.id}
                                    className="rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-medium text-brand-800 dark:bg-brand-950/40 dark:text-brand-200"
                                >
                                    {tag.name}
                                </span>
                            ))}
                        </div>
                    ) : (
                        <span className="text-xs italic text-muted-foreground">
                            {t('contactos:row.no_tags')}
                        </span>
                    ),
            },
            {
                key: 'custom_fields',
                header: t('contactos:columns.variables'),
                cell: (row) => {
                    const entries = Object.entries(row.custom_fields ?? {});
                    if (entries.length === 0) {
                        return (
                            <span className="text-xs italic text-muted-foreground">
                                {t('contactos:row.no_vars')}
                            </span>
                        );
                    }
                    return (
                        <div className="flex flex-wrap gap-1">
                            {entries.slice(0, 3).map(([key, value]) => (
                                <span
                                    key={key}
                                    className="rounded-md bg-muted px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground"
                                >
                                    {key}={value}
                                </span>
                            ))}
                        </div>
                    );
                },
            },
        ];

        if (showRowActions) {
            base.push({
                key: 'acciones',
                header: t('contactos:columns.acciones'),
                className: 'w-12',
                cell: (row) => (
                    <ContactRowActions
                        contact={row}
                        onEdit={(c) => setModal({ type: 'edit', contact: c })}
                        onDelete={(c) => setModal({ type: 'delete', contact: c })}
                        canUpdate={canUpdate}
                        canDelete={canDelete}
                    />
                ),
            });
        }

        return base;
    }, [t, showRowActions, canUpdate, canDelete]);

    return (
        <>
            <Head title={t('contactos:title')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={t('contactos:title')}
                    description={t('contactos:description')}
                    stats={[
                        {
                            label: t('contactos:stats.total'),
                            value: stats.total,
                            variant: 'info',
                            icon: Users,
                        },
                        {
                            label: t('contactos:stats.variables'),
                            value: stats.con_variables,
                            variant: 'warning',
                            icon: Tags,
                        },
                        {
                            label: t('contactos:stats.email'),
                            value: stats.con_email,
                            variant: 'success',
                            icon: AtSign,
                        },
                        {
                            label: t('contactos:stats.filters'),
                            value: activeFiltersCount,
                            variant: 'warning',
                            icon: Filter,
                        },
                        {
                            label: t('contactos:stats.matches'),
                            value: stats.coincidencias,
                            variant: 'primary',
                            icon: ScreenShare,
                        },
                    ]}
                    action={
                        <div className="flex flex-row items-center gap-2">
                            {canExport ? (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="cursor-pointer gap-2"
                                >
                                    <a href={exportUrl} download="contactos.xlsx">
                                        <Download
                                            className="size-4"
                                            strokeWidth={2.5}
                                        />
                                        <span className="hidden sm:inline">
                                            {t('common:actions.export_xlsx')}
                                        </span>
                                    </a>
                                </Button>
                            ) : null}
                            {canCreate ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setModal({ type: 'import' })}
                                    className="cursor-pointer gap-2"
                                >
                                    <Upload className="size-4" strokeWidth={2.5} />
                                    <span className="hidden sm:inline">
                                        {t('contactos:actions.bulk_import')}
                                    </span>
                                    <span className="sm:hidden">
                                        {t('contactos:actions.bulk_import_short')}
                                    </span>
                                </Button>
                            ) : null}
                            <PlanLimitCreateButton
                                permission="contacts.create"
                                reached={limitReached}
                                tooltip={t('contactos:plan_limit.max_contacts')}
                                onClick={openCreate}
                            >
                                <Plus className="size-4" strokeWidth={2.5} />
                                <span className="hidden sm:inline">
                                    {t('contactos:actions.new')}
                                </span>
                                <span className="sm:hidden">
                                    {t('contactos:actions.new_short')}
                                </span>
                            </PlanLimitCreateButton>
                        </div>
                    }
                />

                <DataTable
                    columns={columns}
                    data={paginated.data}
                    rowKey={(row) => row.id}
                    sort={sort}
                    onSortChange={setSort}
                    isLoading={isLoading}
                    ariaLiveMessage={t('contactos:aria.results_count_other', {
                        count: stats.coincidencias,
                    })}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder={t('contactos:search_placeholder')}
                        />
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
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={activeFiltersCount > 0 ? Activity : Users}
                            title={
                                activeFiltersCount > 0
                                    ? t('contactos:empty.no_results_title')
                                    : t('contactos:empty.no_records_title')
                            }
                            description={
                                activeFiltersCount > 0
                                    ? t('contactos:empty.no_results_description')
                                    : t('contactos:empty.no_records_description')
                            }
                            action={
                                activeFiltersCount === 0 && canCreate ? (
                                    <div className="flex flex-wrap justify-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setModal({ type: 'import' })
                                            }
                                            className="cursor-pointer gap-2"
                                        >
                                            <Upload className="size-4" />
                                            {t('contactos:actions.bulk_import')}
                                        </Button>
                                        {!limitReached ? (
                                            <Button
                                                type="button"
                                                onClick={openCreate}
                                                className="cursor-pointer gap-2"
                                            >
                                                <Plus className="size-4" />
                                                {t('contactos:actions.create_first')}
                                            </Button>
                                        ) : null}
                                    </div>
                                ) : undefined
                            }
                        />
                    }
                />
            </div>

            <ContactFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                contact={modal.type === 'edit' ? modal.contact : null}
                sedes={sedes}
            />

            <ContactDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                contact={modal.type === 'delete' ? modal.contact : null}
            />

            <BulkImportModal
                open={modal.type === 'import'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                translationNs="contactos"
                templateUrl="/contactos/plantilla"
                importUrl="/contactos/import"
                reloadOnly={['contacts', 'stats', 'plan_limits']}
            />
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Contactos', href: '/contactos' },
        ]}
    >
        {page}
    </AppLayout>
);
