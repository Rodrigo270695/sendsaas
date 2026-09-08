import { AlertTriangle, Check, Info, X } from 'lucide-react';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

/**
 * Toaster global de SendSaaS.
 *
 * - Esquina superior derecha.
 * - Iconos Lucide (check visible, sin el círculo nativo de Sonner).
 * - Colores de marca (#AB3C3D).
 */
function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="top-right"
            richColors
            closeButton
            expand
            duration={4000}
            offset={16}
            gap={10}
            icons={{
                success: <Check className="size-4" strokeWidth={2.75} />,
                error: <X className="size-4" strokeWidth={2.75} />,
                info: <Info className="size-4" strokeWidth={2.5} />,
                warning: <AlertTriangle className="size-4" strokeWidth={2.5} />,
                close: <X className="size-3.5" strokeWidth={2.5} />,
            }}
            toastOptions={{
                classNames: {
                    toast: 'group toast pointer-events-auto rounded-xl border border-border/60 bg-card text-foreground shadow-lg shadow-brand-900/8 ring-1 ring-brand-600/10 backdrop-blur-sm',
                    title: 'text-sm font-semibold',
                    description: 'text-xs text-muted-foreground',
                    actionButton:
                        'rounded-md bg-primary px-2.5 py-1 text-xs font-semibold text-primary-foreground cursor-pointer hover:bg-primary/90',
                    cancelButton:
                        'rounded-md bg-muted px-2.5 py-1 text-xs font-semibold text-muted-foreground cursor-pointer hover:bg-muted/80',
                    closeButton:
                        'cursor-pointer rounded-md text-muted-foreground hover:bg-brand-50 hover:text-brand-700',
                    success: 'sendsaas-toast-success',
                    error: 'sendsaas-toast-error',
                    info: 'sendsaas-toast-info',
                    warning: 'sendsaas-toast-warning',
                },
            }}
            style={
                {
                    '--normal-bg': 'var(--card)',
                    '--normal-text': 'var(--foreground)',
                    '--normal-border': 'var(--border)',
                    '--success-bg': 'var(--brand-50)',
                    '--success-text': 'var(--brand-700)',
                    '--success-border': 'var(--brand-200)',
                    '--error-bg': 'oklch(0.97 0.03 25)',
                    '--error-text': 'var(--destructive)',
                    '--error-border': 'oklch(0.85 0.1 25)',
                    '--info-bg': 'oklch(0.97 0.02 25)',
                    '--info-text': 'var(--brand-700)',
                    '--info-border': 'var(--brand-200)',
                    '--warning-bg': 'oklch(0.98 0.04 80)',
                    '--warning-text': 'oklch(0.55 0.16 70)',
                    '--warning-border': 'oklch(0.85 0.12 75)',
                    '--toast-close-button-start': 'unset',
                    '--toast-close-button-end': '0',
                    '--toast-close-button-transform': 'translate(35%, -35%)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
