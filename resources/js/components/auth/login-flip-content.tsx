import { setLayoutProps } from '@inertiajs/react';
import {
    useEffect,
    useMemo,
    useRef,
    useState,
    type ReactNode,
    type RefObject,
} from 'react';
import { useTranslation } from 'react-i18next';
import ForgotPasswordForm from '@/components/auth/forgot-password-form';
import LoginForm from '@/components/auth/login-form';
import { cn } from '@/lib/utils';

type View = 'login' | 'forgot';

type LoginFlipContentProps = {
    canResetPassword: boolean;
};

export default function LoginFlipContent({
    canResetPassword,
}: LoginFlipContentProps) {
    const { t } = useTranslation('auth');
    const [view, setView] = useState<View>('login');
    const flipped = view === 'forgot';
    const viewMeta = useMemo<Record<View, { title: string; description: string }>>(
        () => ({
            login: {
                title: 'SendSaaS.',
                description: t('login.platform_description'),
            },
            forgot: {
                title: t('forgot.title'),
                description: t('forgot.description'),
            },
        }),
        [t],
    );

    const frontRef = useRef<HTMLDivElement>(null);
    const backRef = useRef<HTMLDivElement>(null);
    const [height, setHeight] = useState<number | undefined>(undefined);

    useEffect(() => {
        const target = flipped ? backRef.current : frontRef.current;
        if (!target) {
            return;
        }

        const measure = () => setHeight(target.offsetHeight);
        measure();

        const observer = new ResizeObserver(measure);
        observer.observe(target);
        return () => observer.disconnect();
    }, [flipped]);

    useEffect(() => {
        const timeoutId = window.setTimeout(() => {
            setLayoutProps(viewMeta[view]);
        }, 400);
        return () => window.clearTimeout(timeoutId);
    }, [view, viewMeta]);

    return (
        <div style={{ perspective: '1600px' }} className="relative">
            <div
                style={{
                    height,
                    transformStyle: 'preserve-3d',
                    transform: flipped ? 'rotateY(180deg)' : 'rotateY(0deg)',
                    transition:
                        'transform 800ms cubic-bezier(0.25, 0.1, 0.25, 1), height 800ms cubic-bezier(0.4, 0, 0.2, 1)',
                }}
                className="relative w-full"
            >
                <FlipFace ref={frontRef} hidden={flipped} rotation="front">
                    <LoginForm
                        canResetPassword={canResetPassword}
                        onForgotPassword={() => setView('forgot')}
                    />
                </FlipFace>

                <FlipFace ref={backRef} hidden={!flipped} rotation="back">
                    <ForgotPasswordForm onBackToLogin={() => setView('login')} />
                </FlipFace>
            </div>
        </div>
    );
}

type FlipFaceProps = {
    ref: RefObject<HTMLDivElement | null>;
    hidden: boolean;
    rotation: 'front' | 'back';
    children: ReactNode;
};

function FlipFace({ ref, hidden, rotation, children }: FlipFaceProps) {
    const isBack = rotation === 'back';

    return (
        <div
            ref={ref}
            aria-hidden={hidden}
            inert={hidden}
            style={{
                backfaceVisibility: 'hidden',
                WebkitBackfaceVisibility: 'hidden',
                transform: isBack ? 'rotateY(180deg)' : undefined,
                position: isBack ? 'absolute' : 'relative',
                inset: isBack ? 0 : undefined,
            }}
            className={cn('w-full', hidden && 'pointer-events-none')}
        >
            <div
                aria-hidden="true"
                className="absolute -inset-3 rounded-4xl bg-linear-to-br from-brand-500/35 via-brand-300/15 to-brand-800/30 opacity-70 blur-3xl"
            />

            <div
                className={cn(
                    'relative overflow-hidden rounded-3xl border border-white/50 bg-white/40 p-6 backdrop-blur-2xl backdrop-saturate-150 sm:p-8',
                    'shadow-[0_24px_70px_-18px_rgba(80,20,22,0.28),0_10px_28px_-14px_rgba(80,20,22,0.18),inset_0_1px_0_0_rgba(255,255,255,0.6)]',
                    'dark:border-white/10 dark:bg-card/30 dark:shadow-[0_24px_70px_-18px_rgba(0,0,0,0.7),inset_0_1px_0_0_rgba(255,255,255,0.08)]',
                )}
            >
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-x-6 top-0 h-px bg-linear-to-r from-transparent via-white/90 to-transparent dark:via-white/25"
                />

                <svg
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 h-full w-full opacity-[0.025] mix-blend-overlay dark:opacity-[0.08]"
                >
                    <filter id={`card-grain-${rotation}`}>
                        <feTurbulence
                            type="fractalNoise"
                            baseFrequency="2"
                            numOctaves="2"
                            stitchTiles="stitch"
                        />
                        <feColorMatrix type="saturate" values="0" />
                    </filter>
                    <rect
                        width="100%"
                        height="100%"
                        filter={`url(#card-grain-${rotation})`}
                    />
                </svg>

                <div className="relative z-10">{children}</div>
            </div>
        </div>
    );
}
