import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { CorpusFilterBar } from '@/components/corpus-filter-bar';
import type { CorpusFilterOptions, CorpusFilters } from '@/types';

const filters: CorpusFilters = {
    duplicate_status: 'all',
    identifier: null,
    missing_abstract: false,
    missing_identifier: false,
    per_page: 10,
    provider: null,
    q: null,
    retracted: false,
    search_query: null,
    work: null,
    year_from: null,
    year_to: null,
};

const options: CorpusFilterOptions = {
    duplicate_statuses: [
        { label: 'All records', value: 'all' },
        { label: 'In duplicate cluster', value: 'in_cluster' },
        { label: 'Not clustered', value: 'not_clustered' },
    ],
    identifiers: ['doi', 'pubmed'],
    providers: ['openalex', 'pubmed'],
    search_queries: [
        {
            id: 'query-1',
            label: 'Primary search',
            query: 'digital primary care',
        },
    ],
};

describe('CorpusFilterBar', () => {
    it('submits URL-owned corpus filters', async () => {
        const user = userEvent.setup();
        const onApply = vi.fn();

        render(
            <CorpusFilterBar
                filters={filters}
                options={options}
                onApply={onApply}
                onReset={vi.fn()}
            />,
        );

        await user.type(screen.getByLabelText('Search'), 'telehealth');
        await user.selectOptions(screen.getByLabelText('Provider'), 'pubmed');
        await user.selectOptions(
            screen.getByLabelText('Search query'),
            'query-1',
        );
        await user.type(screen.getByLabelText('Year from'), '2020');
        await user.selectOptions(screen.getByLabelText('Identifier'), 'doi');
        await user.click(screen.getByRole('checkbox', { name: 'Retracted' }));
        await user.click(screen.getByRole('button', { name: 'Apply' }));

        expect(onApply).toHaveBeenCalledWith(
            expect.objectContaining({
                identifier: 'doi',
                provider: 'pubmed',
                q: 'telehealth',
                retracted: true,
                search_query: 'query-1',
                year_from: 2020,
            }),
        );
    });

    it('calls reset without leaking current filters', async () => {
        const user = userEvent.setup();
        const onReset = vi.fn();

        render(
            <CorpusFilterBar
                filters={{ ...filters, q: 'existing' }}
                options={options}
                onApply={vi.fn()}
                onReset={onReset}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Reset' }));

        expect(onReset).toHaveBeenCalledOnce();
    });
});
