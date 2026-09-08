export type PlanEstadoFilter =
    | 'todos'
    | 'activos'
    | 'inactivos'
    | 'publicos'
    | 'privados';

export type PlanFeatureType = 'int' | 'bool' | 'str';

export type PlanFeatureCatalogEntry = {
    feature: string;
    type: PlanFeatureType;
    group: string;
    default: number | boolean | string | null;
};

export type PlanFeatureRow = {
    feature: string;
    valor_int: number | null;
    valor_bool: boolean | null;
    valor_str: string | null;
};

export type Plan = {
    id: string;
    codigo: string;
    nombre: string;
    descripcion: string | null;
    badge: string | null;
    color_hex: string | null;
    precio_mensual: string;
    precio_anual: string | null;
    trial_days: number;
    orden: number;
    es_publico: boolean;
    activo: boolean;
    created_at: string;
    updated_at: string;
    features_count: number;
    features: readonly PlanFeatureRow[];
};

export type PlanStats = {
    total: number;
    activos: number;
    inactivos: number;
    publicos: number;
    coincidencias: number;
};

export type PlanFilters = {
    search: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    estado: PlanEstadoFilter;
};

export function planIntFeature(
    plan: Plan,
    feature: string,
): number | null {
    const row = plan.features.find((item) => item.feature === feature);

    return row?.valor_int ?? null;
}
