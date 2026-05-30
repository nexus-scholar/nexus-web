import {
    AlertCircle,
    CheckCircle2,
    FileLock2,
    GitMerge,
    ListChecks,
    Rows3,
} from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import type { DeduplicationPayload } from '@/types';

type DedupReadinessRailProps = {
    deduplication: DeduplicationPayload;
};

export function DedupReadinessRail({ deduplication }: DedupReadinessRailProps) {
    const sourceWorkLabel =
        deduplication.state === 'locked' ? 'Source works' : 'Draft works';

    const items = [
        {
            label: sourceWorkLabel,
            value: deduplication.summary.draft_unique_works,
            description: `${deduplication.summary.raw_query_links} query links`,
            icon: Rows3,
        },
        {
            label: 'Representatives',
            value: deduplication.summary.representative_works,
            description: 'Records that would enter the lock snapshot',
            icon: ListChecks,
        },
        {
            label: 'Duplicate clusters',
            value: deduplication.summary.duplicate_clusters,
            description: `${deduplication.summary.duplicates_removed} records collapsed`,
            icon: GitMerge,
        },
        {
            label: 'Lock readiness',
            value: deduplication.lock.available ? 'Ready' : 'Blocked',
            description:
                deduplication.lock.blocked_reason ??
                'Fresh deduplication evidence is available',
            icon: deduplication.lock.available ? CheckCircle2 : FileLock2,
        },
    ];

    return (
        <section className="space-y-3">
            {deduplication.state === 'stale' && (
                <Alert className="border-status-conflict/30 bg-status-conflict-bg text-status-conflict">
                    <AlertCircle className="size-4" />
                    <AlertTitle>Deduplication is stale</AlertTitle>
                    <AlertDescription>
                        The draft corpus changed after the latest run. Run
                        deduplication again before locking.
                    </AlertDescription>
                </Alert>
            )}

            <div className="grid overflow-hidden rounded-lg border bg-border/70 shadow-xs sm:grid-cols-2 xl:grid-cols-4">
                {items.map((item) => {
                    const Icon = item.icon;

                    return (
                        <div
                            key={item.label}
                            className="min-w-0 border-border/70 bg-card p-4 [&:not(:last-child)]:border-b xl:[&:not(:last-child)]:border-r xl:[&:not(:last-child)]:border-b-0 sm:[&:nth-child(odd)]:border-r"
                        >
                            <div className="flex items-start gap-3">
                                <div className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                    <Icon className="size-4" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="text-xs font-medium text-muted-foreground">
                                        {item.label}
                                    </div>
                                    <div className="mt-2 text-2xl leading-none font-semibold tabular-nums">
                                        {item.value}
                                    </div>
                                    <div className="mt-2 line-clamp-2 text-xs leading-5 text-muted-foreground">
                                        {item.description}
                                    </div>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
