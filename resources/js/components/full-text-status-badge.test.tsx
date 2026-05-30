import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { FullTextStatusBadge } from '@/components/full-text-status-badge';

describe('FullTextStatusBadge', () => {
    it('renders retrieval status labels with semantic styling', () => {
        render(<FullTextStatusBadge status="completed_with_failures" />);

        expect(screen.getByText('Completed With Failures')).toHaveClass(
            'text-status-conflict',
        );
    });

    it('accepts server-provided labels', () => {
        render(<FullTextStatusBadge status="success" label="Retrieved" />);

        expect(screen.getByText('Retrieved')).toHaveClass(
            'text-status-include',
        );
    });
});
