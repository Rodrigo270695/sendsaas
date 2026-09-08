import { cn } from '@/lib/utils';

export default function AppLogoIcon({
    className,
}: {
    className?: string;
}) {
    return (
        <img
            src="/logo-mark.png"
            alt=""
            className={cn('object-contain', className)}
        />
    );
}
