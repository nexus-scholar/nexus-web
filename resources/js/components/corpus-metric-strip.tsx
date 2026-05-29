import {
    AlertTriangle,
    Database,
    FileQuestion,
    GitMerge,
    Link2,
    Search,
} from 'lucide-react';
import { MetricCard } from '@/components/metric-card';
import type { CorpusMetrics } from '@/types';

type CorpusMetricStripProps = {
    metrics: CorpusMetrics;
};

export function CorpusMetricStrip({ metrics }: CorpusMetricStripProps) {
    const yearRange =
        metrics.year_range.from && metrics.year_range.to
            ? `${metrics.year_range.from}-${metrics.year_range.to}`
            : 'Not set';

    return (
        <div className="grid gap-4 md:grid-cols-3 2xl:grid-cols-6">
            <MetricCard
                label="Unique works"
                value={metrics.unique_works}
                description="Distinct corpus records."
                icon={Database}
            />
            <MetricCard
                label="Query links"
                value={metrics.raw_query_links}
                description={`${metrics.search_queries} search queries`}
                icon={Link2}
            />
            <MetricCard
                label="Providers"
                value={metrics.provider_coverage}
                description={metrics.providers.join(', ') || 'No providers'}
                icon={Search}
            />
            <MetricCard
                label="Year range"
                value={yearRange}
                description="Range represented in records."
                icon={FileQuestion}
            />
            <MetricCard
                label="Metadata issues"
                value={metrics.missing_abstracts + metrics.missing_identifiers}
                description={`${metrics.missing_abstracts} abstracts / ${metrics.missing_identifiers} IDs`}
                icon={AlertTriangle}
            />
            <MetricCard
                label="Duplicate clusters"
                value={metrics.duplicate_clusters}
                description={`${metrics.retracted_records} retracted records`}
                icon={GitMerge}
            />
        </div>
    );
}
