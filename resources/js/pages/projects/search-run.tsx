import { Head, Link } from '@inertiajs/react';
import {
    AlertCircle,
    BookOpenCheck,
    CheckCircle2,
    Clock3,
    Database,
    RefreshCw,
    Search,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { MetricCard } from '@/components/metric-card';
import { PageHeader, PageShell } from '@/components/page-shell';
import { ProjectStatusBadge } from '@/components/project-status-badge';
import { SearchRunStatusBadge } from '@/components/search-run-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ProjectStatus, SearchRunStatus } from '@/types';

type ProjectPayload = {
    id: string;
    name: string;
    status: ProjectStatus;
    status_label: string;
    workspace: {
        id: string;
        name: string;
    };
    urls: {
        overview: string;
        protocol: string;
        search_plan: string;
        corpus: string;
        activity: string;
    };
};

type ProviderProgress = {
    provider_alias: string;
    total_raw: number;
    total_unique: number;
    duration_ms: number;
    error_message: string | null;
};

type SearchRunItem = {
    id: string;
    query_key: string;
    label: string;
    query: string;
    providers: string[];
    year_from: number | null;
    year_to: number | null;
    result_limit: number;
    include_raw_data: boolean;
    status: SearchRunStatus;
    status_label: string;
    core_search_query_id: string | null;
    total_raw: number;
    total_unique: number;
    duration_ms: number | null;
    error_message: string | null;
    provider_progress: ProviderProgress[];
};

type SearchRunPayload = {
    id: string;
    status: SearchRunStatus;
    status_label: string;
    plan_version: number;
    query_count: number;
    failure_count: number;
    total_raw: number;
    total_unique: number;
    error_message: string | null;
    started_at: string | null;
    completed_at: string | null;
    failed_at: string | null;
    created_at: string;
    requested_by: {
        id: number;
        name: string;
        email: string;
    } | null;
    urls: {
        self: string;
    };
    items: SearchRunItem[];
    lifecycle: Array<{
        status: SearchRunStatus | 'started' | 'progressed';
        job_name: string;
        summary: Record<string, unknown>;
        error_class: string | null;
        error_message: string | null;
        duration_ms: number;
        occurred_at: string;
    }>;
};

type Props = {
    project: ProjectPayload;
    searchRun: SearchRunPayload;
};

export default function ProjectSearchRun({ project, searchRun }: Props) {
    const completedItems = searchRun.items.filter(
        (item) => item.status === 'completed',
    ).length;

    return (
        <>
            <Head title={`${project.name} search run`} />

            <PageShell>
                <PageHeader
                    eyebrow={`${project.workspace.name} / Search run`}
                    title="Search run"
                    description="Background provider execution, result counts, failures, and lifecycle state."
                    actions={
                        <>
                            <ProjectStatusBadge status={project.status} />
                            <SearchRunStatusBadge status={searchRun.status} />
                            <Button variant="outline" size="sm" asChild>
                                <Link href={searchRun.urls.self}>
                                    <RefreshCw className="size-4" />
                                    Refresh
                                </Link>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={project.urls.search_plan}>
                                    Search plan
                                </Link>
                            </Button>
                            {(searchRun.status === 'completed' ||
                                project.status === 'draft_corpus' ||
                                project.status === 'locked' ||
                                project.status === 'locked_corpus') && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={project.urls.corpus}>
                                        <BookOpenCheck className="size-4" />
                                        Corpus
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid gap-4 md:grid-cols-4">
                    <MetricCard
                        label="Run status"
                        value={searchRun.status_label}
                        description={`Plan v${searchRun.plan_version}`}
                        icon={Clock3}
                    />
                    <MetricCard
                        label="Queries"
                        value={`${completedItems}/${searchRun.query_count}`}
                        description={`${searchRun.failure_count} failed`}
                        icon={Search}
                    />
                    <MetricCard
                        label="Raw records"
                        value={searchRun.total_raw}
                        description="Before deduplication."
                        icon={Database}
                    />
                    <MetricCard
                        label="Unique works"
                        value={searchRun.total_unique}
                        description="After provider aggregation."
                        icon={CheckCircle2}
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
                    <section className="space-y-4">
                        <Card>
                            <CardHeader className="flex-row items-start justify-between gap-4">
                                <div className="space-y-1">
                                    <CardTitle>Query execution</CardTitle>
                                    <CardDescription>
                                        Provider-level records from core are
                                        shown when a queued job has run.
                                    </CardDescription>
                                </div>
                                <Badge variant="outline">
                                    {searchRun.items.length} items
                                </Badge>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {searchRun.items.map((item) => (
                                    <RunItemRow key={item.id} item={item} />
                                ))}
                            </CardContent>
                        </Card>
                    </section>

                    <aside className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Run detail</CardTitle>
                                <CardDescription>
                                    Dispatch and completion facts.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <FactRow
                                    label="Requested by"
                                    value={
                                        searchRun.requested_by?.name ?? 'System'
                                    }
                                />
                                <FactRow
                                    label="Queued"
                                    value={formatDate(searchRun.created_at)}
                                />
                                <FactRow
                                    label="Started"
                                    value={formatDate(searchRun.started_at)}
                                />
                                <FactRow
                                    label="Completed"
                                    value={formatDate(
                                        searchRun.completed_at ??
                                            searchRun.failed_at,
                                    )}
                                />
                                {searchRun.error_message && (
                                    <div className="rounded-md border bg-status-exclude-bg p-3 text-status-exclude">
                                        {searchRun.error_message}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Lifecycle</CardTitle>
                                <CardDescription>
                                    Core lifecycle records for this run.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {searchRun.lifecycle.length === 0 ? (
                                    <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                                        Waiting for the queue worker to record
                                        lifecycle events.
                                    </div>
                                ) : (
                                    searchRun.lifecycle.map((record, index) => (
                                        <div
                                            key={`${record.status}-${index}`}
                                            className="rounded-md border bg-muted/30 p-3"
                                        >
                                            <div className="flex items-center justify-between gap-3">
                                                <Badge
                                                    variant="outline"
                                                    className="capitalize"
                                                >
                                                    {record.status}
                                                </Badge>
                                                <span className="text-xs text-muted-foreground">
                                                    {formatDate(
                                                        record.occurred_at,
                                                    )}
                                                </span>
                                            </div>
                                            {record.error_message && (
                                                <div className="mt-2 text-sm text-status-exclude">
                                                    {record.error_message}
                                                </div>
                                            )}
                                        </div>
                                    ))
                                )}
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            </PageShell>
        </>
    );
}

function RunItemRow({ item }: { item: SearchRunItem }) {
    return (
        <article className="rounded-md border bg-muted/20 p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="text-sm font-medium">{item.label}</h3>
                        <Badge variant="outline">{item.query_key}</Badge>
                    </div>
                    <p className="font-mono text-sm break-words text-muted-foreground">
                        {item.query}
                    </p>
                </div>
                <SearchRunStatusBadge
                    status={item.status}
                    className="w-fit shrink-0"
                />
            </div>

            <div className="mt-4 grid gap-3 sm:grid-cols-4">
                <FactCard label="Raw" value={item.total_raw} />
                <FactCard label="Unique" value={item.total_unique} />
                <FactCard label="Limit" value={item.result_limit} />
                <FactCard
                    label="Duration"
                    value={formatDuration(item.duration_ms)}
                />
            </div>

            {item.error_message && (
                <div className="mt-4 flex gap-2 rounded-md border bg-status-exclude-bg p-3 text-sm text-status-exclude">
                    <AlertCircle className="mt-0.5 size-4 shrink-0" />
                    <span>{item.error_message}</span>
                </div>
            )}

            <div className="mt-4 space-y-2">
                <div className="text-xs font-medium text-muted-foreground">
                    Providers
                </div>
                {item.provider_progress.length === 0 ? (
                    <div className="flex flex-wrap gap-2">
                        {item.providers.map((provider) => (
                            <Badge key={provider} variant="outline">
                                {provider}
                            </Badge>
                        ))}
                    </div>
                ) : (
                    <div className="grid gap-2 sm:grid-cols-2">
                        {item.provider_progress.map((provider) => (
                            <div
                                key={provider.provider_alias}
                                className="rounded-md border bg-background p-3 text-sm"
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <span className="font-medium">
                                        {provider.provider_alias}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {provider.duration_ms} ms
                                    </span>
                                </div>
                                <div className="mt-2 text-xs text-muted-foreground">
                                    {provider.total_raw} raw /{' '}
                                    {provider.total_unique} unique
                                </div>
                                {provider.error_message && (
                                    <div className="mt-2 text-xs text-status-exclude">
                                        {provider.error_message}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </article>
    );
}

function FactCard({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="rounded-md border bg-background p-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-sm font-medium">{value}</div>
        </div>
    );
}

function FactRow({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-medium">{value}</span>
        </div>
    );
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

function formatDuration(value: number | null): string {
    if (value === null) {
        return 'Pending';
    }

    if (value < 1000) {
        return `${value} ms`;
    }

    return `${(value / 1000).toFixed(1)} s`;
}

ProjectSearchRun.layout = {
    breadcrumbs: [
        {
            title: 'Search run',
            href: '#',
        },
    ],
};
