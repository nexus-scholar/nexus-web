import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ProviderProvenanceList } from '@/components/provider-provenance-list';

describe('ProviderProvenanceList', () => {
    it('renders provider sightings and query provenance', () => {
        render(
            <ProviderProvenanceList
                providers={[
                    {
                        first_seen_at: '2026-05-29T12:00:00Z',
                        last_seen_at: '2026-05-29T12:05:00Z',
                        provider_alias: 'openalex',
                        provider_work_id: 'W-DEMO',
                    },
                ]}
                provenance={[
                    {
                        provider_alias: 'openalex',
                        provider_work_id: 'W-DEMO',
                        query_text: 'digital primary care',
                        rank: 2,
                        search_query_id: 'query-1',
                        search_query_label: 'Primary search',
                        seen_at: '2026-05-29T12:00:00Z',
                    },
                ]}
            />,
        );

        expect(screen.getAllByText('openalex')).toHaveLength(2);
        expect(screen.getAllByText('W-DEMO')).toHaveLength(2);
        expect(screen.getByText('Primary search')).toBeInTheDocument();
        expect(screen.getByText('2')).toBeInTheDocument();
    });

    it('shows empty provenance states', () => {
        render(<ProviderProvenanceList providers={[]} provenance={[]} />);

        expect(
            screen.getByText('No provider sightings recorded.'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('No query provenance recorded.'),
        ).toBeInTheDocument();
    });
});
