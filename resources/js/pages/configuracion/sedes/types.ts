export type SedeDistritoChain = {
    id: number;
    name: string;
    provincia_id: number;
    provincia: {
        id: number;
        name: string;
        departamento_id: number;
        departamento: {
            id: number;
            name: string;
        };
    };
} | null;

export type Sede = {
    id: string;
    nombre: string;
    codigo: string;
    direccion: string | null;
    telefono: string | null;
    email: string | null;
    distrito_id: number | null;
    distrito: string | null;
    provincia: string | null;
    departamento: string | null;
    distrito_model: SedeDistritoChain;
    activa: boolean;
    created_at: string;
    updated_at: string;
};

export type GeoOption = {
    id: number;
    name: string;
};

export type SedeStats = {
    total: number;
    activas: number;
    inactivas: number;
    coincidencias: number;
};

export type SedeEstadoFilter = 'todas' | 'activa' | 'inactiva';

export type SedeFilters = {
    search: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    estado: SedeEstadoFilter;
};
