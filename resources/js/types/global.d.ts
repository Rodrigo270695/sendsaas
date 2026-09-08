import type { Auth } from '@/types/auth';
import type { TenantShared } from '@/types/tenant';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            tenant: TenantShared | null;
            auth: Auth;
            locale: string;
            timezone: string;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
