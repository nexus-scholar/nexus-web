import { ScreeningStatusBadge } from '@/components/screening-status-badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ScreeningWorkload } from '@/types';

type ReviewerWorkloadListProps = {
    workload: ScreeningWorkload[];
};

export function ReviewerWorkloadList({ workload }: ReviewerWorkloadListProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Reviewer workload</CardTitle>
                <CardDescription>
                    Assignment balance and current decision state.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {workload.length === 0 ? (
                    <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                        No reviewer assignments have been created.
                    </div>
                ) : (
                    workload.map((reviewer) => (
                        <article
                            key={reviewer.user_id}
                            className="rounded-md border bg-card p-3 shadow-xs"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <div className="truncate text-sm font-medium">
                                        {reviewer.name}
                                    </div>
                                    <div className="truncate text-xs text-muted-foreground">
                                        {reviewer.email}
                                    </div>
                                </div>
                                <div className="text-sm font-medium">
                                    {reviewer.counts.resolved}/
                                    {reviewer.counts.total}
                                </div>
                            </div>
                            <div className="mt-3 flex flex-wrap gap-1.5">
                                <ScreeningStatusBadge
                                    status="pending"
                                    label={`${reviewer.counts.pending} pending`}
                                />
                                <ScreeningStatusBadge
                                    status="decided"
                                    label={`${reviewer.counts.decided} submitted`}
                                />
                                <ScreeningStatusBadge
                                    status="conflict"
                                    label={`${reviewer.counts.conflict} conflict`}
                                />
                                <ScreeningStatusBadge
                                    status="resolved"
                                    label={`${reviewer.counts.resolved} resolved`}
                                />
                            </div>
                        </article>
                    ))
                )}
            </CardContent>
        </Card>
    );
}
