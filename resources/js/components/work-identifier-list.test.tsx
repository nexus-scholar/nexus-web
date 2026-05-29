import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { WorkIdentifierList } from '@/components/work-identifier-list';

describe('WorkIdentifierList', () => {
    it('renders identifier namespaces and primary state', () => {
        render(
            <WorkIdentifierList
                identifiers={[
                    {
                        is_primary: true,
                        namespace: 'doi',
                        value: '10.1000/demo',
                    },
                ]}
            />,
        );

        expect(screen.getByText('DOI')).toBeInTheDocument();
        expect(screen.getByText('10.1000/demo')).toBeInTheDocument();
        expect(screen.getByText('primary')).toBeInTheDocument();
    });

    it('shows a missing identifier state', () => {
        render(<WorkIdentifierList identifiers={[]} />);

        expect(screen.getByText('No identifier recorded.')).toBeInTheDocument();
    });
});
