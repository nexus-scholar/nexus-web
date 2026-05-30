import { ExternalLink } from 'lucide-react';
import type { ReactNode } from 'react';
import { MetadataCompletenessBadge } from '@/components/metadata-completeness-badge';
import { ProviderProvenanceList } from '@/components/provider-provenance-list';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { WorkIdentifierList } from '@/components/work-identifier-list';
import type { CorpusRecord } from '@/types';

type CorpusRecordDetailProps = {
    record: CorpusRecord | null;
    variant?: 'card' | 'panel';
};

export function CorpusRecordDetail({
    record,
    variant = 'card',
}: CorpusRecordDetailProps) {
    if (!record) {
        return <EmptyDetail variant={variant} />;
    }

    if (variant === 'panel') {
        return (
            <div className="space-y-5">
                <RecordHeading record={record} />
                <DetailSections record={record} />
            </div>
        );
    }

    return (
        <Card>
            <CardHeader className="space-y-3">
                <RecordHeading record={record} />
            </CardHeader>
            <CardContent>
                <DetailSections record={record} />
            </CardContent>
        </Card>
    );
}

function EmptyDetail({ variant }: { variant: 'card' | 'panel' }) {
    if (variant === 'panel') {
        return (
            <div className="rounded-md border bg-muted/30 p-4 text-sm text-muted-foreground">
                No record is selected.
            </div>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Record detail</CardTitle>
                <CardDescription>
                    Select a corpus record to inspect metadata and provenance.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="rounded-md border bg-muted/30 p-4 text-sm text-muted-foreground">
                    No record is selected.
                </div>
            </CardContent>
        </Card>
    );
}

function RecordHeading({ record }: { record: CorpusRecord }) {
    return (
        <>
            <div className="flex flex-wrap items-center gap-2">
                <MetadataCompletenessBadge
                    flags={record.metadata_flags}
                    label={record.quality_label}
                />
                {record.duplicate && (
                    <Badge variant="outline">
                        Cluster size {record.duplicate.cluster_size}
                    </Badge>
                )}
            </div>
            <div className="space-y-1">
                <CardTitle className="text-base leading-6">
                    {record.title}
                </CardTitle>
                <CardDescription>
                    {[record.year, record.venue_name, record.language]
                        .filter(Boolean)
                        .join(' / ') || 'No venue metadata recorded'}
                </CardDescription>
            </div>
        </>
    );
}

function DetailSections({ record }: { record: CorpusRecord }) {
    return (
        <div className="space-y-5">
            <section className="space-y-2">
                <h3 className="text-sm font-medium">Abstract</h3>
                <p className="max-h-56 overflow-auto rounded-md border bg-muted/20 p-3 text-sm leading-6 text-muted-foreground">
                    {record.abstract ?? 'No abstract recorded.'}
                </p>
            </section>

            <section className="space-y-2">
                <h3 className="text-sm font-medium">Identifiers</h3>
                <WorkIdentifierList identifiers={record.identifiers} />
            </section>

            <section className="grid gap-3 rounded-md border bg-muted/20 p-3 text-sm sm:grid-cols-3">
                <Fact label="Authors" value={record.counts.authors} />
                <Fact label="Citations" value={record.cited_by_count} />
                <Fact
                    label="Retrieved"
                    value={formatDate(record.retrieved_at)}
                />
            </section>

            {record.url && (
                <a
                    href={record.url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
                >
                    Open source record
                    <ExternalLink className="size-4" />
                </a>
            )}

            <ProviderProvenanceList
                providers={record.providers}
                provenance={record.provenance}
            />
        </div>
    );
}

function Fact({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 font-medium">{value}</div>
        </div>
    );
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'Not recorded';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
    }).format(new Date(value));
}
