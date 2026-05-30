import { Head, Link, router } from '@inertiajs/react';
import { GitMerge, RefreshCw } from 'lucide-react';
import { DedupClusterTable } from '@/components/dedup-cluster-table';
import { DedupLockDialog } from '@/components/dedup-lock-dialog';
import { DedupReadinessRail } from '@/components/dedup-readiness-rail';
import { DedupStatusBadge } from '@/components/dedup-status-badge';
import { PageHeader, PageShell } from '@/components/page-shell';
import { ProjectStatusBadge } from '@/components/project-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type {
    DeduplicationClusterDetail,
    DeduplicationPayload,
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
        search_plan: string;
        corpus: string;
        deduplication: string;
        deduplicate: string;
        lock: string;
        activity: string;
    };
};

type Props = {
    project: ProjectPayload;
    deduplication: DeduplicationPayload;
    can: {
        view_deduplication: boolean;
        deduplicate_corpus: boolean;
        lock_corpus: boolean;
    };
};

export default function ProjectDeduplication({
    can,
    deduplication,
    project,
}: Props) {
    const selectedClusterId = deduplication.selectedCluster?.id ?? null;

    const runDeduplication = () => {
        router.post(
            project.urls.deduplicate,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const closeClusterDetail = () => {
        router.get(project.urls.deduplication, {}, { preserveScroll: true });
    };

    const clusterHref = (clusterId: string) =>
        withQuery(project.urls.deduplication, { cluster: clusterId });

    return (
        <>
            <Head title={`${project.name} deduplication`} />

            <PageShell>
                <PageHeader
                    eyebrow={`${project.workspace.name} / ${project.review_type_label}`}
                    title="Deduplication and lock"
                    description="Review duplicate evidence, preserve representatives, and create the locked corpus snapshot."
                    actions={
                        <>
                            <ProjectStatusBadge status={project.status} />
                            <DedupStatusBadge state={deduplication.state} />
                            {can.deduplicate_corpus && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={runDeduplication}
                                    disabled={project.locked_at !== null}
                                >
                                    <RefreshCw className="size-4" />
                                    Run deduplication
                                </Button>
                            )}
                            {can.lock_corpus && (
                                <DedupLockDialog
                                    actionUrl={project.urls.lock}
                                    blockedReason={
                                        deduplication.lock.blocked_reason
                                    }
                                    disabled={!deduplication.lock.available}
                                />
                            )}
                            <Button variant="outline" size="sm" asChild>
                                <Link href={project.urls.corpus}>Corpus</Link>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={project.urls.overview}>
                                    Overview
                                </Link>
                            </Button>
                        </>
                    }
                />

                <DedupReadinessRail deduplication={deduplication} />

                {deduplication.latest_run && (
                    <Card>
                        <CardHeader className="flex-row items-start gap-3">
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-brand-muted text-brand-muted-foreground">
                                <GitMerge className="size-4" />
                            </div>
                            <div className="min-w-0 space-y-1">
                                <CardTitle>Latest run</CardTitle>
                                <CardDescription>
                                    {formatDate(
                                        deduplication.latest_run.completed_at,
                                    )}{' '}
                                    /{' '}
                                    {deduplication.latest_run.duration_ms ?? 0}
                                    ms /{' '}
                                    {
                                        deduplication.latest_run
                                            .duplicates_removed
                                    }{' '}
                                    duplicates removed
                                </CardDescription>
                            </div>
                        </CardHeader>
                    </Card>
                )}

                <DedupClusterTable
                    clusters={deduplication.clusters}
                    selectedClusterId={selectedClusterId}
                    clusterHref={clusterHref}
                />

                <Sheet
                    open={Boolean(deduplication.selectedCluster)}
                    onOpenChange={(open) => {
                        if (!open) {
                            closeClusterDetail();
                        }
                    }}
                >
                    <SheetContent
                        side="right"
                        className="w-[min(100vw,48rem)] gap-0 overflow-hidden p-0 sm:max-w-3xl"
                    >
                        <SheetHeader className="border-b px-5 py-4 pr-12">
                            <SheetTitle>Duplicate cluster</SheetTitle>
                            <SheetDescription>
                                Representative, members, and supporting
                                evidence.
                            </SheetDescription>
                        </SheetHeader>
                        <div className="overflow-y-auto px-5 py-5">
                            <ClusterDetail
                                cluster={deduplication.selectedCluster}
                            />
                        </div>
                    </SheetContent>
                </Sheet>
            </PageShell>
        </>
    );
}

function ClusterDetail({
    cluster,
}: {
    cluster: DeduplicationClusterDetail | null;
}) {
    if (!cluster) {
        return null;
    }

    return (
        <div className="space-y-5">
            <section className="space-y-3">
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">
                        {cluster.cluster_size} members
                    </Badge>
                    <Badge variant="outline">
                        {formatConfidence(cluster.confidence)}
                    </Badge>
                    <Badge variant="outline">{cluster.strategy}</Badge>
                </div>
                <div className="font-mono text-xs text-muted-foreground">
                    {cluster.id}
                </div>
            </section>

            <section className="space-y-3">
                <h3 className="text-sm font-medium">Members</h3>
                <div className="space-y-2">
                    {cluster.members.map((member) => (
                        <article
                            key={member.work_id}
                            className="rounded-lg border bg-card p-3 shadow-xs"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="line-clamp-2 text-sm font-medium">
                                        {member.title}
                                    </div>
                                    <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                        <span>{member.year ?? 'Any year'}</span>
                                        {member.venue_name && (
                                            <span>{member.venue_name}</span>
                                        )}
                                        <span>
                                            {member.cited_by_count.toLocaleString()}{' '}
                                            citations
                                        </span>
                                    </div>
                                </div>
                                {member.is_representative && (
                                    <Badge className="border-transparent bg-status-include-bg text-status-include">
                                        Representative
                                    </Badge>
                                )}
                            </div>
                            <div className="mt-3 flex flex-wrap gap-1">
                                {member.providers.map((provider) => (
                                    <Badge key={provider} variant="outline">
                                        {provider}
                                    </Badge>
                                ))}
                                {member.reason && (
                                    <Badge variant="outline">
                                        {member.reason}
                                    </Badge>
                                )}
                                {member.confidence !== null && (
                                    <Badge variant="outline">
                                        {formatConfidence(member.confidence)}
                                    </Badge>
                                )}
                            </div>
                        </article>
                    ))}
                </div>
            </section>

            <section className="space-y-3">
                <h3 className="text-sm font-medium">Evidence</h3>
                {cluster.evidence.length === 0 ? (
                    <div className="rounded-lg border bg-card p-3 text-sm text-muted-foreground shadow-xs">
                        No evidence rows were recorded.
                    </div>
                ) : (
                    <div className="space-y-2">
                        {cluster.evidence.map((evidence, index) => (
                            <div
                                key={`${evidence.reason}-${index}`}
                                className="rounded-lg border bg-card p-3 text-sm shadow-xs"
                            >
                                <div className="font-medium">
                                    {evidence.reason ?? 'Evidence'}
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {evidence.source ?? 'source'} /{' '}
                                    {formatConfidence(
                                        evidence.confidence ?? null,
                                    )}
                                </div>
                                {evidence.namespace && evidence.value && (
                                    <div className="mt-2 font-mono text-xs text-muted-foreground">
                                        {evidence.namespace}:{evidence.value}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </section>
        </div>
    );
}

function formatConfidence(value: number | null): string {
    if (value === null) {
        return 'Not scored';
    }

    return `${Math.round(value * 100)}%`;
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'Not recorded';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function withQuery(
    url: string,
    params: Record<string, string | number | boolean>,
): string {
    const query = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        query.set(key, String(value));
    });

    const queryString = query.toString();

    return queryString ? `${url}?${queryString}` : url;
}

ProjectDeduplication.layout = {
    breadcrumbs: [
        {
            title: 'Deduplication',
            href: '#',
        },
    ],
};
