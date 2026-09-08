export type TenantEstado = 'trial' | 'active' | 'suspended' | 'cancelled';

export type TenantEstadoFilter = 'todos' | TenantEstado;

export type TenantPlanRef = {
    id: string;
    codigo: string;
    nombre: string;
    badge: string | null;
    color_hex: string | null;
};

export type Tenant = {
    id: string;
    slug: string;
    schema_name: string;
    razon_social: string;
    nombre_comercial: string | null;
    ruc: string | null;
    email_admin: string;
    telefono: string | null;
    estado: TenantEstado;
    trial_ends_at: string | null;
    suspended_at: string | null;
    suspension_reason: string | null;
    cancelled_at: string | null;
    timezone: string;
    locale: string;
    created_at: string;
    updated_at: string;
    plan: TenantPlanRef | null;
};

export type TenantStats = {
    total: number;
    trial: number;
    active: number;
    suspended: number;
    cancelled: number;
    coincidencias: number;
};

export type TenantPlanFilterNone = 'sin_plan';

export type TenantFilters = {
    search: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    estado: TenantEstadoFilter;
    plan_id: string | TenantPlanFilterNone | null;
};

export type TenantPlanOption = {
    id: string;
    codigo: string;
    nombre: string;
    trial_days: number;
    precio_mensual: string;
    color_hex: string | null;
};
