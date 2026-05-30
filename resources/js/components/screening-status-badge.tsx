import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type {
    ScreeningAssignmentStatus,
    ScreeningBatchStatus,
    ScreeningConflictStatus,
} from '@/types';

type ScreeningStatusBadgeProps = {
    status:
        | ScreeningAssignmentStatus
        | ScreeningBatchStatus
        | ScreeningConflictStatus;
    label?: string | null;
    className?: string;
};

const statusClasses: Record<string, string> = {
    active: 'border-transparent bg-status-import-bg text-status-import',
    cancelled: 'border-transparent bg-status-pending-bg text-status-pending',
    completed: 'border-transparent bg-status-include-bg text-status-include',
    conflict: 'border-transparent bg-status-conflict-bg text-status-conflict',
    conflicts: 'border-transparent bg-status-conflict-bg text-status-conflict',
    decided: 'border-transparent bg-status-import-bg text-status-import',
    draft: 'border-transparent bg-status-pending-bg text-status-pending',
    in_progress: 'border-transparent bg-status-import-bg text-status-import',
    open: 'border-transparent bg-status-conflict-bg text-status-conflict',
    pending: 'border-transparent bg-status-pending-bg text-status-pending',
    resolved: 'border-transparent bg-status-include-bg text-status-include',
};

export function ScreeningStatusBadge({
    className,
    label,
    status,
}: ScreeningStatusBadgeProps) {
    return (
        <Badge
            className={cn(
                statusClasses[status] ??
                    'border-transparent bg-status-pending-bg text-status-pending',
                className,
            )}
        >
            {label ?? statusLabel(status)}
        </Badge>
    );
}

function statusLabel(status: string): string {
    return status
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}
