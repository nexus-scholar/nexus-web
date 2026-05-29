import { Head, Link, router } from '@inertiajs/react';
import { FileSearch, LockKeyhole, Search } from 'lucide-react';
import { CorpusFilterBar } from '@/components/corpus-filter-bar';
import { CorpusMetricStrip } from '@/components/corpus-metric-strip';
import { CorpusRecordDetail } from '@/components/corpus-record-detail';
import { CorpusRecordTable } from '@/components/corpus-record-table';
import { CorpusStatusBadge } from '@/components/corpus-status-badge';
import { PageHeader, PageShell } from '@/components/page-shell';
import { ProjectStatusBadge } from '@/components/project-status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    CorpusFilterOptions,
    CorpusFilters,
    CorpusMetrics,
    CorpusRecord,
    CorpusSnapshot,
    CorpusSource,
    PaginatedCorpusRecords,
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
        protocol: string;
        search_plan: string;
        corpus: string;
        activity: string;
    };
};

type CorpusPayload = {
    source: CorpusSource;
    status_label: string;
    snapshot: CorpusSnapshot | null;
    metrics: CorpusMetrics;
    filters: CorpusFilters;
    filterOptions: CorpusFilterOptions;
    records: PaginatedCorpusRecords;
    selectedRecord: CorpusRecord | null;
};

type Props = {
    project: ProjectPayload;
    corpus: CorpusPayload;
    can: {
        view_corpus: boolean;
    };
};

export default function ProjectCorpus({ corpus, project }: Props) {
    const selectedWorkId = corpus.selectedRecord?.id ?? null;

    const applyFilters = (filters: Partial<CorpusFilters>) => {
        router.get(project.urls.corpus, cleanQuery(filters), {
            preserveScroll: true,
        });
    };

    const resetFilters = () => {
        router.get(project.urls.corpus, {}, { preserveScroll: true });
    };

    const recordHref = (workId: string) =>
        withQuery(project.urls.corpus, {
            ...cleanQuery(corpus.filters),
            work: workId,
        });

    return (
        <>
            <Head title={`${project.name} corpus`} />

            <PageShell>
                <PageHeader
                    eyebrow={`${project.workspace.name} / ${project.review_type_label}`}
                    title="Corpus review"
                    description="Inspect draft corpus membership, metadata quality, and provider provenance before deduplication or lock."
                    actions={
                        <>
                            <ProjectStatusBadge status={project.status} />
                            <CorpusStatusBadge source={corpus.source} />
                            <Button variant="outline" size="sm" asChild>
                                <Link href={project.urls.search_plan}>
                                    Search plan
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

                {corpus.snapshot && (
                    <Card>
                        <CardHeader className="flex-row items-start gap-3">
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-status-audit-bg text-status-audit">
                                <LockKeyhole className="size-4" />
                            </div>
                            <div className="space-y-1">
                                <CardTitle>Snapshot-backed corpus</CardTitle>
                                <CardDescription>
                                    Locked{' '}
                                    {formatDate(corpus.snapshot.locked_at)}
                                    {corpus.snapshot.lock_reason
                                        ? ` / ${corpus.snapshot.lock_reason}`
                                        : ''}
                                </CardDescription>
                            </div>
                        </CardHeader>
                    </Card>
                )}

                <CorpusMetricStrip metrics={corpus.metrics} />

                <CorpusFilterBar
                    filters={corpus.filters}
                    options={corpus.filterOptions}
                    onApply={applyFilters}
                    onReset={resetFilters}
                />

                {corpus.metrics.unique_works === 0 ? (
                    <EmptyCorpusState
                        searchPlanUrl={project.urls.search_plan}
                    />
                ) : (
                    <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_25rem]">
                        <section className="space-y-3">
                            <CorpusRecordTable
                                records={corpus.records.data}
                                selectedWorkId={selectedWorkId}
                                recordHref={recordHref}
                            />
                            <Pagination records={corpus.records} />
                        </section>

                        <aside className="xl:sticky xl:top-5 xl:self-start">
                            <CorpusRecordDetail
                                record={corpus.selectedRecord}
                            />
                        </aside>
                    </div>
                )}
            </PageShell>
        </>
    );
}

function EmptyCorpusState({ searchPlanUrl }: { searchPlanUrl: string }) {
    return (
        <Card>
            <CardHeader className="flex-row items-start gap-3">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-brand-muted text-brand-muted-foreground">
                    <FileSearch className="size-4" />
                </div>
                <div className="space-y-1">
                    <CardTitle>No corpus records yet</CardTitle>
                    <CardDescription>
                        Run search before reviewing corpus membership.
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent>
                <Button asChild>
                    <Link href={searchPlanUrl}>
                        <Search className="size-4" />
                        Open search plan
                    </Link>
                </Button>
            </CardContent>
        </Card>
    );
}

function Pagination({ records }: { records: PaginatedCorpusRecords }) {
    if (records.meta.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-3 rounded-lg border bg-card p-3 text-sm shadow-xs sm:flex-row sm:items-center sm:justify-between">
            <div className="text-muted-foreground">
                Showing {records.meta.from ?? 0}-{records.meta.to ?? 0} of{' '}
                {records.meta.total}
            </div>
            <div className="flex gap-2">
                {records.links.prev ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={records.links.prev} preserveScroll>
                            Previous
                        </Link>
                    </Button>
                ) : (
                    <Button variant="outline" size="sm" disabled>
                        Previous
                    </Button>
                )}
                {records.links.next ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={records.links.next} preserveScroll>
                            Next
                        </Link>
                    </Button>
                ) : (
                    <Button variant="outline" size="sm" disabled>
                        Next
                    </Button>
                )}
            </div>
        </div>
    );
}

function cleanQuery(
    filters: Partial<CorpusFilters>,
): Record<string, string | number | boolean> {
    return Object.fromEntries(
        Object.entries(filters).filter(([key, value]) => {
            if (value === null || value === '' || value === undefined) {
                return false;
            }

            if (
                value === false &&
                [
                    'missing_abstract',
                    'missing_identifier',
                    'retracted',
                ].includes(key)
            ) {
                return false;
            }

            if (key === 'duplicate_status' && value === 'all') {
                return false;
            }

            if (key === 'per_page' && value === 10) {
                return false;
            }

            return true;
        }) as Array<[string, string | number | boolean]>,
    );
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

function formatDate(value: string | null): string {
    if (!value) {
        return 'at an unknown time';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

ProjectCorpus.layout = {
    breadcrumbs: [
        {
            title: 'Corpus',
            href: '#',
        },
    ],
};
