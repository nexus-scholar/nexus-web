import type { LucideIcon } from 'lucide-react';
import {
    AlertCircle,
    CheckCircle2,
    Circle,
    CircleDotDashed,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type WorkflowStepStatus = 'complete' | 'current' | 'blocked' | 'pending';

type WorkflowStepMeta = {
    label: string;
    icon: LucideIcon;
    className: string;
    markerClassName: string;
};

const workflowStepMeta = {
    complete: {
        label: 'Complete',
        icon: CheckCircle2,
        className:
            'border-transparent bg-status-include-bg text-status-include',
        markerClassName: 'bg-status-include-bg text-status-include',
    },
    current: {
        label: 'Current',
        icon: CircleDotDashed,
        className:
            'border-transparent bg-brand-muted text-brand-muted-foreground',
        markerClassName: 'bg-brand-muted text-brand-muted-foreground',
    },
    blocked: {
        label: 'Blocked',
        icon: AlertCircle,
        className:
            'border-transparent bg-status-exclude-bg text-status-exclude',
        markerClassName: 'bg-status-exclude-bg text-status-exclude',
    },
    pending: {
        label: 'Pending',
        icon: Circle,
        className:
            'border-transparent bg-status-pending-bg text-status-pending',
        markerClassName: 'bg-status-pending-bg text-status-pending',
    },
} satisfies Record<WorkflowStepStatus, WorkflowStepMeta>;

type WorkflowStepCardProps = {
    title: string;
    description: string;
    status: WorkflowStepStatus;
    step?: number | string;
    meta?: ReactNode;
    action?: ReactNode;
    className?: string;
};

export function WorkflowStepCard({
    action,
    className,
    description,
    meta,
    status,
    step,
    title,
}: WorkflowStepCardProps) {
    const stepMeta = workflowStepMeta[status];
    const Icon = stepMeta.icon;

    return (
        <article
            aria-current={status === 'current' ? 'step' : undefined}
            className={cn(
                'rounded-lg border bg-card p-4 text-card-foreground shadow-xs',
                status === 'current' && 'border-brand/50 ring-1 ring-brand/20',
                className,
            )}
        >
            <div className="flex items-start gap-3">
                <div
                    className={cn(
                        'flex size-9 shrink-0 items-center justify-center rounded-md',
                        stepMeta.markerClassName,
                    )}
                >
                    {step ? (
                        <span className="text-sm font-semibold">{step}</span>
                    ) : (
                        <Icon className="size-4" aria-hidden="true" />
                    )}
                </div>

                <div className="min-w-0 flex-1 space-y-3">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div className="min-w-0 space-y-1">
                            <h3 className="text-sm leading-5 font-medium">
                                {title}
                            </h3>
                            <p className="text-sm leading-5 text-muted-foreground">
                                {description}
                            </p>
                        </div>
                        <Badge
                            variant="outline"
                            className={cn('shrink-0', stepMeta.className)}
                        >
                            {stepMeta.label}
                        </Badge>
                    </div>

                    {(meta || action) && (
                        <div className="flex flex-col gap-2 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                            {meta && <div>{meta}</div>}
                            {action && (
                                <div className="shrink-0 text-sm">{action}</div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </article>
    );
}
