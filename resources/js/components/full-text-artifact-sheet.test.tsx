import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { FullTextArtifactSheet } from '@/components/full-text-artifact-sheet';
import type { FullTextItem } from '@/types';

const item: FullTextItem = {
    artifact_path: 'full-text/projects/project-1/batches/batch-1/work.pdf',
    artifact_type: 'pdf',
    completed_at: '2026-05-30 10:00:00',
    download_url: '/projects/project-1/full-text/artifacts/item-1',
    error_message: null,
    http_status: 200,
    id: 'item-1',
    metadata: { license: 'demo-open-access' },
    screening_decision: 'needs_review',
    screening_decision_label: 'Maybe',
    screening_reason: 'Abstract does not settle outcome eligibility.',
    source_alias: 'demo_oa',
    source_attempts: [
        {
            attempted_at: '2026-05-30T10:00:00Z',
            duration_ms: 32,
            error_message: null,
            file_path: 'full-text/projects/project-1/batches/batch-1/work.pdf',
            http_status: 200,
            id: 'fetch-1',
            metadata: { license: 'demo-open-access' },
            source_alias: 'demo_oa',
            source_url: 'https://example.test/full-text.pdf',
            status: 'success',
            status_label: 'Success',
        },
    ],
    started_at: '2026-05-30 09:59:00',
    status: 'success',
    status_label: 'Retrieved',
    work: {
        abstract: 'Digital intervention in primary care.',
        cited_by_count: 12,
        id: 'work-1',
        identifiers: [{ is_primary: true, namespace: 'doi', value: '10.1/a' }],
        is_retracted: false,
        language: 'en',
        providers: [{ provider_alias: 'openalex', provider_work_id: 'W1' }],
        title: 'Digital coaching for cardiometabolic risk',
        venue_name: 'Primary Care Digital Health',
        venue_type: 'journal',
        year: 2026,
    },
    work_id: 'work-1',
};

describe('FullTextArtifactSheet', () => {
    it('renders artifact metadata, source attempts, and download access', () => {
        render(
            <FullTextArtifactSheet
                canDownload
                item={item}
                onOpenChange={vi.fn()}
                open
            />,
        );

        expect(
            screen.getByText('Digital coaching for cardiometabolic risk'),
        ).toBeVisible();
        expect(screen.getByText('Maybe')).toBeVisible();
        expect(
            screen.getByText('Abstract does not settle outcome eligibility.'),
        ).toBeVisible();
        expect(
            screen.getByRole('link', { name: /Download artifact/ }),
        ).toHaveAttribute(
            'href',
            '/projects/project-1/full-text/artifacts/item-1',
        );
        expect(screen.getByText('Source attempts')).toBeVisible();
        expect(
            screen.getByText('https://example.test/full-text.pdf'),
        ).toBeVisible();
        expect(screen.getByText('doi: 10.1/a')).toBeVisible();
    });

    it('renders a clear empty state without a selected item', () => {
        render(
            <FullTextArtifactSheet item={null} onOpenChange={vi.fn()} open />,
        );

        expect(screen.getByText('No record selected')).toBeVisible();
    });
});
