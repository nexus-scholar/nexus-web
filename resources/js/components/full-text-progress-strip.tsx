import {
    AlertTriangle,
    CheckCircle2,
    Clock3,
    FileSearch,
    HelpCircle,
} from 'lucide-react';
import { MetricCard } from '@/components/metric-card';
import type { FullTextBatch } from '@/types';

type FullTextProgressStripProps = {
    batch: FullTextBatch | null;
    candidateCount: number;
};

export function FullTextProgressStrip({
    batch,
    candidateCount,
}: FullTextProgressStripProps) {
    const terminal =
        (batch?.success_count ?? 0) +
        (batch?.failed_count ?? 0) +
        (batch?.skipped_count ?? 0) +
        (batch?.manual_needed_count ?? 0);
    const queued = Math.max(
        (batch?.candidate_count ?? candidateCount) - terminal,
        0,
    );
    const progressPercent = batch?.progress_percent ?? 0;

    return (
        <div className="space-y-3">
            <div className="grid gap-3 md:grid-cols-5">
                <MetricCard
                    label="Progress"
                    value={`${progressPercent}%`}
                    description={`${terminal} of ${batch?.candidate_count ?? candidateCount} candidates terminal`}
                    icon={FileSearch}
                />
                <MetricCard
                    label="Retrieved"
                    value={batch?.success_count ?? 0}
                    description="Artifacts stored successfully."
                    icon={CheckCircle2}
                />
                <MetricCard
                    label="Failed"
                    value={batch?.failed_count ?? 0}
                    description="Source or download errors."
                    icon={AlertTriangle}
                />
                <MetricCard
                    label="Skipped"
                    value={batch?.skipped_count ?? 0}
                    description="No source candidate found."
                    icon={Clock3}
                />
                <MetricCard
                    label="Manual needed"
                    value={batch?.manual_needed_count ?? 0}
                    description="Records needing human follow-up."
                    icon={HelpCircle}
                />
            </div>

            <div
                aria-label="Full-text retrieval progress"
                className="h-2 overflow-hidden rounded-full bg-muted"
            >
                <div
                    className="h-full bg-brand"
                    style={{ width: `${Math.min(100, progressPercent)}%` }}
                />
            </div>

            {batch && queued > 0 && (
                <p className="text-xs text-muted-foreground">
                    {queued} records remain queued or running in the background
                    batch.
                </p>
            )}
        </div>
    );
}
