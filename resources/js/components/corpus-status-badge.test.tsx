import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { CorpusStatusBadge } from '@/components/corpus-status-badge';

describe('CorpusStatusBadge', () => {
    it('labels draft and locked corpus sources', () => {
        const { rerender } = render(<CorpusStatusBadge source="draft" />);

        expect(screen.getByText('Query-linked draft')).toBeInTheDocument();

        rerender(<CorpusStatusBadge source="locked" />);

        expect(screen.getByText('Snapshot locked')).toBeInTheDocument();
    });
});
