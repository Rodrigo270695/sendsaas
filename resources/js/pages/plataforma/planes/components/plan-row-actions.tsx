import { Copy, KeyRound, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { toastManager } from '@/lib/toast';
import type { Plan } from '../types';

export type PlanRowActionsProps = {
    plan: Plan;
    onEdit: (plan: Plan) => void;
    onManageFeatures: (plan: Plan) => void;
    onDelete: (plan: Plan) => void;
    canUpdate?: boolean;
    canDelete?: boolean;
};

export function PlanRowActions({
    plan,
    onEdit,
    onManageFeatures,
    onDelete,
    canUpdate = true,
    canDelete = true,
}: PlanRowActionsProps) {
    const { t } = useTranslation(['planes', 'common']);

    const handleCopy = async () => {
        try {
            await navigator.clipboard.writeText(plan.codigo);
            toastManager.success({
                title: t('planes:toast.code_copied'),
                description: plan.codigo,
                duration: 2000,
            });
        } catch {
            toastManager.error({
                title: t('common:feedback.copy_error'),
            });
        }
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={t('planes:row.actions_for', {
                        name: plan.nombre,
                    })}
                    className="size-8 cursor-pointer"
                >
                    <MoreHorizontal className="size-4" strokeWidth={2.5} />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuItem
                    onSelect={handleCopy}
                    className="cursor-pointer gap-2"
                >
                    <Copy className="size-4" strokeWidth={2.25} />
                    {t('planes:row.copy_code')}
                </DropdownMenuItem>

                {(canUpdate || canDelete) && <DropdownMenuSeparator />}

                {canUpdate && (
                    <DropdownMenuItem
                        onSelect={() => onEdit(plan)}
                        className="cursor-pointer gap-2"
                    >
                        <Pencil className="size-4" strokeWidth={2.25} />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                )}

                {canUpdate && (
                    <DropdownMenuItem
                        onSelect={() => onManageFeatures(plan)}
                        className="cursor-pointer gap-2 text-primary focus:text-primary"
                    >
                        <KeyRound className="size-4" strokeWidth={2.25} />
                        {t('planes:row.manage_features')}
                    </DropdownMenuItem>
                )}

                {canDelete && (
                    <DropdownMenuItem
                        onSelect={() => onDelete(plan)}
                        className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                    >
                        <Trash2 className="size-4" strokeWidth={2.25} />
                        {t('common:actions.delete')}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
