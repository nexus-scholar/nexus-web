import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { CorpusMetricStrip } from '@/components/corpus-metric-strip';
import type { CorpusMetrics } from '@/types';

const metrics: CorpusMetrics = {
    duplicate_clusters: 0,
    missing_abstracts: 47,
    missing_identifiers: 0,
    provider_coverage: 3,
    providers: ['arxiv', 'crossref', 'openalex'],
    raw_query_links: 147,
    retracted_records: 0,
    search_queries: 1,
    unique_works: 147,
    year_range: {
        from: 2024,
        to: 2026,
    },
};

describe('CorpusMetricStrip', () => {
    it('renders compact corpus metrics without splitting the year range', () => {
        render(<CorpusMetricStrip metrics={metrics} />);

        expect(screen.getByText('Unique works')).toBeVisible();
        expect(screen.getAllByText('147')).toHaveLength(2);
        expect(screen.getByText('2024-2026')).toHaveClass('whitespace-nowrap');
        expect(screen.getByText('arxiv, crossref, openalex')).toBeVisible();
    });
});
