import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { SearchRunStatusBadge } from '@/components/search-run-status-badge';

describe('SearchRunStatusBadge', () => {
    it('renders each search-run status label', () => {
        render(
            <>
                <SearchRunStatusBadge status="queued" />
                <SearchRunStatusBadge status="running" />
                <SearchRunStatusBadge status="completed" />
                <SearchRunStatusBadge status="failed" />
            </>,
        );

        expect(screen.getByText('Queued')).toBeInTheDocument();
        expect(screen.getByText('Running')).toBeInTheDocument();
        expect(screen.getByText('Completed')).toBeInTheDocument();
        expect(screen.getByText('Failed')).toBeInTheDocument();
    });
});
