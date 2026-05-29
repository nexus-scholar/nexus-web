import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { WorkspaceStatusBadge } from '@/components/workspace-status-badge';

describe('WorkspaceStatusBadge', () => {
    it('renders the default label for a workspace status', () => {
        render(<WorkspaceStatusBadge status="shared" />);

        expect(screen.getByText('Shared')).toHaveClass('bg-brand-muted');
    });

    it('allows a caller-provided label without losing status styling', () => {
        render(
            <WorkspaceStatusBadge status="disabled">
                Suspended
            </WorkspaceStatusBadge>,
        );

        expect(screen.getByText('Suspended')).toHaveClass(
            'text-status-exclude',
        );
    });
});
