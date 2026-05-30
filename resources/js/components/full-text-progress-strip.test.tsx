import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { FullTextProgressStrip } from '@/components/full-text-progress-strip';
import type { FullTextBatch } from '@/types';

const batch: FullTextBatch = {
    candidate_count: 4,
    completed_at: null,
    destination_folder: 'full-text/projects/project-1/batches/batch-1',
    failed_count: 1,
    id: 'batch-1',
    manual_needed_count: 0,
    progress_percent: 75,
    screening_batch_id: 'screening-1',
    skipped_count: 1,
    snapshot_id: 'snapshot-1',
    started_at: '2026-05-30 10:00:00',
    status: 'running',
    status_label: 'Running',
    success_count: 1,
};

describe('FullTextProgressStrip', () => {
    it('summarizes batch progress and terminal states', () => {
        render(<FullTextProgressStrip batch={batch} candidateCount={4} />);

        expect(screen.getByText('75%')).toBeVisible();
        expect(screen.getByText('Retrieved')).toBeVisible();
        expect(screen.getByText('Failed')).toBeVisible();
        expect(screen.getByText('Skipped')).toBeVisible();
        expect(
            screen.getByText(
                '1 records remain queued or running in the background batch.',
            ),
        ).toBeVisible();
    });
});
