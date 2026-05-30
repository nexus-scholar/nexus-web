import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ScreeningDecisionPanel } from '@/components/screening-decision-panel';
import type { ScreeningSelectedAssignment } from '@/types';

vi.mock('@inertiajs/react', () => ({
    useForm: (initialData: Record<string, unknown>) => ({
        data: initialData,
        errors: {},
        processing: false,
        post: vi.fn(),
        setData: vi.fn(),
    }),
}));

const assignment: ScreeningSelectedAssignment = {
    can_record_decision: false,
    decision: {
        decided_at: '2026-05-30 12:00:00',
        decision: 'include',
        decision_label: 'Include',
        evidence: ['primary care'],
        exclusion_basis: [],
        id: 'decision-1',
        reason: 'Matches the inclusion criteria.',
        uncertainty: [],
    },
    decision_url: '/projects/project-1/screening/assignments/a1/decision',
    id: 'assignment-1',
    status: 'resolved',
    status_label: 'Resolved',
    work: {
        abstract: 'Demo abstract.',
        cited_by_count: 12,
        id: 'work-1',
        identifiers: [],
        language: 'en',
        provenance: [],
        providers: [],
        title: 'Digital coaching in primary care',
        venue_name: 'Demo Evidence',
        venue_type: 'journal',
        year: 2024,
    },
};

describe('ScreeningDecisionPanel', () => {
    it('renders a closed decision state for resolved assignments', () => {
        render(<ScreeningDecisionPanel assignment={assignment} />);

        expect(screen.getByText('Decision locked')).toBeVisible();
        expect(
            screen.getByText('The team decision is finalized for this record.'),
        ).toBeVisible();
        expect(screen.getByRole('button', { name: /include/i })).toBeDisabled();
        expect(
            screen.getByRole('button', { name: /decision closed/i }),
        ).toBeDisabled();
    });

    it('explains conflict-locked assignments', () => {
        render(
            <ScreeningDecisionPanel
                assignment={{
                    ...assignment,
                    decision: null,
                    status: 'conflict',
                    status_label: 'Conflict',
                }}
            />,
        );

        expect(
            screen.getByText(
                'This record has a disagreement and must be handled from conflict review.',
            ),
        ).toBeVisible();
    });
});
