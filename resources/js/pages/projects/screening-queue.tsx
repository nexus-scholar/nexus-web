import { Head, Link } from '@inertiajs/react';
import { ClipboardList, FileText } from 'lucide-react';
import { PageHeader, PageShell } from '@/components/page-shell';
import { ProjectStatusBadge } from '@/components/project-status-badge';
import { ScreeningDecisionPanel } from '@/components/screening-decision-panel';
import { ScreeningQueueTable } from '@/components/screening-queue-table';
import { ScreeningStatusBadge } from '@/components/screening-status-badge';
import { ScreeningWorkDetail } from '@/components/screening-work-detail';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    ProjectStatus,
    ScreeningQueuePayload,
    ScreeningSelectedAssignment,
} from '@/types';

type ProjectPayload = {
    id: string;
    name: string;
    review_type_label: string;
    status: ProjectStatus;
    status_label: string;
    workspace: {
        id: string;
        name: string;
    };
    urls: {
        overview: string;
        screening: string;
        screening_queue: string;
    };
};

type Props = {
    project: ProjectPayload;
    queue: ScreeningQueuePayload;
    can: {
        screen_assigned_work: boolean;
        view_screening: boolean;
    };
};

export default function ProjectScreeningQueue({ can, project, queue }: Props) {
    const selected = queue.selectedAssignment;

    return (
        <>
            <Head title={`${project.name} screening queue`} />

            <PageShell>
                <PageHeader
                    eyebrow={`${project.workspace.name} / ${project.review_type_label}`}
                    title="Reviewer queue"
                    description="Review assigned records against the protocol and submit title and abstract decisions."
                    actions={
                        <>
                            <ProjectStatusBadge status={project.status} />
                            {queue.batch && (
                                <ScreeningStatusBadge
                                    status={queue.batch.status}
                                    label={queue.batch.status_label}
                                />
                            )}
                            {can.view_screening && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={project.urls.screening}>
                                        Screening overview
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

                {!queue.batch ? (
                    <Card>
                        <CardHeader className="flex-row items-start gap-3">
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-status-pending-bg text-status-pending">
                                <ClipboardList className="size-4" />
                            </div>
                            <div className="space-y-1">
                                <CardTitle>No active screening batch</CardTitle>
                                <CardDescription>
                                    The owner must start title and abstract
                                    screening before reviewer queues are
                                    available.
                                </CardDescription>
                            </div>
                        </CardHeader>
                    </Card>
                ) : (
                    <div className="grid gap-4 xl:grid-cols-[minmax(24rem,32rem)_minmax(0,1fr)]">
                        <section className="space-y-4">
                            <QueueSummary
                                total={queue.assignments.length}
                                selected={selected}
                            />
                            <div className="overflow-x-auto">
                                <ScreeningQueueTable
                                    assignments={queue.assignments}
                                    selectedAssignmentId={selected?.id ?? null}
                                />
                            </div>
                        </section>

                        <section className="space-y-4">
                            <ProtocolCriteria protocol={queue.protocol} />
                            <ScreeningWorkDetail assignment={selected} />
                            <ScreeningDecisionPanel
                                key={selected?.id ?? 'empty'}
                                assignment={selected}
                            />
                        </section>
                    </div>
                )}
            </PageShell>
        </>
    );
}

function QueueSummary({
    selected,
    total,
}: {
    total: number;
    selected: ScreeningSelectedAssignment | null;
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-brand-muted text-brand-muted-foreground">
                    <ClipboardList className="size-4" />
                </div>
                <div className="space-y-1">
                    <CardTitle>{total} assignments</CardTitle>
                    <CardDescription>
                        {selected
                            ? `Selected assignment is ${selected.status_label.toLowerCase()}.`
                            : 'No assignment selected.'}
                    </CardDescription>
                </div>
            </CardHeader>
        </Card>
    );
}

function ProtocolCriteria({
    protocol,
}: {
    protocol: ScreeningQueuePayload['protocol'];
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-status-audit-bg text-status-audit">
                    <FileText className="size-4" />
                </div>
                <div className="space-y-1">
                    <CardTitle>Protocol criteria</CardTitle>
                    <CardDescription>{protocol.title}</CardDescription>
                </div>
            </CardHeader>
            <CardContent className="grid gap-3 md:grid-cols-2">
                <CriteriaFact
                    label="Research question"
                    value={protocol.research_question}
                />
                <CriteriaFact
                    label="Language"
                    value={protocol.language_policy}
                />
                <CriteriaFact
                    label="Inclusion"
                    value={protocol.inclusion_criteria}
                />
                <CriteriaFact
                    label="Exclusion"
                    value={protocol.exclusion_criteria}
                />
            </CardContent>
        </Card>
    );
}

function CriteriaFact({
    label,
    value,
}: {
    label: string;
    value: string | null;
}) {
    return (
        <div className="rounded-md border bg-muted/20 p-3">
            <div className="mb-1 flex items-center gap-2">
                <Badge variant="outline">{label}</Badge>
            </div>
            <div className="line-clamp-4 text-sm leading-5 text-muted-foreground">
                {value ?? 'Not recorded'}
            </div>
        </div>
    );
}

ProjectScreeningQueue.layout = {
    breadcrumbs: [
        {
            title: 'Screening queue',
            href: '#',
        },
    ],
};
