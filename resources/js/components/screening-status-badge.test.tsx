import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ScreeningStatusBadge } from '@/components/screening-status-badge';

describe('ScreeningStatusBadge', () => {
    it('uses conflict styling for open conflicts', () => {
        render(<ScreeningStatusBadge status="open" />);

        expect(screen.getByText('Open')).toHaveClass('text-status-conflict');
    });

    it('allows caller-owned labels for assignment counts', () => {
        render(<ScreeningStatusBadge status="resolved" label="4 resolved" />);

        expect(screen.getByText('4 resolved')).toHaveClass(
            'text-status-include',
        );
    });
});
