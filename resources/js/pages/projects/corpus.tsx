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
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
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
        router.get(
            project.urls.corpus,
            cleanQuery({
                ...corpus.filters,
                ...filters,
            }),
            { preserveScroll: true },
        );
    };

    const resetFilters = () => {
        router.get(project.urls.corpus, {}, { preserveScroll: true });
    };

    const closeRecordDetail = () => {
        router.get(
            project.urls.corpus,
            cleanQuery({
                ...corpus.filters,
                work: null,
            }),
            { preserveScroll: true },
        );
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
                    <section className="space-y-3">
                        <CorpusRecordTable
                            direction={corpus.filters.direction}
                            records={corpus.records.data}
                            selectedWorkId={selectedWorkId}
                            recordHref={recordHref}
                            sort={corpus.filters.sort}
                            onSort={applyFilters}
                        />
                        <Pagination
                            baseUrl={project.urls.corpus}
                            filters={corpus.filters}
                            records={corpus.records}
                            onPerPageChange={(perPage) => {
                                applyFilters({
                                    per_page: perPage,
                                    work: null,
                                });
                            }}
                        />
                    </section>
                )}

                <Sheet
                    open={Boolean(corpus.selectedRecord)}
                    onOpenChange={(open) => {
                        if (!open) {
                            closeRecordDetail();
                        }
                    }}
                >
                    <SheetContent
                        side="right"
                        className="w-[min(100vw,48rem)] gap-0 overflow-hidden p-0 sm:max-w-3xl"
                    >
                        <SheetHeader className="border-b px-5 py-4 pr-12">
                            <SheetTitle>Work details</SheetTitle>
                            <SheetDescription>
                                Metadata, identifiers, provider sightings, and
                                query provenance.
                            </SheetDescription>
                        </SheetHeader>
                        <div className="overflow-y-auto px-5 py-5">
                            <CorpusRecordDetail
                                record={corpus.selectedRecord}
                                variant="panel"
                            />
                        </div>
                    </SheetContent>
                </Sheet>
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

function Pagination({
    baseUrl,
    filters,
    onPerPageChange,
    records,
}: {
    baseUrl: string;
    filters: CorpusFilters;
    records: PaginatedCorpusRecords;
    onPerPageChange: (perPage: number) => void;
}) {
    if (records.meta.total === 0) {
        return null;
    }

    const pages = paginationWindow(
        records.meta.current_page,
        records.meta.last_page,
    );

    return (
        <div className="flex flex-col gap-3 rounded-lg border bg-card p-3 text-sm shadow-xs xl:flex-row xl:items-center xl:justify-between">
            <div className="flex flex-wrap items-center gap-3">
                <div className="text-muted-foreground">
                    Showing {records.meta.from ?? 0}-{records.meta.to ?? 0} of{' '}
                    {records.meta.total}
                </div>
                <label className="flex items-center gap-2 text-muted-foreground">
                    Rows
                    <select
                        className="h-8 rounded-md border border-input bg-background px-2 text-foreground shadow-xs"
                        value={records.meta.per_page}
                        onChange={(event) => {
                            onPerPageChange(Number(event.target.value));
                        }}
                    >
                        {[10, 20, 30, 50].map((size) => (
                            <option key={size} value={size}>
                                {size}
                            </option>
                        ))}
                    </select>
                </label>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <PaginationLink
                    disabled={records.meta.current_page === 1}
                    href={pageHref(baseUrl, filters, 1)}
                    label="First"
                />
                <PaginationLink
                    disabled={!records.links.prev}
                    href={records.links.prev ?? '#'}
                    label="Previous"
                />
                <div className="flex items-center gap-1">
                    {pages.map((page) => (
                        <Button
                            key={page}
                            variant={
                                page === records.meta.current_page
                                    ? 'default'
                                    : 'outline'
                            }
                            size="sm"
                            asChild
                        >
                            <Link
                                href={pageHref(baseUrl, filters, page)}
                                preserveScroll
                            >
                                {page}
                            </Link>
                        </Button>
                    ))}
                </div>
                <PaginationLink
                    disabled={!records.links.next}
                    href={records.links.next ?? '#'}
                    label="Next"
                />
                <PaginationLink
                    disabled={
                        records.meta.current_page === records.meta.last_page
                    }
                    href={pageHref(baseUrl, filters, records.meta.last_page)}
                    label="Last"
                />
            </div>
        </div>
    );
}

function PaginationLink({
    disabled,
    href,
    label,
}: {
    disabled: boolean;
    href: string;
    label: string;
}) {
    if (disabled) {
        return (
            <Button variant="outline" size="sm" disabled>
                {label}
            </Button>
        );
    }

    return (
        <Button variant="outline" size="sm" asChild>
            <Link href={href} preserveScroll>
                {label}
            </Link>
        </Button>
    );
}

function paginationWindow(current: number, last: number): number[] {
    const start = Math.max(1, current - 1);
    const end = Math.min(last, current + 1);

    return Array.from({ length: end - start + 1 }, (_, index) => start + index);
}

function pageHref(
    baseUrl: string,
    filters: CorpusFilters,
    page: number,
): string {
    return withQuery(baseUrl, {
        ...cleanQuery(filters),
        page,
    });
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

            if (key === 'sort' && value === 'year') {
                return false;
            }

            if (key === 'direction' && value === 'desc') {
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
