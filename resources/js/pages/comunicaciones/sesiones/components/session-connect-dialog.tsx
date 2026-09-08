import { router } from '@inertiajs/react';
import { Loader2, QrCode } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { toastManager } from '@/lib/toast';
import type { WhatsappSession } from '../types';

export type SessionConnectDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    session: WhatsappSession | null;
    configured: boolean;
};

type QrPayload = {
    ready?: boolean;
    qr_code?: string | null;
    status?: string;
    error?: string;
    message?: string;
    phone?: string | null;
};

const POLL_MS = 4000;

export function SessionConnectDialog({
    open,
    onOpenChange,
    session,
    configured,
}: SessionConnectDialogProps) {
    const { t } = useTranslation(['comunicaciones', 'common']);
    const [qrCode, setQrCode] = useState<string | null>(null);
    const [message, setMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const sessionIdRef = useRef<string | null>(null);
    const startConnectRef = useRef<() => void>(() => {});

    const stopPoll = useCallback(() => {
        if (pollRef.current !== null) {
            clearInterval(pollRef.current);
            pollRef.current = null;
        }
    }, []);

    const resetUi = useCallback(() => {
        setQrCode(null);
        setMessage(null);
        setError(null);
        setLoading(false);
    }, []);

    const fetchQr = useCallback(async () => {
        const id = sessionIdRef.current;
        if (!id) {
            return;
        }

        setLoading(true);
        try {
            const res = await fetch(`/comunicaciones/sesiones/${id}/qr`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const data = (await res.json()) as QrPayload;
            setError(
                data.error ??
                    (res.ok ? null : t('comunicaciones:sesiones.connect.qr_error')),
            );
            setMessage(data.message ?? null);

            if (data.ready) {
                setQrCode(null);
                stopPoll();
                toastManager.success({
                    title: t('comunicaciones:sesiones.connect.ready_title'),
                    description: data.phone
                        ? t('comunicaciones:sesiones.connect.ready_phone', {
                              phone: data.phone,
                          })
                        : undefined,
                    duration: 4000,
                });
                router.reload({ only: ['sessions', 'stats'] });
                onOpenChange(false);
                return;
            }

            if (data.qr_code) {
                setQrCode(data.qr_code);
                setError(null);
            }
        } catch {
            setError(t('comunicaciones:sesiones.connect.network_error'));
        } finally {
            setLoading(false);
        }
    }, [onOpenChange, stopPoll, t]);

    const startPoll = useCallback(() => {
        stopPoll();
        void fetchQr();
        pollRef.current = setInterval(() => {
            void fetchQr();
        }, POLL_MS);
    }, [fetchQr, stopPoll]);

    const startConnect = useCallback(() => {
        const id = sessionIdRef.current;
        if (!id) {
            return;
        }

        setQrCode(null);
        setError(null);
        setMessage(t('comunicaciones:sesiones.connect.starting'));
        setLoading(true);
        router.post(
            `/comunicaciones/sesiones/${id}/connect`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                only: ['sessions', 'stats', 'openwa'],
                onFinish: () => setLoading(false),
                onSuccess: () => startPoll(),
                onError: () => {
                    setMessage(null);
                    setError(t('comunicaciones:sesiones.connect.start_error'));
                },
            },
        );
    }, [startPoll, t]);

    startConnectRef.current = startConnect;

    useEffect(() => {
        if (!open || !session || !configured) {
            stopPoll();
            if (!open) {
                sessionIdRef.current = null;
                resetUi();
            }
            return;
        }

        sessionIdRef.current = session.id;
        startConnectRef.current();

        return () => stopPoll();
    }, [configured, open, session?.id, resetUi, stopPoll]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-base">
                        <QrCode className="size-5" strokeWidth={2.25} />
                        {t('comunicaciones:sesiones.connect.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('comunicaciones:sesiones.connect.description', {
                            name: session?.alias ?? '',
                        })}
                    </DialogDescription>
                </DialogHeader>

                {!configured ? (
                    <p className="rounded-lg border border-dashed px-3 py-4 text-sm text-muted-foreground">
                        {t('comunicaciones:sesiones.connect.not_configured')}
                    </p>
                ) : (
                    <div className="flex flex-col items-center gap-3">
                        {qrCode ? (
                            <>
                                <p className="text-center text-sm font-medium">
                                    {t('comunicaciones:sesiones.connect.scan')}
                                </p>
                                <img
                                    src={qrCode}
                                    alt={t('comunicaciones:sesiones.connect.qr_alt')}
                                    className="max-w-[220px] rounded-md border bg-white p-2"
                                />
                                <p className="text-center text-xs text-muted-foreground">
                                    {t('comunicaciones:sesiones.connect.steps')}
                                </p>
                            </>
                        ) : (
                            <p className="flex items-center gap-2 text-center text-sm text-muted-foreground">
                                {loading ? (
                                    <Loader2
                                        className="size-4 shrink-0 animate-spin"
                                        aria-hidden
                                    />
                                ) : null}
                                {error ??
                                    message ??
                                    t('comunicaciones:sesiones.connect.waiting')}
                            </p>
                        )}
                    </div>
                )}

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        className="cursor-pointer"
                    >
                        {t('common:actions.close')}
                    </Button>
                    {configured ? (
                        <Button
                            type="button"
                            onClick={startConnect}
                            disabled={loading}
                            className="cursor-pointer gap-2"
                        >
                            {loading ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <QrCode className="size-4" />
                            )}
                            {t('comunicaciones:sesiones.connect.refresh')}
                        </Button>
                    ) : null}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
