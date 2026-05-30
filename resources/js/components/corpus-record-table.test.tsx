import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { CorpusRecordTable } from '@/components/corpus-record-table';
import type { CorpusRecord } from '@/types';

vi.mock('@inertiajs/react', () => ({
    Link: ({
        children,
        href,
        ...props
    }: {
        children: ReactNode;
        href: string;
    }) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
}));

const records: CorpusRecord[] = [
    {
        abstract: null,
        abstract_preview: 'Preview',
        authors: [],
        cited_by_count: 12,
        counts: {
            authors: 1,
            identifiers: 1,
            providers: 1,
            provenance: 2,
        },
        duplicate: null,
        id: 'work-1',
        identifiers: [],
        is_retracted: false,
        language: 'en',
        metadata_flags: {
            in_duplicate_cluster: false,
            missing_abstract: false,
            missing_identifier: false,
            retracted: false,
        },
        providers: [
            {
                first_seen_at: null,
                last_seen_at: null,
                provider_alias: 'openalex',
                provider_work_id: 'W1',
            },
        ],
        provenance: [],
        quality_label: 'Ready',
        retrieved_at: '2026-05-29T12:00:00Z',
        title: 'Digital coaching for cardiometabolic risk',
        url: 'https://example.test/work-1',
        venue_name: 'Demo Evidence',
        venue_type: 'journal',
        year: 2024,
    },
];

describe('CorpusRecordTable', () => {
    it('supports server-owned sorting from column headers', async () => {
        const user = userEvent.setup();
        const onSort = vi.fn();

        render(
            <CorpusRecordTable
                direction="desc"
                records={records}
                recordHref={(id) => `/corpus?work=${id}`}
                selectedWorkId={null}
                sort="year"
                onSort={onSort}
            />,
        );

        await user.click(screen.getByRole('button', { name: /Year/ }));

        expect(onSort).toHaveBeenCalledWith({
            direction: 'asc',
            sort: 'year',
            work: null,
        });
    });

    it('lets the reader manage visible columns', async () => {
        const user = userEvent.setup();

        render(
            <CorpusRecordTable
                direction="desc"
                records={records}
                recordHref={(id) => `/corpus?work=${id}`}
                selectedWorkId={null}
                sort="year"
                onSort={vi.fn()}
            />,
        );

        await user.click(screen.getByRole('button', { name: /Columns/ }));
        await user.click(
            screen.getByRole('menuitemcheckbox', { name: 'Providers' }),
        );

        expect(screen.queryByText('openalex')).not.toBeInTheDocument();
    });

    it('tracks selected rows for read-only bulk actions', async () => {
        const user = userEvent.setup();

        render(
            <CorpusRecordTable
                direction="desc"
                records={records}
                recordHref={(id) => `/corpus?work=${id}`}
                selectedWorkId={null}
                sort="year"
                onSort={vi.fn()}
            />,
        );

        await user.click(
            screen.getByRole('checkbox', {
                name: 'Select Digital coaching for cardiometabolic risk',
            }),
        );

        const toolbar = screen.getByText('1 selected').closest('div');

        expect(toolbar).not.toBeNull();
        expect(
            within(toolbar as HTMLElement).getByText('1 selected'),
        ).toBeVisible();
    });
});
