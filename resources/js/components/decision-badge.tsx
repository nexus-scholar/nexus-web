import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { ScreeningDecision } from '@/types';

type DecisionBadgeProps = {
    decision: ScreeningDecision | null;
    label?: string | null;
    className?: string;
};

const decisionClasses: Record<ScreeningDecision, string> = {
    exclude: 'border-transparent bg-status-exclude-bg text-status-exclude',
    include: 'border-transparent bg-status-include-bg text-status-include',
    needs_review:
        'border-transparent bg-status-conflict-bg text-status-conflict',
};

export function DecisionBadge({
    className,
    decision,
    label,
}: DecisionBadgeProps) {
    if (!decision) {
        return (
            <Badge
                variant="outline"
                className={cn('text-muted-foreground', className)}
            >
                No decision
            </Badge>
        );
    }

    return (
        <Badge className={cn(decisionClasses[decision], className)}>
            {label ?? decisionLabel(decision)}
        </Badge>
    );
}

function decisionLabel(decision: ScreeningDecision): string {
    return {
        exclude: 'Exclude',
        include: 'Include',
        needs_review: 'Maybe',
    }[decision];
}
