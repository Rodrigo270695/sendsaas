import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toastManager, type ToastType } from '@/lib/toast';

type FlashShape = {
    id?: string | null;
    success?: string | null;
    error?: string | null;
    info?: string | null;
    warning?: string | null;
};

const FLASH_KEYS: { key: keyof FlashShape; type: ToastType }[] = [
    { key: 'success', type: 'success' },
    { key: 'error', type: 'error' },
    { key: 'info', type: 'info' },
    { key: 'warning', type: 'warning' },
];

function showFlashes(flash: FlashShape): void {
    for (const { key, type } of FLASH_KEYS) {
        const message = flash[key];

        if (typeof message === 'string' && message.length > 0) {
            toastManager.add({ type, title: message });
        }
    }
}

type InertiaPage = { props?: { flash?: FlashShape | null } };
type InertiaEvent = CustomEvent<{ page?: InertiaPage }>;

export function useFlashToast(): void {
    const lastShownIdRef = useRef<string | null>(null);

    useEffect(() => {
        const handle = (flash: FlashShape | null | undefined): void => {
            if (!flash || typeof flash.id !== 'string' || flash.id === '') {
                return;
            }

            if (lastShownIdRef.current === flash.id) {
                return;
            }

            lastShownIdRef.current = flash.id;
            showFlashes(flash);
        };

        const initialPage = (router as unknown as { page?: InertiaPage }).page;
        handle(initialPage?.props?.flash);

        const removeSuccess = router.on('success', (event) => {
            handle((event as InertiaEvent).detail?.page?.props?.flash);
        });

        const removeError = router.on('error', (event) => {
            handle((event as InertiaEvent).detail?.page?.props?.flash);
        });

        return () => {
            removeSuccess();
            removeError();
        };
    }, []);
}
