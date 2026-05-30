import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ReviewerWorkloadList } from '@/components/reviewer-workload-list';
import type { ScreeningWorkload } from '@/types';

const workload: ScreeningWorkload[] = [
    {
        counts: {
            conflict: 1,
            decided: 2,
            in_progress: 0,
            pending: 3,
            resolved: 4,
            total: 10,
        },
        email: 'reviewer@nexusscholar.test',
        name: 'Maya Reviewer',
        user_id: 7,
    },
];

describe('ReviewerWorkloadList', () => {
    it('renders reviewer progress and status counts', () => {
        render(<ReviewerWorkloadList workload={workload} />);

        expect(screen.getByText('Maya Reviewer')).toBeVisible();
        expect(screen.getByText('4/10')).toBeVisible();
        expect(screen.getByText('3 pending')).toBeVisible();
        expect(screen.getByText('1 conflict')).toBeVisible();
    });

    it('renders an empty assignment state', () => {
        render(<ReviewerWorkloadList workload={[]} />);

        expect(
            screen.getByText('No reviewer assignments have been created.'),
        ).toBeVisible();
    });
});
