import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type AuthFormCardProps = {
    children: ReactNode;
    className?: string;
};

export default function AuthFormCard({
    children,
    className,
}: AuthFormCardProps) {
    return (
        <div className="relative">
            <div
                aria-hidden="true"
                className="absolute -inset-3 rounded-4xl bg-linear-to-br from-brand-500/35 via-brand-300/15 to-brand-800/30 opacity-70 blur-3xl"
            />

            <div
                className={cn(
                    'relative overflow-hidden rounded-3xl border border-white/50 bg-white/40 p-6 backdrop-blur-2xl backdrop-saturate-150 sm:p-8',
                    'shadow-[0_24px_70px_-18px_rgba(80,20,22,0.28),0_10px_28px_-14px_rgba(80,20,22,0.18),inset_0_1px_0_0_rgba(255,255,255,0.6)]',
                    'dark:border-white/10 dark:bg-card/30 dark:shadow-[0_24px_70px_-18px_rgba(0,0,0,0.7),inset_0_1px_0_0_rgba(255,255,255,0.08)]',
                    className,
                )}
            >
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-x-6 top-0 h-px bg-linear-to-r from-transparent via-white/90 to-transparent dark:via-white/25"
                />

                <div className="relative z-10">{children}</div>
            </div>
        </div>
    );
}
