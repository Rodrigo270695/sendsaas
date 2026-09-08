import { cn } from '@/lib/utils';

export default function AuthChatScene() {
    return (
        <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 z-1 hidden overflow-hidden lg:block"
        >
            <Bubble
                className="auth-float-a top-[16%] left-[5%] max-w-56 delay-100"
                incoming
            >
                ¿Ya salió el lote de hoy?
            </Bubble>
            <Bubble className="auth-float-b top-[30%] left-[7%] max-w-56 delay-200">
                Enviado · 128 contactos
            </Bubble>
            <TypingBubble className="auth-float-c top-[44%] left-[6%] delay-300" />
            <Bubble
                className="auth-float-d right-[6%] bottom-[34%] max-w-48 delay-150"
                incoming
            >
                Recibido, gracias
            </Bubble>
            <Bubble className="auth-float-e right-[8%] bottom-[20%] max-w-48 delay-300">
                Entrega confirmada ✓✓
            </Bubble>
            <Bubble
                className="auth-float-a right-[10%] top-[22%] max-w-44 delay-500"
                incoming
            >
                ¿Pueden reenviar el PDF?
            </Bubble>
        </div>
    );
}

function Bubble({
    children,
    className,
    incoming = false,
}: {
    children: string;
    className?: string;
    incoming?: boolean;
}) {
    return (
        <div
            className={cn(
                'absolute rounded-2xl px-3.5 py-2 text-[0.7rem] leading-snug shadow-sm backdrop-blur-md',
                incoming
                    ? 'rounded-tl-sm border border-border/60 bg-card/70 text-muted-foreground'
                    : 'rounded-tr-sm bg-brand-600/90 text-white shadow-[0_10px_24px_-12px_rgba(171,60,61,0.7)]',
                className,
            )}
        >
            {children}
        </div>
    );
}

function TypingBubble({ className }: { className?: string }) {
    return (
        <div
            className={cn(
                'absolute flex items-center gap-1.5 rounded-2xl rounded-tl-sm border border-border/60 bg-card/70 px-3.5 py-2.5 shadow-sm backdrop-blur-md',
                className,
            )}
        >
            <span className="auth-typing-dot size-1.5 rounded-full bg-brand-500/80" />
            <span className="auth-typing-dot size-1.5 rounded-full bg-brand-500/80 [animation-delay:160ms]" />
            <span className="auth-typing-dot size-1.5 rounded-full bg-brand-500/80 [animation-delay:320ms]" />
        </div>
    );
}
