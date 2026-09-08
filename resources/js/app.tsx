import { createInertiaApp, router } from '@inertiajs/react';
import '@/lib/i18n';
import PwaInstallBanner from '@/components/pwa-install-banner';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { capturePwaInstallPrompt } from '@/lib/pwa-install';

const appName = import.meta.env.VITE_APP_NAME || 'OmniDesk';

capturePwaInstallPrompt();

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <PwaInstallBanner />
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#AB3C3D',
    },
});

initializeTheme();

router.on('invalid', () => {
    window.location.reload();
});

window.addEventListener('unhandledrejection', (event) => {
    const reason = event.reason;
    const message =
        typeof reason === 'string'
            ? reason
            : reason instanceof Error
              ? reason.message
              : '';

    if (
        message.includes('Failed to fetch dynamically imported module') ||
        message.includes('Importing a module script failed') ||
        message.includes('error loading dynamically imported module')
    ) {
        event.preventDefault();
        window.location.reload();
    }
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js')
            .then((registration) => {
                void registration.update();
            })
            .catch(() => {
                /* sin SW la app sigue funcionando */
            });
    });
}
