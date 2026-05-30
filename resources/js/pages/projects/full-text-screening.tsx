import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    ClipboardList,
    FileCheck2,
    FileText,
    UsersRound,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConflictResolutionSheet } from '@/components/conflict-resolution-sheet';
import { DecisionBadge } from '@/components/decision-badge';
import { FullTextScreeningSetupPanel } from '@/components/full-text-screening-setup-panel';
import { PageHeader, PageShell } from '@/components/page-shell';
import { ProjectStatusBadge } from '@/components/project-status-badge';
import { ReviewerWorkloadList } from '@/components/reviewer-workload-list';
import { ScreeningProgressStrip } from '@/components/screening-progress-strip';
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
    FullTextScreeningOverviewPayload,
    ProjectStatus,
    ReviewType,
    ScreeningConflict,
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
        screening: string;
        screening_queue: string;
        full_text: string;
        full_text_screening: string;
        full_text_screening_queue: string;
        full_text_screening_batches: string;
        activity: string;
    };
};

type Props = {
    project: ProjectPayload;
    screening: FullTextScreeningOverviewPayload;
    can: {
        view_full_text_screening: boolean;
        manage_full_text_screening: boolean;
        screen_assigned_full_text: boolean;
        resolve_full_text_screening_conflict: boolean;
        view_full_text: boolean;
    };
};

export default function ProjectFullTextScreening({
    can,
    project,
    screening,
}: Props) {
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
        router.get(
            project.urls.full_text_screening,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={`${project.name} full-text screening`} />

            <PageShell>
                <PageHeader
                    eyebrow={`${project.workspace.name} / ${project.review_type_label}`}
                    title="Full-text screening"
                    description="Assign retrieved artifacts, capture final eligibility decisions, and resolve full-text conflicts."
                    actions={
                        <>
                            <ProjectStatusBadge status={project.status} />
                            {screening.batch && (
                                <ScreeningStatusBadge
                                    status={screening.batch.status}
                                    label={screening.batch.status_label}
                                />
                            )}
                            {can.screen_assigned_full_text &&
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
                            {can.view_full_text && (
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

                <ReadinessCard screening={screening} />

                {screening.batch ? (
                    <>
                        <ScreeningProgressStrip
                            counts={screening.batch.counts}
                            progressPercent={screening.batch.progress_percent}
                        />

                        <div className="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_24rem]">
                            <section className="space-y-4">
                                <CompletionSummary screening={screening} />
                                <ConflictTable
                                    conflicts={screening.conflicts}
                                    projectUrl={
                                        project.urls.full_text_screening
                                    }
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
                    <FullTextScreeningSetupPanel
                        actionUrl={project.urls.full_text_screening_batches}
                        availableReviewers={screening.setup.available_reviewers}
                        defaultRequiredReviewerCount={
                            screening.setup.default_required_reviewer_count
                        }
                        disabled={!can.manage_full_text_screening}
                        readiness={screening.readiness}
                    />
                )}

                {isClient && (
                    <ConflictResolutionSheet
                        key={screening.selectedConflict?.id ?? 'no-conflict'}
                        conflict={screening.selectedConflict}
                        canResolve={can.resolve_full_text_screening_conflict}
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

function ReadinessCard({
    screening,
}: {
    screening: FullTextScreeningOverviewPayload;
}) {
    const readiness = screening.readiness;

    return (
        <Card>
            <CardHeader className="flex-row items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-brand-muted text-brand-muted-foreground">
                    <FileCheck2 className="size-4" />
                </div>
                <div className="min-w-0 flex-1 space-y-1">
                    <CardTitle>Artifact-linked input</CardTitle>
                    <CardDescription>
                        {readiness.ready
                            ? `${readiness.counts.screenable} records have successful full-text artifacts ready for review.`
                            : 'Full-text screening is waiting on the workflow gates below.'}
                    </CardDescription>
                </div>
                <Badge
                    className={
                        readiness.ready
                            ? 'border-transparent bg-status-include-bg text-status-include'
                            : 'border-transparent bg-status-pending-bg text-status-pending'
                    }
                >
                    {readiness.ready ? 'Ready' : 'Blocked'}
                </Badge>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid gap-3 md:grid-cols-4">
                    <Fact
                        label="Screenable"
                        value={readiness.counts.screenable}
                        description="Successful artifacts"
                    />
                    <Fact
                        label="Follow-up"
                        value={readiness.follow_up.total}
                        description="Missing or failed artifacts"
                    />
                    <Fact
                        label="Full-text batch"
                        value={
                            readiness.full_text_batch?.status_label ??
                            'No batch'
                        }
                        description="Retrieval state"
                    />
                    <Fact
                        label="Policy"
                        value={screening.protocol.full_text_policy_label}
                        description="Protocol rule"
                    />
                </div>

                {readiness.blockers.length > 0 && (
                    <div className="rounded-md border border-status-conflict/25 bg-status-conflict-bg p-3">
                        <div className="text-sm font-medium text-status-conflict">
                            Full-text screening blockers
                        </div>
                        <ul className="mt-2 space-y-1 text-sm text-status-conflict">
                            {readiness.blockers.map((blocker) => (
                                <li key={blocker}>{blocker}</li>
                            ))}
                        </ul>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function CompletionSummary({
    screening,
}: {
    screening: FullTextScreeningOverviewPayload;
}) {
    const outcomes = screening.batch?.counts.outcomes;

    return (
        <Card>
            <CardHeader className="flex-row items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-status-audit-bg text-status-audit">
                    <FileText className="size-4" />
                </div>
                <div className="space-y-1">
                    <CardTitle>Eligibility summary</CardTitle>
                    <CardDescription>
                        Final full-text outcomes are derived from resolved
                        reviewer assignments and adjudication.
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent className="grid gap-3 md:grid-cols-4">
                <Fact
                    label="Included"
                    value={outcomes?.include ?? 0}
                    description="Final include"
                />
                <Fact
                    label="Maybe"
                    value={outcomes?.needs_review ?? 0}
                    description="Needs team review"
                />
                <Fact
                    label="Excluded"
                    value={outcomes?.exclude ?? 0}
                    description="Final exclude"
                />
                <Fact
                    label="Unresolved"
                    value={outcomes?.unresolved_works ?? 0}
                    description="Open work"
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
                        Full-text disagreements that require owner or
                        adjudicator review.
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent>
                {conflicts.length === 0 ? (
                    <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                        No full-text conflicts have been opened.
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
    events: FullTextScreeningOverviewPayload['recentAuditEvents'];
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-status-audit-bg text-status-audit">
                    <Activity className="size-4" />
                </div>
                <div className="space-y-1">
                    <CardTitle>Full-text screening audit</CardTitle>
                    <CardDescription>
                        Recent decision and conflict events for this project.
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent>
                {events.length === 0 ? (
                    <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                        No full-text screening audit events recorded yet.
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

function Fact({
    description,
    label,
    value,
}: {
    label: string;
    value: number | string;
    description: string;
}) {
    return (
        <div className="rounded-md border bg-muted/20 p-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-2 text-2xl leading-none font-semibold tabular-nums">
                {value}
            </div>
            <div className="mt-1 text-xs text-muted-foreground">
                {description}
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

ProjectFullTextScreening.layout = {
    breadcrumbs: [
        {
            title: 'Full-text screening',
            href: '#',
        },
    ],
};
