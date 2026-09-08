import { useEffect, useRef } from 'react';

export default function AuthPointerGlow() {
    const glowRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const glow = glowRef.current;
        if (!glow) {
            return;
        }

        const onMove = (event: PointerEvent) => {
            glow.style.setProperty('--spot-x', `${event.clientX}px`);
            glow.style.setProperty('--spot-y', `${event.clientY}px`);
        };

        window.addEventListener('pointermove', onMove, { passive: true });
        return () => window.removeEventListener('pointermove', onMove);
    }, []);

    return (
        <div
            ref={glowRef}
            aria-hidden="true"
            className="auth-pointer-glow pointer-events-none absolute inset-0 z-[1]"
        />
    );
}
