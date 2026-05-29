import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type WorkspaceStatus =
    | 'active'
    | 'personal'
    | 'shared'
    | 'operator'
    | 'disabled';

const statusLabels: Record<WorkspaceStatus, string> = {
    active: 'Active',
    personal: 'Personal',
    shared: 'Shared',
    operator: 'Operator',
    disabled: 'Disabled',
};

const statusClasses: Record<WorkspaceStatus, string> = {
    active: 'border-transparent bg-status-include-bg text-status-include',
    personal: 'border-transparent bg-status-pending-bg text-status-pending',
    shared: 'border-transparent bg-brand-muted text-brand-muted-foreground',
    operator: 'border-transparent bg-status-audit-bg text-status-audit',
    disabled: 'border-transparent bg-status-exclude-bg text-status-exclude',
};

type WorkspaceStatusBadgeProps = {
    status: WorkspaceStatus;
    children?: ReactNode;
    className?: string;
};

export function WorkspaceStatusBadge({
    status,
    children,
    className,
}: WorkspaceStatusBadgeProps) {
    return (
        <Badge
            variant="outline"
            className={cn('capitalize', statusClasses[status], className)}
        >
            {children ?? statusLabels[status]}
        </Badge>
    );
}
