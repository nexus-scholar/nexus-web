import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { ProjectRole, ProjectStatus, ProtocolStatus } from '@/types';

type BadgeMeta = {
    label: string;
    className: string;
};

const projectStatusMeta = {
    draft: {
        label: 'Draft',
        className:
            'border-transparent bg-status-pending-bg text-status-pending',
    },
    ready_for_search: {
        label: 'Ready for search',
        className:
            'border-transparent bg-status-include-bg text-status-include',
    },
    searching: {
        label: 'Searching',
        className: 'border-transparent bg-status-import-bg text-status-import',
    },
    draft_corpus: {
        label: 'Draft corpus',
        className:
            'border-transparent bg-brand-muted text-brand-muted-foreground',
    },
    locked_corpus: {
        label: 'Locked corpus',
        className: 'border-transparent bg-status-audit-bg text-status-audit',
    },
    screening: {
        label: 'Screening',
        className: 'border-transparent bg-status-import-bg text-status-import',
    },
    adjudication: {
        label: 'Adjudication',
        className:
            'border-transparent bg-status-conflict-bg text-status-conflict',
    },
    exporting: {
        label: 'Exporting',
        className: 'border-transparent bg-status-import-bg text-status-import',
    },
    archived: {
        label: 'Archived',
        className: 'border-transparent bg-muted text-muted-foreground',
    },
} satisfies Record<ProjectStatus, BadgeMeta>;

const protocolStatusMeta = {
    draft: {
        label: 'Draft',
        className:
            'border-transparent bg-status-pending-bg text-status-pending',
    },
    complete: {
        label: 'Complete',
        className:
            'border-transparent bg-status-include-bg text-status-include',
    },
    locked: {
        label: 'Locked',
        className: 'border-transparent bg-status-audit-bg text-status-audit',
    },
    amended: {
        label: 'Amended',
        className:
            'border-transparent bg-status-conflict-bg text-status-conflict',
    },
} satisfies Record<ProtocolStatus, BadgeMeta>;

const projectRoleMeta = {
    owner: {
        label: 'Owner',
        className: 'border-transparent bg-status-audit-bg text-status-audit',
    },
    reviewer: {
        label: 'Reviewer',
        className: 'border-transparent bg-status-import-bg text-status-import',
    },
    adjudicator: {
        label: 'Adjudicator',
        className:
            'border-transparent bg-status-conflict-bg text-status-conflict',
    },
    viewer: {
        label: 'Viewer',
        className:
            'border-transparent bg-status-pending-bg text-status-pending',
    },
} satisfies Record<ProjectRole, BadgeMeta>;

type StatusBadgeProps<TStatus extends string> = {
    status: TStatus;
    children?: ReactNode;
    className?: string;
};

function StatusBadge<TStatus extends string>({
    children,
    className,
    meta,
    status,
}: StatusBadgeProps<TStatus> & { meta: Record<TStatus, BadgeMeta> }) {
    const statusMeta = meta[status];

    return (
        <Badge
            variant="outline"
            className={cn('whitespace-nowrap', statusMeta.className, className)}
        >
            {children ?? statusMeta.label}
        </Badge>
    );
}

export function ProjectStatusBadge({
    status,
    ...props
}: StatusBadgeProps<ProjectStatus>) {
    return <StatusBadge status={status} meta={projectStatusMeta} {...props} />;
}

export function ProtocolStatusBadge({
    status,
    ...props
}: StatusBadgeProps<ProtocolStatus>) {
    return <StatusBadge status={status} meta={protocolStatusMeta} {...props} />;
}

export function ProjectRoleBadge({
    status,
    ...props
}: StatusBadgeProps<ProjectRole>) {
    return <StatusBadge status={status} meta={projectRoleMeta} {...props} />;
}
