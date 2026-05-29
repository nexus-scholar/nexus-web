import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import {
    ProjectRoleBadge,
    ProjectStatusBadge,
    ProtocolStatusBadge,
} from '@/components/project-status-badge';

describe('project workflow badges', () => {
    it('renders project status with semantic styling', () => {
        render(<ProjectStatusBadge status="ready_for_search" />);

        expect(screen.getByText('Ready for search')).toHaveClass(
            'text-status-include',
        );
    });

    it('renders protocol status with lock styling', () => {
        render(<ProtocolStatusBadge status="locked" />);

        expect(screen.getByText('Locked')).toHaveClass('text-status-audit');
    });

    it('renders project roles with caller-provided labels', () => {
        render(
            <ProjectRoleBadge status="adjudicator">
                Lead adjudicator
            </ProjectRoleBadge>,
        );

        expect(screen.getByText('Lead adjudicator')).toHaveClass(
            'text-status-conflict',
        );
    });
});
