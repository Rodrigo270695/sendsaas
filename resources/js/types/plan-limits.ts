export type PlanLimitFeature =
    | 'max_usuarios'
    | 'max_whatsapp_sessions'
    | 'max_outbound_per_day'
    | 'max_contacts'
    | 'max_campaigns'
    | 'max_automations'
    | 'max_sedes';

export type PlanLimitEntry = {
    limit: number | null;
    used: number;
    remaining: number | null;
    reached: boolean;
    unlimited: boolean;
};

export type PlanLimitsSnapshot = Partial<
    Record<PlanLimitFeature, PlanLimitEntry>
>;
