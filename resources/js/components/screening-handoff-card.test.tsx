import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ScreeningHandoffCard } from '@/components/screening-handoff-card';
import type { ScreeningBatch } from '@/types';

const completedBatch: ScreeningBatch = {
    completed_at: '2026-05-30 12:00:00',
    counts: {
        assignments: {
            conflict: 0,
            decided: 0,
            in_progress: 0,
            pending: 0,
            resolved: 6,
            total: 6,
        },
        conflicts: {
            open: 0,
            resolved: 1,
        },
        decisions: {
            exclude: 2,
            include: 3,
            needs_review: 2,
        },
        outcomes: {
            exclude: 1,
            excluded: 1,
            include: 2,
            needs_review: 1,
            ready_for_full_text: 3,
            resolved_works: 4,
            total_works: 4,
            unresolved_works: 0,
        },
    },
    criteria_hash: 'abc',
    id: 'batch-1',
    progress_percent: 100,
    required_reviewer_count: 2,
    snapshot_id: 'snapshot-1',
    stage: 'title_abstract',
    stage_label: 'Title and abstract',
    started_at: '2026-05-30 11:00:00',
    status: 'completed',
    status_label: 'Completed',
};

describe('ScreeningHandoffCard', () => {
    it('shows completed full-text readiness from final outcomes', () => {
        render(<ScreeningHandoffCard batch={completedBatch} />);

        expect(screen.getByText('Full-text readiness')).toBeVisible();
        expect(screen.getByText('Handoff ready')).toBeVisible();
        expect(
            screen.getByText(
                '3 records are ready for the next full-text workflow.',
            ),
        ).toBeVisible();
        expect(screen.getByText('Ready for full text')).toBeVisible();
        expect(screen.getByText('Included')).toBeVisible();
        expect(screen.getByText('Maybe')).toBeVisible();
        expect(screen.getByText('Excluded')).toBeVisible();
    });

    it('shows blockers before completion', () => {
        render(
            <ScreeningHandoffCard
                batch={{
                    ...completedBatch,
                    counts: {
                        ...completedBatch.counts,
                        conflicts: { open: 1, resolved: 0 },
                        outcomes: {
                            ...completedBatch.counts.outcomes,
                            unresolved_works: 2,
                        },
                    },
                    status: 'conflicts',
                    status_label: 'Conflicts',
                }}
            />,
        );

        expect(
            screen.getByText(
                '2 records still need reviewer agreement or adjudication.',
            ),
        ).toBeVisible();
        expect(screen.getByText('Conflicts')).toBeVisible();
    });
});
