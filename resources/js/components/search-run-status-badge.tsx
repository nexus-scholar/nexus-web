import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { SearchRunStatus } from '@/types';

const searchRunStatusMeta = {
    queued: {
        label: 'Queued',
        className:
            'border-transparent bg-status-pending-bg text-status-pending',
    },
    running: {
        label: 'Running',
        className:
            'border-transparent bg-brand-muted text-brand-muted-foreground',
    },
    completed: {
        label: 'Completed',
        className:
            'border-transparent bg-status-include-bg text-status-include',
    },
    failed: {
        label: 'Failed',
        className:
            'border-transparent bg-status-exclude-bg text-status-exclude',
    },
} satisfies Record<SearchRunStatus, { label: string; className: string }>;

type SearchRunStatusBadgeProps = {
    status: SearchRunStatus;
    children?: ReactNode;
    className?: string;
};

export function SearchRunStatusBadge({
    children,
    className,
    status,
}: SearchRunStatusBadgeProps) {
    const meta = searchRunStatusMeta[status];

    return (
        <Badge variant="outline" className={cn(meta.className, className)}>
            {children ?? meta.label}
        </Badge>
    );
}
