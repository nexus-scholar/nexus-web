import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    FileDown,
    FileSearch,
    LockKeyhole,
    ShieldCheck,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { FullTextArtifactSheet } from '@/components/full-text-artifact-sheet';
import { FullTextCandidateTable } from '@/components/full-text-candidate-table';
import { FullTextProgressStrip } from '@/components/full-text-progress-strip';
import { FullTextStatusBadge } from '@/components/full-text-status-badge';
import { PageHeader, PageShell } from '@/components/page-shell';
import { ProjectStatusBadge } from '@/components/project-status-badge';
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
    FullTextOverviewPayload,
    ProjectStatus,
    ReviewType,
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
        corpus: string;
        deduplication: string;
        screening: string;
        full_text: string;
        full_text_batches: string;
        activity: string;
    };
};

type Props = {
    project: ProjectPayload;
    fullText: FullTextOverviewPayload;
    can: {
        view_full_text: boolean;
        manage_full_text: boolean;
        download_full_text_artifact: boolean;
    };
};

export default function ProjectFullText({ can, fullText, project }: Props) {
    const [isClient, setIsClient] = useState(false);
    const hasOpenBatch =
        fullText.batch?.status === 'queued' ||
        fullText.batch?.status === 'running';

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setIsClient(true);
        }, 0);

        return () => {
            window.clearTimeout(timer);
        };
    }, []);

    const startBatch = () => {
        router.post(
            project.urls.full_text_batches,
            {},
            { preserveScroll: true },
        );
    };

    const closeItem = () => {
        router.get(project.urls.full_text, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={`${project.name} full text`} />

            <PageShell>
                <PageHeader
                    eyebrow={`${project.workspace.name} / ${project.review_type_label}`}
                    title="Full-text retrieval"
                    description="Retrieve legal open-access artifacts and inspect source attempts before full-text screening."
                    actions={
                        <>
                            <ProjectStatusBadge status={project.status} />
                            {fullText.batch && (
                                <FullTextStatusBadge
                                    status={fullText.batch.status}
                                    label={fullText.batch.status_label}
                                />
                            )}
                            {can.manage_full_text && (
                                <Button
                                    disabled={
                                        !fullText.readiness.ready ||
                                        hasOpenBatch
                                    }
                                    onClick={startBatch}
                                    size="sm"
                                    type="button"
                                >
                                    <FileDown className="size-4" />
                                    Start retrieval
                                </Button>
                            )}
                            <Button variant="outline" size="sm" asChild>
                                <Link href={project.urls.screening}>
                                    Screening
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

                <ReadinessCard fullText={fullText} />

                <FullTextProgressStrip
                    batch={fullText.batch}
                    candidateCount={fullText.readiness.counts.candidate_count}
                />

                <div className="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
                    <section className="space-y-4">
                        <FullTextCandidateTable
                            canDownload={can.download_full_text_artifact}
                            itemHref={(id) =>
                                `${project.urls.full_text}?item=${encodeURIComponent(id)}`
                            }
                            items={fullText.items}
                            selectedItemId={fullText.selectedItem?.id ?? null}
                        />
                    </section>

                    <aside className="space-y-4">
                        <SourcePolicyCard sources={fullText.sourcePolicy} />
                        <AuditTrail events={fullText.recentAuditEvents} />
                    </aside>
                </div>

                {isClient && (
                    <FullTextArtifactSheet
                        canDownload={can.download_full_text_artifact}
                        item={fullText.selectedItem}
                        open={Boolean(fullText.selectedItem)}
                        onOpenChange={(open) => {
                            if (!open) {
                                closeItem();
                            }
                        }}
                    />
                )}
            </PageShell>
        </>
    );
}

function ReadinessCard({ fullText }: { fullText: FullTextOverviewPayload }) {
    return (
        <Card>
            <CardHeader className="flex-row items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-brand-muted text-brand-muted-foreground">
                    <FileSearch className="size-4" />
                </div>
                <div className="min-w-0 flex-1 space-y-1">
                    <CardTitle>Retrieval input</CardTitle>
                    <CardDescription>
                        {fullText.readiness.ready
                            ? `${fullText.readiness.counts.candidate_count} included or maybe records are ready for automatic retrieval.`
                            : 'Full-text retrieval is waiting on the workflow gates below.'}
                    </CardDescription>
                </div>
                <Badge
                    className={
                        fullText.readiness.ready
                            ? 'border-transparent bg-status-include-bg text-status-include'
                            : 'border-transparent bg-status-pending-bg text-status-pending'
                    }
                >
                    {fullText.readiness.ready ? 'Ready' : 'Blocked'}
                </Badge>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid gap-3 md:grid-cols-4">
                    <Fact
                        icon={CheckCircle2}
                        label="Included"
                        value={fullText.readiness.counts.include}
                        description="Final include outcomes"
                    />
                    <Fact
                        icon={AlertTriangle}
                        label="Maybe"
                        value={fullText.readiness.counts.needs_review}
                        description="Needs full-text check"
                    />
                    <Fact
                        icon={LockKeyhole}
                        label="Snapshot"
                        value={fullText.readiness.snapshot?.work_count ?? 0}
                        description="Locked representative records"
                    />
                    <Fact
                        icon={ShieldCheck}
                        label="Policy"
                        value={fullText.protocol.full_text_policy_label}
                        description="Protocol full-text rule"
                    />
                </div>

                {fullText.readiness.blockers.length > 0 && (
                    <div className="rounded-md border border-status-conflict/25 bg-status-conflict-bg p-3">
                        <div className="text-sm font-medium text-status-conflict">
                            Retrieval blockers
                        </div>
                        <ul className="mt-2 space-y-1 text-sm text-status-conflict">
                            {fullText.readiness.blockers.map((blocker) => (
                                <li key={blocker}>{blocker}</li>
                            ))}
                        </ul>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function SourcePolicyCard({
    sources,
}: {
    sources: FullTextOverviewPayload['sourcePolicy'];
}) {
    const enabledSources = sources.filter((source) => source.enabled);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Source policy</CardTitle>
                <CardDescription>
                    Automatic retrieval is limited to legal open-access source
                    candidates.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                <div className="flex flex-wrap gap-1">
                    {enabledSources.map((source) => (
                        <Badge key={source.alias} variant="outline">
                            {source.label}
                        </Badge>
                    ))}
                </div>
                <p className="text-xs leading-5 text-muted-foreground">
                    Shadow-library providers are intentionally excluded from the
                    automatic source policy.
                </p>
            </CardContent>
        </Card>
    );
}

function AuditTrail({
    events,
}: {
    events: FullTextOverviewPayload['recentAuditEvents'];
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-status-audit-bg text-status-audit">
                    <Activity className="size-4" />
                </div>
                <div className="space-y-1">
                    <CardTitle>Full-text audit</CardTitle>
                    <CardDescription>
                        Recent retrieval events for this project.
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent>
                {events.length === 0 ? (
                    <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                        No full-text audit events recorded yet.
                    </div>
                ) : (
                    <div className="divide-y rounded-md border">
                        {events.map((event) => (
                            <div
                                key={event.id}
                                className="flex items-start gap-3 p-3 text-sm"
                            >
                                <Activity className="mt-0.5 size-4 text-muted-foreground" />
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
    icon: Icon,
    label,
    value,
}: {
    description: string;
    icon: typeof FileSearch;
    label: string;
    value: number | string;
}) {
    return (
        <div className="rounded-md border bg-muted/20 p-3">
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <Icon className="size-4" />
                {label}
            </div>
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

ProjectFullText.layout = {
    breadcrumbs: [
        {
            title: 'Full text',
            href: '#',
        },
    ],
};
