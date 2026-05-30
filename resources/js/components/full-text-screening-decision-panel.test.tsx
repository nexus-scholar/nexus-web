import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { FullTextScreeningDecisionPanel } from '@/components/full-text-screening-decision-panel';
import type { FullTextScreeningSelectedAssignment } from '@/types';

vi.mock('@inertiajs/react', () => ({
    useForm: (initialData: Record<string, unknown>) => ({
        data: initialData,
        errors: {},
        processing: false,
        post: vi.fn(),
        setData: vi.fn(),
    }),
}));

const assignment: FullTextScreeningSelectedAssignment = {
    artifact: {
        artifact_path: 'full-text/projects/demo/batches/b1/work-1.pdf',
        artifact_type: 'pdf',
        completed_at: '2026-05-30 12:00:00',
        download_url: '/projects/project-1/full-text/artifacts/item-1',
        id: 'item-1',
        metadata: {},
        source_alias: 'demo_oa',
        status: 'success',
        status_label: 'Success',
    },
    can_record_decision: true,
    decision: null,
    decision_url:
        '/projects/project-1/full-text-screening/assignments/a1/decision',
    id: 'assignment-1',
    status: 'pending',
    status_label: 'Pending',
    title_abstract_handoff: {
        decision: 'include',
        decision_label: 'Include',
        reason: 'Eligible from title and abstract.',
    },
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

describe('FullTextScreeningDecisionPanel', () => {
    it('requires artifact confirmation before submit is enabled', () => {
        render(<FullTextScreeningDecisionPanel assignment={assignment} />);

        expect(screen.getByText('Artifact inspected')).toBeVisible();
        expect(
            screen.getByRole('button', { name: /record full-text decision/i }),
        ).toBeDisabled();
    });

    it('renders structured exclusion reasons for full-text decisions', () => {
        render(<FullTextScreeningDecisionPanel assignment={assignment} />);

        expect(screen.getByText('Wrong population')).toBeVisible();
        expect(screen.getByText('Wrong study design')).toBeVisible();
        expect(screen.getByText('Required for exclude')).toBeVisible();
    });

    it('renders a closed state for resolved assignments', () => {
        render(
            <FullTextScreeningDecisionPanel
                assignment={{
                    ...assignment,
                    can_record_decision: false,
                    decision: {
                        decided_at: '2026-05-30 12:30:00',
                        decision: 'exclude',
                        decision_label: 'Exclude',
                        evidence: ['full-text methods'],
                        exclusion_basis: ['Wrong study design'],
                        id: 'decision-1',
                        reason: 'Not an eligible study design.',
                        uncertainty: [],
                    },
                    status: 'resolved',
                    status_label: 'Resolved',
                }}
            />,
        );

        expect(screen.getByText('Decision locked')).toBeVisible();
        expect(
            screen.getByText(
                'The team decision is finalized for this full text.',
            ),
        ).toBeVisible();
    });
});
