import { router } from '@inertiajs/react';
import { Loader2, LogOut } from 'lucide-react';
import { useState } from 'react';
import { Trans, useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { WhatsappSession } from '../types';

export type SessionDisconnectDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    session: WhatsappSession | null;
};

export function SessionDisconnectDialog({
    open,
    onOpenChange,
    session,
}: SessionDisconnectDialogProps) {
    const { t } = useTranslation(['comunicaciones', 'common']);
    const [processing, setProcessing] = useState(false);

    const onConfirm = () => {
        if (!session) {
            return;
        }
        setProcessing(true);
        router.post(
            `/comunicaciones/sesiones/${session.id}/disconnect`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex size-11 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400">
                        <LogOut
                            className="size-5"
                            strokeWidth={2.5}
                            aria-hidden
                        />
                    </div>
                    <DialogTitle className="pt-2 text-base">
                        {t('comunicaciones:sesiones.disconnect.title')}
                    </DialogTitle>
                    <DialogDescription asChild>
                        <div className="text-sm">
                            <Trans
                                i18nKey="comunicaciones:sesiones.disconnect.description"
                                values={{ name: session?.alias ?? '' }}
                                components={{ strong: <strong /> }}
                            />
                        </div>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                        className="cursor-pointer"
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="button"
                        onClick={onConfirm}
                        disabled={processing || !session}
                        className="cursor-pointer gap-2"
                    >
                        {processing ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <LogOut className="size-4" />
                        )}
                        {processing
                            ? t('comunicaciones:sesiones.disconnect.loading')
                            : t('comunicaciones:sesiones.disconnect.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
