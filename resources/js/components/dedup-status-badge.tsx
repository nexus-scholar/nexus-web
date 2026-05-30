import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { DeduplicationState } from '@/types';

const stateMeta = {
    not_run: {
        label: 'Not run',
        className:
            'border-transparent bg-status-pending-bg text-status-pending',
    },
    stale: {
        label: 'Stale',
        className:
            'border-transparent bg-status-conflict-bg text-status-conflict',
    },
    duplicates_found: {
        label: 'Duplicates found',
        className:
            'border-transparent bg-brand-muted text-brand-muted-foreground',
    },
    clear: {
        label: 'Clear',
        className:
            'border-transparent bg-status-include-bg text-status-include',
    },
    locked: {
        label: 'Locked',
        className: 'border-transparent bg-status-audit-bg text-status-audit',
    },
} satisfies Record<DeduplicationState, { label: string; className: string }>;

type DedupStatusBadgeProps = {
    state: DeduplicationState;
    className?: string;
};

export function DedupStatusBadge({ className, state }: DedupStatusBadgeProps) {
    const meta = stateMeta[state];

    return (
        <Badge
            variant="outline"
            className={cn('whitespace-nowrap', meta.className, className)}
        >
            {meta.label}
        </Badge>
    );
}
