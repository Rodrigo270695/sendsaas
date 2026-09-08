import type { ReactNode } from 'react';
import { Can } from '@/components/can';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type PlanLimitCreateButtonProps = {
    permission: string;
    reached: boolean;
    tooltip: string;
    onClick: () => void;
    children: ReactNode;
    className?: string;
};

export function PlanLimitCreateButton({
    permission,
    reached,
    tooltip,
    onClick,
    children,
    className = 'cursor-pointer gap-2',
}: PlanLimitCreateButtonProps) {
    return (
        <Can permission={permission}>
            <Tooltip>
                <TooltipTrigger asChild>
                    <span className="inline-flex">
                        <Button
                            type="button"
                            onClick={onClick}
                            disabled={reached}
                            className={className}
                        >
                            {children}
                        </Button>
                    </span>
                </TooltipTrigger>
                {reached ? (
                    <TooltipContent side="bottom" className="max-w-xs">
                        {tooltip}
                    </TooltipContent>
                ) : null}
            </Tooltip>
        </Can>
    );
}
