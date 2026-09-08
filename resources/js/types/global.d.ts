import type { Auth } from '@/types/auth';
import type { TenantShared } from '@/types/tenant';
import type { FlashToast } from '@/types/ui';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            greeting_name: string;
            tenant: TenantShared | null;
            auth: Auth;
            locale: string;
            contact_whatsapp: string;
            timezone: string;
            sidebarOpen: boolean;
            flash: FlashToast | null;
            [key: string]: unknown;
        };
    }
}
