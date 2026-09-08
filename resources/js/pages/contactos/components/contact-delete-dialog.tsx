import { router } from '@inertiajs/react';
import { Loader2, TriangleAlert } from 'lucide-react';
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
import type { Contact } from '../types';

export type ContactDeleteDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    contact: Contact | null;
};

export function ContactDeleteDialog({
    open,
    onOpenChange,
    contact,
}: ContactDeleteDialogProps) {
    const { t } = useTranslation(['contactos', 'common']);
    const [processing, setProcessing] = useState(false);

    const onConfirm = () => {
        if (!contact) {
            return;
        }
        setProcessing(true);
        router.delete(`/contactos/${contact.id}`, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex size-11 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                        <TriangleAlert className="size-5" strokeWidth={2.5} aria-hidden />
                    </div>
                    <DialogTitle className="pt-2 text-base">
                        {t('contactos:delete.title')}
                    </DialogTitle>
                    <DialogDescription asChild>
                        <div className="text-sm">
                            <Trans
                                i18nKey="contactos:delete.description"
                                values={{ name: contact?.name ?? '' }}
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
                        variant="destructive"
                        onClick={onConfirm}
                        disabled={processing || !contact}
                        className="cursor-pointer gap-2"
                    >
                        {processing && <Loader2 className="size-4 animate-spin" />}
                        {processing
                            ? t('contactos:delete.loading')
                            : t('contactos:delete.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
