import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { DecisionBadge } from '@/components/decision-badge';

describe('DecisionBadge', () => {
    it('renders include, maybe, exclude, and empty states with semantic classes', () => {
        const { rerender } = render(<DecisionBadge decision="include" />);

        expect(screen.getByText('Include')).toHaveClass('text-status-include');

        rerender(<DecisionBadge decision="needs_review" />);
        expect(screen.getByText('Maybe')).toHaveClass('text-status-conflict');

        rerender(<DecisionBadge decision="exclude" />);
        expect(screen.getByText('Exclude')).toHaveClass('text-status-exclude');

        rerender(<DecisionBadge decision={null} />);
        expect(screen.getByText('No decision')).toBeVisible();
    });
});
