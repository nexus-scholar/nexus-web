import { CheckCircle2, FileCheck2, Hourglass, XCircle } from 'lucide-react';
import { ScreeningStatusBadge } from '@/components/screening-status-badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { ScreeningBatch } from '@/types';

type ScreeningHandoffCardProps = {
    batch: ScreeningBatch;
};

export function ScreeningHandoffCard({ batch }: ScreeningHandoffCardProps) {
    const outcomes = batch.counts.outcomes;
    const isCompleted = batch.status === 'completed';
    const blockers = outcomes.unresolved_works;

    return (
        <Card
            className={cn(
                'border-dashed',
                isCompleted && 'border-brand/35 bg-brand-muted/20',
            )}
        >
            <CardHeader className="flex-row items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-brand-muted text-brand-muted-foreground">
                    <FileCheck2 className="size-4" />
                </div>
                <div className="min-w-0 flex-1 space-y-1">
                    <CardTitle>Full-text readiness</CardTitle>
                    <CardDescription>
                        {isCompleted
                            ? `${outcomes.ready_for_full_text} records are ready for the next full-text workflow.`
                            : `${blockers} records still need reviewer agreement or adjudication.`}
                    </CardDescription>
                </div>
                <ScreeningStatusBadge
                    status={batch.status}
                    label={isCompleted ? 'Handoff ready' : batch.status_label}
                />
            </CardHeader>
            <CardContent className="grid gap-3 md:grid-cols-4">
                <HandoffFact
                    icon={FileCheck2}
                    label="Ready for full text"
                    value={outcomes.ready_for_full_text}
                    description="Include plus maybe"
                />
                <HandoffFact
                    icon={CheckCircle2}
                    label="Included"
                    value={outcomes.include}
                    description="Final include"
                />
                <HandoffFact
                    icon={Hourglass}
                    label="Maybe"
                    value={outcomes.needs_review}
                    description="Needs full-text check"
                />
                <HandoffFact
                    icon={XCircle}
                    label="Excluded"
                    value={outcomes.excluded}
                    description="Final exclude"
                />
            </CardContent>
        </Card>
    );
}

function HandoffFact({
    description,
    icon: Icon,
    label,
    value,
}: {
    icon: typeof FileCheck2;
    label: string;
    value: number;
    description: string;
}) {
    return (
        <div className="rounded-md border bg-background/70 p-3">
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <Icon className="size-4" />
                {label}
            </div>
            <div className="mt-2 text-2xl leading-none font-semibold">
                {value}
            </div>
            <div className="mt-1 text-xs text-muted-foreground">
                {description}
            </div>
        </div>
    );
}
