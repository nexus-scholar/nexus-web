import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Button } from '@/components/ui/button';
import { WorkflowStepCard } from '@/components/workflow-step-card';

describe('WorkflowStepCard', () => {
    it('marks the active step for guided workflows', () => {
        render(
            <WorkflowStepCard
                step={2}
                title="Define protocol"
                description="Record the review question and inclusion criteria."
                status="current"
                meta="8 required fields"
            />,
        );

        const card = screen.getByText('Define protocol').closest('article');

        expect(card).toHaveAttribute('aria-current', 'step');
        expect(screen.getByText('Current')).toHaveClass(
            'text-brand-muted-foreground',
        );
        expect(screen.getByText('8 required fields')).toBeVisible();
    });

    it('renders workflow actions without changing the step status', () => {
        render(
            <WorkflowStepCard
                title="Invite reviewers"
                description="Add reviewers after the project shell exists."
                status="pending"
                action={<Button variant="outline">Open</Button>}
            />,
        );

        expect(screen.getByRole('button', { name: 'Open' })).toBeVisible();
        expect(screen.getByText('Pending')).toHaveClass('text-status-pending');
    });
});
