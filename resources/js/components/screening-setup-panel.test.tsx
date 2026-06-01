import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ScreeningSetupPanel } from '@/components/screening-setup-panel';
import type { ScreeningReviewer, ScreeningSnapshot } from '@/types';

vi.mock('@inertiajs/react', () => ({
    useForm: <TData extends Record<string, unknown>>(initialData: TData) => ({
        data: initialData,
        errors: {},
        processing: false,
        post: vi.fn(),
        setData: vi.fn(),
    }),
}));

const reviewer: ScreeningReviewer = {
    email: 'reviewer@nexusscholar.test',
    id: 1,
    name: 'Demo Reviewer',
    role: 'reviewer',
    role_label: 'Reviewer',
};

const owner: ScreeningReviewer = {
    email: 'owner@nexusscholar.test',
    id: 2,
    name: 'Demo Owner',
    role: 'owner',
    role_label: 'Owner',
};

const snapshot: ScreeningSnapshot = {
    id: 'snapshot-1',
    locked_at: '2026-06-01 10:00:00',
    lock_reason: 'Ready for screening.',
    metadata: {},
    representative_snapshot: true,
    work_count: 2,
};

const renderPanel = ({
    availableReviewers = [reviewer],
    defaultRequiredReviewerCount = 1,
    disabled = false,
    panelSnapshot = snapshot,
}: {
    availableReviewers?: ScreeningReviewer[];
    defaultRequiredReviewerCount?: number;
    disabled?: boolean;
    panelSnapshot?: ScreeningSnapshot | null;
} = {}) => {
    render(
        <ScreeningSetupPanel
            actionUrl="/projects/project-1/screening/batches"
            availableReviewers={availableReviewers}
            defaultRequiredReviewerCount={defaultRequiredReviewerCount}
            disabled={disabled}
            snapshot={panelSnapshot}
        />,
    );
};

describe('ScreeningSetupPanel', () => {
    it('explains that a representative locked corpus is required', () => {
        renderPanel({ panelSnapshot: null });

        expect(screen.getByText('Screening cannot start yet')).toBeVisible();
        expect(
            screen.getByText('Lock a representative corpus before screening.'),
        ).toBeVisible();
        expect(
            screen.getByRole('button', { name: /start screening/i }),
        ).toBeDisabled();
    });

    it('explains non-representative and empty snapshots', () => {
        renderPanel({
            panelSnapshot: {
                ...snapshot,
                representative_snapshot: false,
                work_count: 0,
            },
        });

        expect(
            screen.getByText(
                'The latest locked corpus snapshot is not marked representative.',
            ),
        ).toBeVisible();
        expect(
            screen.getByText(
                'The locked corpus snapshot has no records to screen.',
            ),
        ).toBeVisible();
    });

    it('explains missing reviewer capacity and permissions', () => {
        renderPanel({
            availableReviewers: [reviewer],
            defaultRequiredReviewerCount: 2,
            disabled: true,
        });

        expect(
            screen.getByText(
                'You need project owner or workspace admin access to manage screening.',
            ),
        ).toBeVisible();
        expect(
            screen.getByText(
                'Select at least 2 active project owners, reviewers, or adjudicators.',
            ),
        ).toBeVisible();
    });

    it('allows an owner to be selected for single-owner screening', () => {
        renderPanel({ availableReviewers: [owner] });

        expect(screen.getByText('Demo Owner')).toBeVisible();
        expect(screen.getByText('1 selected')).toBeVisible();
        expect(
            screen.getByRole('button', { name: /start screening/i }),
        ).toBeEnabled();
    });
});
