export default function AuthAuroraBackground() {
    return (
        <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 z-0 overflow-hidden"
        >
            <div className="aurora-blob-1 absolute -top-32 -left-24 size-168 rounded-full bg-[radial-gradient(circle_at_center,oklch(0.91_0.04_25/0.85),transparent_60%)] blur-3xl dark:bg-[radial-gradient(circle_at_center,oklch(0.32_0.06_25/0.5),transparent_60%)]" />
            <div className="aurora-blob-2 absolute top-32 -right-28 size-144 rounded-full bg-[radial-gradient(circle_at_center,oklch(0.78_0.08_25/0.7),transparent_60%)] blur-3xl dark:bg-[radial-gradient(circle_at_center,oklch(0.28_0.05_25/0.55),transparent_60%)]" />
            <div className="aurora-blob-3 absolute -bottom-40 left-1/3 size-160 rounded-full bg-[radial-gradient(circle_at_center,oklch(0.7_0.1_25/0.45),transparent_60%)] blur-3xl dark:bg-[radial-gradient(circle_at_center,oklch(0.22_0.04_25/0.55),transparent_60%)]" />

            <svg className="absolute inset-0 h-full w-full opacity-[0.025] mix-blend-overlay dark:opacity-[0.06]">
                <filter id="auth-grain">
                    <feTurbulence
                        type="fractalNoise"
                        baseFrequency="0.85"
                        numOctaves="2"
                        stitchTiles="stitch"
                    />
                    <feColorMatrix type="saturate" values="0" />
                </filter>
                <rect width="100%" height="100%" filter="url(#auth-grain)" />
            </svg>
        </div>
    );
}
