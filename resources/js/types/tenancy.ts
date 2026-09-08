export type TenancyShared = {
    root_domain: string;
    scheme: string;
    login_path: string;
};

export type TenantImpersonationShared = {
    tenant_id: string;
    tenant_label: string;
};
