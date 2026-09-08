import { LoaderCircle } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { downloadXlsx } from '@/lib/download-file';
import { toastManager } from '@/lib/toast';
import { cn } from '@/lib/utils';

type XlsxDownloadButtonProps = {
    href: string;
    filename: string;
    className?: string;
    children: ReactNode;
};

export function XlsxDownloadButton({
    href,
    filename,
    className,
    children,
}: XlsxDownloadButtonProps) {
    const { t } = useTranslation('common');
    const [busy, setBusy] = useState(false);

    const handleClick = async () => {
        if (busy) {
            return;
        }

        setBusy(true);
        try {
            await downloadXlsx(href, filename);
        } catch {
            toastManager.error({ title: t('feedback.export_error') });
        } finally {
            setBusy(false);
        }
    };

    return (
        <Button
            type="button"
            variant="outline"
            className={cn('cursor-pointer gap-2', className)}
            disabled={busy}
            onClick={() => void handleClick()}
        >
            {busy ? <LoaderCircle className="size-4 animate-spin" aria-hidden /> : children}
        </Button>
    );
}
