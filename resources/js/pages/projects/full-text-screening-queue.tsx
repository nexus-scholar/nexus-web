import { Head, Link } from '@inertiajs/react';
import { ClipboardList, FileText } from 'lucide-react';
import { FullTextScreeningDecisionPanel } from '@/components/full-text-screening-decision-panel';
import { FullTextScreeningQueueTable } from '@/components/full-text-screening-queue-table';
import { FullTextScreeningWorkDetail } from '@/components/full-text-screening-work-detail';
import { PageHeader, PageShell } from '@/components/page-shell';
import { ProjectStatusBadge } from '@/components/project-status-badge';
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
import type {
    FullTextScreeningQueuePayload,
    FullTextScreeningSelectedAssignment,
    ProjectStatus,
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
        full_text: string;
        full_text_screening: string;
        full_text_screening_queue: string;
    };
};

type Props = {
    project: ProjectPayload;
    queue: FullTextScreeningQueuePayload;
    can: {
        screen_assigned_full_text: boolean;
        view_full_text_screening: boolean;
        download_full_text_artifact: boolean;
    };
};

export default function ProjectFullTextScreeningQueue({
    can,
    project,
    queue,
}: Props) {
    const selected = queue.selectedAssignment;

    return (
        <>
            <Head title={`${project.name} full-text queue`} />

            <PageShell>
                <PageHeader
                    eyebrow={`${project.workspace.name} / ${project.review_type_label}`}
                    title="Full-text reviewer queue"
                    description="Inspect the artifact, apply eligibility criteria, and submit final full-text decisions."
                    actions={
                        <>
                            <ProjectStatusBadge status={project.status} />
                            {queue.batch && (
                                <ScreeningStatusBadge
                                    status={queue.batch.status}
                                    label={queue.batch.status_label}
                                />
                            )}
                            {can.view_full_text_screening && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={project.urls.full_text_screening}
                                    >
                                        Screening overview
                                    </Link>
                                </Button>
                            )}
                            <Button variant="outline" size="sm" asChild>
                                <Link href={project.urls.full_text}>
                                    Full text
                                </Link>
                            </Button>
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
                                <CardTitle>
                                    No active full-text screening batch
                                </CardTitle>
                                <CardDescription>
                                    The owner must start full-text screening
                                    before reviewer queues are available.
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
                                <FullTextScreeningQueueTable
                                    assignments={queue.assignments}
                                    selectedAssignmentId={selected?.id ?? null}
                                />
                            </div>
                        </section>

                        <section className="space-y-4">
                            <ProtocolCriteria protocol={queue.protocol} />
                            <FullTextScreeningWorkDetail
                                assignment={selected}
                                canDownload={can.download_full_text_artifact}
                            />
                            <FullTextScreeningDecisionPanel
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
    selected: FullTextScreeningSelectedAssignment | null;
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-brand-muted text-brand-muted-foreground">
                    <ClipboardList className="size-4" />
                </div>
                <div className="space-y-1">
                    <CardTitle>{total} full-text assignments</CardTitle>
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
    protocol: FullTextScreeningQueuePayload['protocol'];
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

ProjectFullTextScreeningQueue.layout = {
    breadcrumbs: [
        {
            title: 'Full-text queue',
            href: '#',
        },
    ],
};
