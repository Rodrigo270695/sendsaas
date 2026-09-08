export type ContactTag = {
    id: string;
    name: string;
    color: string;
};

export type ContactSede = {
    id: string;
    nombre: string;
    codigo: string;
};

export type Contact = {
    id: string;
    name: string;
    phone: string;
    email: string | null;
    notes: string | null;
    sede_id: string | null;
    sede: ContactSede | null;
    tags: ContactTag[];
    custom_fields: Record<string, string> | null;
    last_contact_at: string | null;
    created_at: string;
    updated_at: string;
};

export type SedeOption = {
    id: string;
    nombre: string;
    codigo: string;
};

export type ContactStats = {
    total: number;
    con_variables: number;
    con_email: number;
    coincidencias: number;
};

export type ContactFilters = {
    search: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
};
