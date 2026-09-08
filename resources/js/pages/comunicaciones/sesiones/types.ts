export type WhatsappSessionStatus =
    | 'created'
    | 'initializing'
    | 'qr_ready'
    | 'authenticating'
    | 'ready'
    | 'disconnected'
    | 'failed';

export type WhatsappSessionSede = {
    id: string;
    nombre: string;
    codigo: string;
};

export type WhatsappSession = {
    id: string;
    alias: string;
    openwa_session_name: string;
    status: WhatsappSessionStatus;
    phone: string | null;
    push_name: string | null;
    sede_id: string | null;
    sede: WhatsappSessionSede | null;
    auto_reconnect: boolean;
    connected_at: string | null;
    last_synced_at: string | null;
    last_error: string | null;
    created_at: string;
    updated_at: string;
};

export type SedeOption = {
    id: string;
    nombre: string;
    codigo: string;
};

export type SessionStats = {
    total: number;
    conectadas: number;
    pendientes: number;
    desconectadas: number;
    coincidencias: number;
};

export type SessionEstadoFilter =
    | 'todas'
    | 'conectada'
    | 'pendiente'
    | 'desconectada';

export type SessionFilters = {
    search: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    estado: SessionEstadoFilter;
};
