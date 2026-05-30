import { Link } from '@inertiajs/react';
import { Eye, GitMerge } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { DeduplicationCluster } from '@/types';

type DedupClusterTableProps = {
    clusters: DeduplicationCluster[];
    selectedClusterId: string | null;
    clusterHref: (clusterId: string) => string;
};

export function DedupClusterTable({
    clusterHref,
    clusters,
    selectedClusterId,
}: DedupClusterTableProps) {
    if (clusters.length === 0) {
        return (
            <div className="rounded-lg border bg-card p-8 text-center shadow-xs">
                <div className="mx-auto flex size-10 items-center justify-center rounded-md bg-status-include-bg text-status-include">
                    <GitMerge className="size-5" />
                </div>
                <div className="mt-3 text-sm font-medium">
                    No duplicate clusters
                </div>
                <p className="mt-1 text-sm text-muted-foreground">
                    The latest run did not find duplicate candidates.
                </p>
            </div>
        );
    }

    return (
        <div className="overflow-hidden rounded-lg border bg-card shadow-xs">
            <div className="border-b p-3 text-sm">
                <span className="font-medium">{clusters.length}</span>{' '}
                <span className="text-muted-foreground">
                    duplicate {clusters.length === 1 ? 'cluster' : 'clusters'}
                </span>
            </div>

            <div className="overflow-x-auto">
                <Table className="min-w-[62rem]">
                    <TableHeader>
                        <TableRow>
                            <TableHead className="min-w-[24rem]">
                                Representative
                            </TableHead>
                            <TableHead className="w-32">Members</TableHead>
                            <TableHead className="w-36">Confidence</TableHead>
                            <TableHead className="min-w-56">Evidence</TableHead>
                            <TableHead className="w-28">State</TableHead>
                            <TableHead className="sticky right-0 z-10 w-28 bg-card text-right shadow-[-10px_0_14px_-14px_rgb(15_23_42/0.45)]">
                                Detail
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {clusters.map((cluster) => (
                            <TableRow
                                key={cluster.id}
                                data-state={
                                    cluster.id === selectedClusterId
                                        ? 'selected'
                                        : undefined
                                }
                            >
                                <TableCell>
                                    <div className="line-clamp-2 text-sm font-medium">
                                        {cluster.representative_title ??
                                            'Representative not available'}
                                    </div>
                                    <div className="mt-1 font-mono text-xs text-muted-foreground">
                                        {cluster.representative_work_id}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    {cluster.cluster_size.toLocaleString()}
                                </TableCell>
                                <TableCell>
                                    {formatConfidence(cluster.confidence)}
                                </TableCell>
                                <TableCell>
                                    <div className="flex flex-wrap gap-1">
                                        {cluster.reasons.length === 0 ? (
                                            <span className="text-xs text-muted-foreground">
                                                Not recorded
                                            </span>
                                        ) : (
                                            cluster.reasons.map((reason) => (
                                                <Badge
                                                    key={reason}
                                                    variant="outline"
                                                >
                                                    {reason}
                                                </Badge>
                                            ))
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        variant="outline"
                                        className={
                                            cluster.is_locked
                                                ? 'border-transparent bg-status-audit-bg text-status-audit'
                                                : 'border-transparent bg-status-pending-bg text-status-pending'
                                        }
                                    >
                                        {cluster.is_locked ? 'Locked' : 'Draft'}
                                    </Badge>
                                </TableCell>
                                <TableCell className="sticky right-0 z-10 bg-card text-right shadow-[-10px_0_14px_-14px_rgb(15_23_42/0.35)]">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        asChild
                                        className={cn(
                                            selectedClusterId === cluster.id &&
                                                'bg-brand-muted text-brand-muted-foreground',
                                        )}
                                    >
                                        <Link
                                            href={clusterHref(cluster.id)}
                                            preserveScroll
                                            preserveState
                                        >
                                            <Eye className="size-4" />
                                            View
                                        </Link>
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}

function formatConfidence(value: number | null): string {
    if (value === null) {
        return 'Not scored';
    }

    return `${Math.round(value * 100)}%`;
}
