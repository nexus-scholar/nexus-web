import {
    AlertTriangle,
    Database,
    FileQuestion,
    GitMerge,
    Link2,
    Search,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { CorpusMetrics } from '@/types';

type CorpusMetricStripProps = {
    metrics: CorpusMetrics;
};

type MetricItem = {
    label: string;
    value: string | number;
    description: string;
    icon: LucideIcon;
};

export function CorpusMetricStrip({ metrics }: CorpusMetricStripProps) {
    const yearRange =
        metrics.year_range.from && metrics.year_range.to
            ? `${metrics.year_range.from}-${metrics.year_range.to}`
            : 'Not set';
    const items: MetricItem[] = [
        {
            description: 'Distinct corpus records',
            icon: Database,
            label: 'Unique works',
            value: metrics.unique_works,
        },
        {
            description: `${metrics.search_queries} search ${
                metrics.search_queries === 1 ? 'query' : 'queries'
            }`,
            icon: Link2,
            label: 'Query links',
            value: metrics.raw_query_links,
        },
        {
            description: compactProviderList(metrics.providers),
            icon: Search,
            label: 'Providers',
            value: metrics.provider_coverage,
        },
        {
            description: 'Publication years represented',
            icon: FileQuestion,
            label: 'Year range',
            value: yearRange,
        },
        {
            description: `${metrics.missing_abstracts} abstracts / ${metrics.missing_identifiers} IDs`,
            icon: AlertTriangle,
            label: 'Metadata issues',
            value: metrics.missing_abstracts + metrics.missing_identifiers,
        },
        {
            description: `${metrics.retracted_records} retracted ${
                metrics.retracted_records === 1 ? 'record' : 'records'
            }`,
            icon: GitMerge,
            label: 'Duplicate clusters',
            value: metrics.duplicate_clusters,
        },
    ];

    return (
        <section className="overflow-hidden rounded-lg border bg-border/70 shadow-xs">
            <div className="grid gap-px bg-border/70 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
                {items.map((item) => (
                    <MetricTile key={item.label} item={item} />
                ))}
            </div>
        </section>
    );
}

function MetricTile({ item }: { item: MetricItem }) {
    const Icon = item.icon;

    return (
        <div className="flex min-h-24 min-w-0 items-start gap-3 bg-card p-4">
            <div className="flex size-7 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                <Icon className="size-4" />
            </div>
            <div className="min-w-0 flex-1">
                <div className="text-xs font-medium text-muted-foreground">
                    {item.label}
                </div>
                <div className="mt-2 text-2xl leading-none font-semibold whitespace-nowrap tabular-nums">
                    {item.value}
                </div>
                <div className="mt-2 truncate text-xs leading-5 text-muted-foreground">
                    {item.description}
                </div>
            </div>
        </div>
    );
}

function compactProviderList(providers: string[]): string {
    if (providers.length === 0) {
        return 'No providers';
    }

    if (providers.length <= 3) {
        return providers.join(', ');
    }

    return `${providers.slice(0, 3).join(', ')} +${providers.length - 3}`;
}
