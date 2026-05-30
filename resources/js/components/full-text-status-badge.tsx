import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { FullTextBatchStatus, FullTextItemStatus } from '@/types';

type FullTextStatusBadgeProps = {
    status: FullTextBatchStatus | FullTextItemStatus;
    label?: string | null;
    className?: string;
};

const statusClasses: Record<string, string> = {
    cancelled: 'border-transparent bg-status-pending-bg text-status-pending',
    completed: 'border-transparent bg-status-include-bg text-status-include',
    completed_with_failures:
        'border-transparent bg-status-conflict-bg text-status-conflict',
    failed: 'border-transparent bg-status-exclude-bg text-status-exclude',
    manual_needed:
        'border-transparent bg-status-conflict-bg text-status-conflict',
    not_started: 'border-transparent bg-status-pending-bg text-status-pending',
    queued: 'border-transparent bg-status-pending-bg text-status-pending',
    running: 'border-transparent bg-status-import-bg text-status-import',
    skipped: 'border-transparent bg-status-pending-bg text-status-pending',
    success: 'border-transparent bg-status-include-bg text-status-include',
};

export function FullTextStatusBadge({
    className,
    label,
    status,
}: FullTextStatusBadgeProps) {
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
