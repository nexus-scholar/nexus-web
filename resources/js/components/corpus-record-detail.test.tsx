import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { CorpusRecordDetail } from '@/components/corpus-record-detail';
import type { CorpusRecord } from '@/types';

const record: CorpusRecord = {
    abstract: 'A trial abstract about digital cardiometabolic care.',
    abstract_preview: 'A trial abstract about digital cardiometabolic care.',
    authors: [
        {
            is_corresponding: true,
            name: 'Lina Haddad',
            orcid: null,
            position: 1,
        },
    ],
    cited_by_count: 12,
    counts: {
        authors: 1,
        identifiers: 1,
        providers: 1,
        provenance: 1,
    },
    duplicate: null,
    id: 'work-1',
    identifiers: [
        {
            is_primary: true,
            namespace: 'doi',
            value: '10.1000/demo',
        },
    ],
    is_retracted: false,
    language: 'en',
    metadata_flags: {
        in_duplicate_cluster: false,
        missing_abstract: false,
        missing_identifier: false,
        retracted: false,
    },
    providers: [
        {
            first_seen_at: '2026-05-29T12:00:00Z',
            last_seen_at: '2026-05-29T12:05:00Z',
            provider_alias: 'openalex',
            provider_work_id: 'W-DEMO',
        },
    ],
    provenance: [
        {
            provider_alias: 'openalex',
            provider_work_id: 'W-DEMO',
            query_text: 'digital primary care',
            rank: 1,
            search_query_id: 'query-1',
            search_query_label: 'Primary search',
            seen_at: '2026-05-29T12:00:00Z',
        },
    ],
    quality_label: 'Ready',
    retrieved_at: '2026-05-29T12:00:00Z',
    title: 'Digital coaching for cardiometabolic risk',
    url: 'https://example.test/work-1',
    venue_name: 'Primary Care Digital Health',
    venue_type: 'journal',
    year: 2024,
};

describe('CorpusRecordDetail', () => {
    it('renders selected record metadata and provenance', () => {
        render(<CorpusRecordDetail record={record} />);

        expect(
            screen.getByText('Digital coaching for cardiometabolic risk'),
        ).toBeInTheDocument();
        expect(screen.getByText('10.1000/demo')).toBeInTheDocument();
        expect(screen.getByText('Primary search')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Open source record/ }),
        ).toHaveAttribute('href', 'https://example.test/work-1');
    });

    it('renders an empty detail state', () => {
        render(<CorpusRecordDetail record={null} />);

        expect(screen.getByText('No record is selected.')).toBeInTheDocument();
    });
});
