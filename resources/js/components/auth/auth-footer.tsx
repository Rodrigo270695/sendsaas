type AuthFooterProps = {
    brandName: string;
};

export default function AuthFooter({ brandName }: AuthFooterProps) {
    const year = new Date().getFullYear();

    return (
        <footer className="relative z-10 mx-auto flex w-full max-w-5xl flex-col items-center gap-2 px-6 pb-6 text-center text-xs text-muted-foreground/80 sm:flex-row sm:justify-between sm:pb-8">
            <span>
                © {year} {brandName} · Bandeja WhatsApp para equipos
            </span>
            <span>Hecho en Perú</span>
        </footer>
    );
}
