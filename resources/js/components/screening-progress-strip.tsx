import {
    AlertTriangle,
    CheckCircle2,
    Clock3,
    GitPullRequest,
} from 'lucide-react';
import { MetricCard } from '@/components/metric-card';
import type { ScreeningCounts } from '@/types';

type ScreeningProgressStripProps = {
    counts: ScreeningCounts;
    progressPercent: number;
};

export function ScreeningProgressStrip({
    counts,
    progressPercent,
}: ScreeningProgressStripProps) {
    return (
        <div className="space-y-3">
            <div className="grid gap-3 md:grid-cols-4">
                <MetricCard
                    label="Progress"
                    value={`${progressPercent}%`}
                    description={`${counts.assignments.resolved} of ${counts.assignments.total} assignments resolved`}
                    icon={CheckCircle2}
                />
                <MetricCard
                    label="Pending"
                    value={counts.assignments.pending}
                    description="Reviewer decisions still needed."
                    icon={Clock3}
                />
                <MetricCard
                    label="Ready"
                    value={counts.outcomes.ready_for_full_text}
                    description="Final include or maybe outcomes."
                    icon={GitPullRequest}
                />
                <MetricCard
                    label="Open conflicts"
                    value={counts.conflicts.open}
                    description="Disagreements awaiting adjudication."
                    icon={AlertTriangle}
                />
            </div>

            <div
                aria-label="Screening progress"
                className="h-2 overflow-hidden rounded-full bg-muted"
            >
                <div
                    className="h-full bg-brand"
                    style={{ width: `${Math.min(100, progressPercent)}%` }}
                />
            </div>
        </div>
    );
}
