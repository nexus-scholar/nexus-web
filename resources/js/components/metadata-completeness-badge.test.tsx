import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { MetadataCompletenessBadge } from '@/components/metadata-completeness-badge';

describe('MetadataCompletenessBadge', () => {
    it('prioritizes retracted records over other metadata states', () => {
        render(
            <MetadataCompletenessBadge
                flags={{
                    in_duplicate_cluster: true,
                    missing_abstract: true,
                    missing_identifier: true,
                    retracted: true,
                }}
            />,
        );

        expect(screen.getByText('Retracted')).toBeInTheDocument();
    });

    it('shows ready when no quality flags are present', () => {
        render(
            <MetadataCompletenessBadge
                flags={{
                    in_duplicate_cluster: false,
                    missing_abstract: false,
                    missing_identifier: false,
                    retracted: false,
                }}
            />,
        );

        expect(screen.getByText('Ready')).toBeInTheDocument();
    });
});
