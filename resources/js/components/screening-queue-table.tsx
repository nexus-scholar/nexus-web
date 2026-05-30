import { Link } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { DecisionBadge } from '@/components/decision-badge';
import { ScreeningStatusBadge } from '@/components/screening-status-badge';
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
import type { ScreeningQueueAssignment } from '@/types';

type ScreeningQueueTableProps = {
    assignments: ScreeningQueueAssignment[];
    selectedAssignmentId: string | null;
};

export function ScreeningQueueTable({
    assignments,
    selectedAssignmentId,
}: ScreeningQueueTableProps) {
    if (assignments.length === 0) {
        return (
            <div className="rounded-lg border bg-card p-6 text-sm text-muted-foreground shadow-xs">
                No assignments are available for this reviewer.
            </div>
        );
    }

    return (
        <div className="overflow-hidden rounded-lg border bg-card shadow-xs">
            <Table className="w-full table-fixed">
                <TableHeader>
                    <TableRow>
                        <TableHead>Record</TableHead>
                        <TableHead className="w-24">Status</TableHead>
                        <TableHead className="w-24">Decision</TableHead>
                        <TableHead className="w-20 text-right">Open</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {assignments.map((assignment) => (
                        <TableRow
                            key={assignment.id}
                            data-state={
                                selectedAssignmentId === assignment.id
                                    ? 'selected'
                                    : undefined
                            }
                        >
                            <TableCell>
                                <div className="min-w-0">
                                    <div className="line-clamp-2 text-sm font-medium">
                                        {assignment.title}
                                    </div>
                                    <div className="mt-1 flex flex-wrap gap-2 text-xs text-muted-foreground">
                                        <span>
                                            {assignment.year ?? 'Any year'}
                                        </span>
                                        {assignment.venue_name && (
                                            <span>{assignment.venue_name}</span>
                                        )}
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>
                                <ScreeningStatusBadge
                                    status={assignment.status}
                                    label={assignment.status_label}
                                />
                            </TableCell>
                            <TableCell>
                                <DecisionBadge
                                    decision={assignment.decision}
                                    label={assignment.decision_label}
                                />
                            </TableCell>
                            <TableCell className="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    asChild
                                    className={cn(
                                        selectedAssignmentId ===
                                            assignment.id &&
                                            'bg-brand-muted text-brand-muted-foreground',
                                    )}
                                >
                                    <Link
                                        href={assignment.href}
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
    );
}
