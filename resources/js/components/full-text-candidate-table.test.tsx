import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { FullTextCandidateTable } from '@/components/full-text-candidate-table';
import type { FullTextItem } from '@/types';

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

const items: FullTextItem[] = [
    {
        artifact_path: 'full-text/project/file.pdf',
        artifact_type: 'pdf',
        completed_at: '2026-05-30 10:00:00',
        download_url: '/projects/project-1/full-text/artifacts/item-1',
        error_message: null,
        http_status: 200,
        id: 'item-1',
        metadata: {},
        screening_decision: 'include',
        screening_decision_label: 'Include',
        screening_reason: 'Eligible intervention.',
        source_alias: 'demo_oa',
        source_attempts: [],
        started_at: '2026-05-30 09:59:00',
        status: 'success',
        status_label: 'Retrieved',
        work: {
            abstract: 'Digital intervention in primary care.',
            cited_by_count: 12,
            id: 'work-1',
            identifiers: [
                { is_primary: true, namespace: 'doi', value: '10.1/a' },
            ],
            is_retracted: false,
            language: 'en',
            providers: [{ provider_alias: 'openalex', provider_work_id: 'W1' }],
            title: 'Digital coaching for cardiometabolic risk',
            venue_name: 'Primary Care Digital Health',
            venue_type: 'journal',
            year: 2026,
        },
        work_id: 'work-1',
    },
    {
        artifact_path: null,
        artifact_type: null,
        completed_at: null,
        download_url: null,
        error_message: null,
        http_status: null,
        id: 'item-2',
        metadata: {},
        screening_decision: 'needs_review',
        screening_decision_label: 'Maybe',
        screening_reason: 'Outcome eligibility is unclear.',
        source_alias: null,
        source_attempts: [],
        started_at: null,
        status: 'queued',
        status_label: 'Queued',
        work: {
            abstract: 'Medication adherence reminders.',
            cited_by_count: 5,
            id: 'work-2',
            identifiers: [],
            is_retracted: false,
            language: 'en',
            providers: [{ provider_alias: 'pubmed', provider_work_id: 'P1' }],
            title: 'Mobile reminders for adherence',
            venue_name: 'Medication Support',
            venue_type: 'journal',
            year: 2024,
        },
        work_id: 'work-2',
    },
];

describe('FullTextCandidateTable', () => {
    it('renders candidates with detail and artifact actions', () => {
        render(
            <FullTextCandidateTable
                canDownload
                itemHref={(id) => `/full-text?item=${id}`}
                items={items}
                selectedItemId="item-1"
            />,
        );

        expect(
            screen.getByText('Digital coaching for cardiometabolic risk'),
        ).toBeVisible();
        expect(screen.getByText('Retrieved')).toBeVisible();
        expect(screen.getByRole('link', { name: /Download/ })).toHaveAttribute(
            'href',
            '/projects/project-1/full-text/artifacts/item-1',
        );
        expect(
            screen.getAllByRole('link', { name: /View/ })[0],
        ).toHaveAttribute('href', '/full-text?item=item-1');

        const selectedRow = screen
            .getByText('Digital coaching for cardiometabolic risk')
            .closest('tr');

        expect(selectedRow).toHaveAttribute('data-state', 'selected');
    });

    it('filters candidates by visible text', async () => {
        const user = userEvent.setup();
        render(
            <FullTextCandidateTable
                itemHref={(id) => `/full-text?item=${id}`}
                items={items}
                selectedItemId={null}
            />,
        );

        await user.type(
            screen.getByRole('textbox', {
                name: 'Search full-text candidates',
            }),
            'adherence',
        );

        expect(
            screen.getByText('Mobile reminders for adherence'),
        ).toBeVisible();
        expect(
            screen.queryByText('Digital coaching for cardiometabolic risk'),
        ).not.toBeInTheDocument();
    });

    it('tracks selected rows without mutating server state', async () => {
        const user = userEvent.setup();
        render(
            <FullTextCandidateTable
                itemHref={(id) => `/full-text?item=${id}`}
                items={items}
                selectedItemId={null}
            />,
        );

        const row = screen
            .getByText('Mobile reminders for adherence')
            .closest('tr');

        expect(row).not.toBeNull();
        await user.click(
            within(row as HTMLElement).getByRole('checkbox', {
                name: 'Select Mobile reminders for adherence',
            }),
        );

        expect(screen.getByText('2 rows / 1 selected')).toBeVisible();
    });
});
