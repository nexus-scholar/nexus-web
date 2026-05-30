import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { ScreeningQueueTable } from '@/components/screening-queue-table';
import type { ScreeningQueueAssignment } from '@/types';

vi.mock('@inertiajs/react', () => ({
    Link: ({
        children,
        href,
        ...props
    }: {
        children: ReactNode;
        href: string;
    }) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
}));

const assignments: ScreeningQueueAssignment[] = [
    {
        decided_at: null,
        decision: 'include',
        decision_label: 'Include',
        href: '/projects/1/screening/queue?assignment=a1',
        id: 'a1',
        status: 'decided',
        status_label: 'Submitted',
        title: 'Digital coaching in primary care',
        venue_name: 'Demo Evidence',
        work_id: 'w1',
        year: 2024,
    },
];

describe('ScreeningQueueTable', () => {
    it('renders assignment status, decision, and open link', () => {
        render(
            <ScreeningQueueTable
                assignments={assignments}
                selectedAssignmentId="a1"
            />,
        );

        expect(
            screen.getByText('Digital coaching in primary care'),
        ).toBeVisible();
        expect(screen.getByText('Submitted')).toBeVisible();
        expect(screen.getByText('Include')).toBeVisible();
        expect(screen.getByRole('link', { name: /view/i })).toHaveAttribute(
            'href',
            '/projects/1/screening/queue?assignment=a1',
        );
    });

    it('renders an empty queue state', () => {
        render(
            <ScreeningQueueTable
                assignments={[]}
                selectedAssignmentId={null}
            />,
        );

        expect(
            screen.getByText('No assignments are available for this reviewer.'),
        ).toBeVisible();
    });
});
