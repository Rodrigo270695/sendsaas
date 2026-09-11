import { SendHorizontal } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePlanLimits } from '@/hooks/use-plan-limits';
import { cn } from '@/lib/utils';

type OutboundQuotaCardProps = {
    sendWindowStart: string;
    sendWindowEnd: string;
    timezone: string;
    className?: string;
};

export function OutboundQuotaCard({
    sendWindowStart,
    sendWindowEnd,
    timezone,
    className,
}: OutboundQuotaCardProps) {
    const { t } = useTranslation('config-suscripcion');
    const outbound = usePlanLimits()?.max_outbound_per_day;

    if (!outbound) {
        return null;
    }

    const unlimited = outbound.unlimited || outbound.limit === null;
    const limit = outbound.limit ?? 0;
    const progressPct = unlimited
        ? 100
        : limit > 0
          ? Math.min(100, (outbound.used / limit) * 100)
          : 0;
    const over = !unlimited && outbound.used >= limit;

    const usageLabel = unlimited
        ? t('outbound.usage_unlimited', { used: outbound.used })
        : t('outbound.usage', { used: outbound.used, limit });

    return (
        <section
            className={cn(
                'overflow-hidden rounded-xl border border-border/60 bg-card/80 p-4 shadow-sm ring-1 ring-border/20',
                className,
            )}
        >
            <div className="mb-3 flex items-start justify-between gap-2">
                <div>
                    <h3 className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <SendHorizontal className="size-3.5" />
                        {t('outbound.title')}
                    </h3>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {t('outbound.description')}
                    </p>
                </div>
            </div>

            <p className="text-sm font-semibold tabular-nums text-foreground">
                {usageLabel}
            </p>

            {!unlimited && limit > 0 ? (
                <div className="mt-3 space-y-1">
                    <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                        <span>{`${Math.round(progressPct)}%`}</span>
                        {outbound.remaining !== null ? (
                            <span className="tabular-nums">
                                {t('outbound.remaining', {
                                    count: outbound.remaining,
                                })}
                            </span>
                        ) : null}
                    </div>
                    <div
                        className="h-1.5 overflow-hidden rounded-full bg-muted/60"
                        role="progressbar"
                        aria-valuenow={outbound.used}
                        aria-valuemin={0}
                        aria-valuemax={limit}
                        aria-label={usageLabel}
                    >
                        <div
                            className={cn(
                                'h-full rounded-full transition-all',
                                over
                                    ? 'bg-red-500'
                                    : progressPct >= 75
                                      ? 'bg-amber-500'
                                      : 'bg-brand-600',
                            )}
                            style={{ width: `${progressPct}%` }}
                        />
                    </div>
                </div>
            ) : (
                <p className="mt-2 text-[11px] text-muted-foreground">
                    {t('outbound.unlimited_hint')}
                </p>
            )}

            <p className="mt-3 text-[11px] text-muted-foreground">
                {t('outbound.window', {
                    start: sendWindowStart,
                    end: sendWindowEnd,
                })}
            </p>
            <p className="mt-1 text-[11px] text-muted-foreground">
                {t('outbound.reset_hint', { timezone })}
            </p>
        </section>
    );
}
