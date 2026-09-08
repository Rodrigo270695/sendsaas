export default function AuthChatScene() {
    return (
        <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 z-[1] hidden overflow-hidden lg:block"
        >
            <Bubble className="top-[18%] left-[6%] max-w-52" incoming>
                ¿Ya salió el lote de hoy?
            </Bubble>
            <Bubble className="top-[32%] left-[8%] max-w-56">
                Enviado · 128 contactos
            </Bubble>
            <Bubble className="right-[7%] bottom-[28%] max-w-48" incoming>
                Recibido, gracias
            </Bubble>
            <Bubble className="right-[9%] bottom-[16%] max-w-44">
                Entrega confirmada ✓✓
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
            className={`absolute rounded-2xl px-3.5 py-2 text-[0.7rem] leading-snug shadow-sm backdrop-blur-md ${
                incoming
                    ? 'rounded-tl-sm border border-border/60 bg-card/70 text-muted-foreground'
                    : 'rounded-tr-sm bg-brand-600/90 text-white'
            } ${className ?? ''}`}
        >
            {children}
        </div>
    );
}
