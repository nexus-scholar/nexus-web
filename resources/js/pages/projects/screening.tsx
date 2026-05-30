import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    ClipboardList,
    FileSearch,
    UsersRound,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConflictResolutionSheet } from '@/components/conflict-resolution-sheet';
import { DecisionBadge } from '@/components/decision-badge';
import { PageHeader, PageShell } from '@/components/page-shell';
import { ProjectStatusBadge } from '@/components/project-status-badge';
import { ReviewerWorkloadList } from '@/components/reviewer-workload-list';
import { ScreeningHandoffCard } from '@/components/screening-handoff-card';
import { ScreeningProgressStrip } from '@/components/screening-progress-strip';
import { ScreeningSetupPanel } from '@/components/screening-setup-panel';
import { ScreeningStatusBadge } from '@/components/screening-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    ProjectStatus,
    ReviewType,
    ScreeningConflict,
    ScreeningOverviewPayload,
} from '@/types';

type ProjectPayload = {
    id: string;
    name: string;
    review_type: ReviewType;
    review_type_label: string;
    status: ProjectStatus;
    status_label: string;
    locked_at: string | null;
    workspace: {
        id: string;
        name: string;
    };
    urls: {
        overview: string;
        protocol: string;
        corpus: string;
        deduplication: string;
        screening: string;
        screening_queue: string;
        screening_batches: string;
        full_text: string;
        activity: string;
    };
};

type Props = {
    project: ProjectPayload;
    screening: ScreeningOverviewPayload;
    can: {
        view_screening: boolean;
        manage_screening: boolean;
        screen_assigned_work: boolean;
        resolve_screening_conflict: boolean;
        view_full_text: boolean;
    };
};

export default function ProjectScreening({ can, project, screening }: Props) {
    const [isClient, setIsClient] = useState(false);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setIsClient(true);
        }, 0);

        return () => {
            window.clearTimeout(timer);
        };
    }, []);

    const closeConflict = () => {
        router.get(project.urls.screening, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={`${project.name} screening`} />

            <PageShell>
                <PageHeader
                    eyebrow={`${project.workspace.name} / ${project.review_type_label}`}
                    title="Title and abstract screening"
                    description="Assign locked records, monitor reviewer progress, and resolve disagreements before full-text work."
                    actions={
                        <>
                            <ProjectStatusBadge status={project.status} />
                            {screening.batch && (
                                <ScreeningStatusBadge
                                    status={screening.batch.status}
                                    label={screening.batch.status_label}
                                />
                            )}
                            {can.screen_assigned_work &&
                                screening.nextReviewerAssignmentUrl && (
                                    <Button size="sm" asChild>
                                        <Link
                                            href={
                                                screening.nextReviewerAssignmentUrl
                                            }
                                        >
                                            <ClipboardList className="size-4" />
                                            Queue
                                        </Link>
                                    </Button>
                                )}
                            <Button variant="outline" size="sm" asChild>
                                <Link href={project.urls.corpus}>Corpus</Link>
                            </Button>
                            {can.view_full_text &&
                                screening.batch?.status === 'completed' && (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={project.urls.full_text}>
                                            Full text
                                        </Link>
                                    </Button>
                                )}
                            <Button variant="outline" size="sm" asChild>
                                <Link href={project.urls.overview}>
                                    Overview
                                </Link>
                            </Button>
                        </>
                    }
                />

                <ScreeningReadinessCard screening={screening} />

                {screening.batch ? (
                    <>
                        <ScreeningProgressStrip
                            counts={screening.batch.counts}
                            progressPercent={screening.batch.progress_percent}
                        />
                        <ScreeningHandoffCard batch={screening.batch} />

                        <div className="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_24rem]">
                            <section className="space-y-4">
                                <ConflictTable
                                    conflicts={screening.conflicts}
                                    projectUrl={project.urls.screening}
                                />
                                <AuditTrail
                                    events={screening.recentAuditEvents}
                                />
                            </section>
                            <ReviewerWorkloadList
                                workload={screening.workload}
                            />
                        </div>
                    </>
                ) : (
                    <ScreeningSetupPanel
                        actionUrl={project.urls.screening_batches}
                        availableReviewers={screening.setup.available_reviewers}
                        defaultRequiredReviewerCount={
                            screening.setup.default_required_reviewer_count
                        }
                        disabled={!can.manage_screening}
                        snapshot={screening.snapshot}
                    />
                )}

                {isClient && (
                    <ConflictResolutionSheet
                        key={screening.selectedConflict?.id ?? 'no-conflict'}
                        conflict={screening.selectedConflict}
                        canResolve={can.resolve_screening_conflict}
                        open={Boolean(screening.selectedConflict)}
                        onOpenChange={(open) => {
                            if (!open) {
                                closeConflict();
                            }
                        }}
                    />
                )}
            </PageShell>
        </>
    );
}

function ScreeningReadinessCard({
    screening,
}: {
    screening: ScreeningOverviewPayload;
}) {
    const snapshot = screening.snapshot;

    return (
        <Card>
            <CardHeader className="flex-row items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-brand-muted text-brand-muted-foreground">
                    <FileSearch className="size-4" />
                </div>
                <div className="min-w-0 flex-1 space-y-1">
                    <CardTitle>Locked corpus input</CardTitle>
                    <CardDescription>
                        {snapshot
                            ? `${snapshot.work_count} representative records locked for screening`
                            : 'No locked representative snapshot is available.'}
                    </CardDescription>
                </div>
                {snapshot && (
                    <Badge variant="outline">
                        {snapshot.representative_snapshot
                            ? 'Representative snapshot'
                            : 'Not representative'}
                    </Badge>
                )}
            </CardHeader>
            <CardContent className="grid gap-3 md:grid-cols-3">
                <Fact
                    label="Review question"
                    value={
                        screening.protocol.research_question ??
                        'No question recorded'
                    }
                />
                <Fact
                    label="Inclusion"
                    value={
                        screening.protocol.inclusion_criteria ??
                        'No inclusion criteria'
                    }
                />
                <Fact
                    label="Exclusion"
                    value={
                        screening.protocol.exclusion_criteria ??
                        'No exclusion criteria'
                    }
                />
            </CardContent>
        </Card>
    );
}

function ConflictTable({
    conflicts,
    projectUrl,
}: {
    conflicts: ScreeningConflict[];
    projectUrl: string;
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-status-conflict-bg text-status-conflict">
                    <AlertTriangle className="size-4" />
                </div>
                <div className="space-y-1">
                    <CardTitle>Conflict review</CardTitle>
                    <CardDescription>
                        Disagreements that require owner or adjudicator review.
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent>
                {conflicts.length === 0 ? (
                    <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                        No conflicts have been opened.
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <Table className="w-full table-fixed">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Record</TableHead>
                                    <TableHead className="w-24">
                                        Status
                                    </TableHead>
                                    <TableHead className="w-32">
                                        Decisions
                                    </TableHead>
                                    <TableHead className="w-24 text-right">
                                        Detail
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {conflicts.map((conflict) => (
                                    <TableRow key={conflict.id}>
                                        <TableCell>
                                            <div className="line-clamp-2 text-sm font-medium">
                                                {conflict.work.title}
                                            </div>
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                {[
                                                    conflict.work.year,
                                                    conflict.work.venue_name,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' / ') ||
                                                    'No venue metadata'}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <ScreeningStatusBadge
                                                status={conflict.status}
                                                label={conflict.status_label}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {conflict.source_decisions.map(
                                                    (decision) => (
                                                        <DecisionBadge
                                                            key={decision.id}
                                                            decision={
                                                                decision.decision
                                                            }
                                                            label={
                                                                decision.decision_label
                                                            }
                                                        />
                                                    ),
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={`${projectUrl}?conflict=${conflict.id}`}
                                                    preserveScroll
                                                >
                                                    Review
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function AuditTrail({
    events,
}: {
    events: ScreeningOverviewPayload['recentAuditEvents'];
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-status-audit-bg text-status-audit">
                    <Activity className="size-4" />
                </div>
                <div className="space-y-1">
                    <CardTitle>Screening audit</CardTitle>
                    <CardDescription>
                        Recent screening events for this project.
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent>
                {events.length === 0 ? (
                    <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                        No screening audit events recorded yet.
                    </div>
                ) : (
                    <div className="divide-y rounded-md border">
                        {events.map((event) => (
                            <div
                                key={event.id}
                                className="flex items-start gap-3 p-3 text-sm"
                            >
                                <UsersRound className="mt-0.5 size-4 text-muted-foreground" />
                                <div className="min-w-0">
                                    <div className="font-medium">
                                        {event.label}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {event.reason ?? 'No audit reason'} /{' '}
                                        {formatDate(event.occurred_at)}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function Fact({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border bg-muted/20 p-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 line-clamp-3 text-sm leading-5 font-medium">
                {value}
            </div>
        </div>
    );
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'Not recorded';
    }

    const normalized = value.replace('T', ' ').replace(/\.\d+Z?$/, '');
    const [datePart, timePart] = normalized.split(' ');
    const [year, month, day] = datePart.split('-');
    const [hour, minute] = (timePart ?? '').split(':');
    const monthNames = [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'May',
        'Jun',
        'Jul',
        'Aug',
        'Sep',
        'Oct',
        'Nov',
        'Dec',
    ];
    const monthLabel = monthNames[Number(month) - 1] ?? month;
    const dayLabel = Number(day).toString();

    return hour && minute
        ? `${monthLabel} ${dayLabel}, ${year} ${hour}:${minute}`
        : `${monthLabel} ${dayLabel}, ${year}`;
}

ProjectScreening.layout = {
    breadcrumbs: [
        {
            title: 'Screening',
            href: '#',
        },
    ],
};
