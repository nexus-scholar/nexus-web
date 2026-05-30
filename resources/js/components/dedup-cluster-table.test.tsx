import { render, screen, within } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { DedupClusterTable } from '@/components/dedup-cluster-table';
import type { DeduplicationCluster } from '@/types';

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

const clusters: DeduplicationCluster[] = [
    {
        cluster_size: 2,
        confidence: 1,
        created_at: '2026-05-30T10:00:00Z',
        id: 'cluster-1',
        is_locked: false,
        reasons: ['doi_match', 'title_fuzzy'],
        representative_title:
            'Digital coaching for cardiometabolic risk in primary care',
        representative_work_id: 'work-1',
        strategy: 'core-v1-plus-exact-identifiers',
    },
];

describe('DedupClusterTable', () => {
    it('renders cluster evidence and detail links', () => {
        render(
            <DedupClusterTable
                clusters={clusters}
                selectedClusterId={null}
                clusterHref={(id) => `/deduplication?cluster=${id}`}
            />,
        );

        expect(screen.getByText('1')).toBeVisible();
        expect(screen.getByText('duplicate cluster')).toBeVisible();
        expect(screen.getByText('doi_match')).toBeVisible();
        expect(screen.getByText('title_fuzzy')).toBeVisible();
        expect(screen.getByRole('link', { name: /View/ })).toHaveAttribute(
            'href',
            '/deduplication?cluster=cluster-1',
        );
    });

    it('renders an empty clear state when no duplicates exist', () => {
        render(
            <DedupClusterTable
                clusters={[]}
                selectedClusterId={null}
                clusterHref={(id) => `/deduplication?cluster=${id}`}
            />,
        );

        expect(screen.getByText('No duplicate clusters')).toBeVisible();
        expect(
            screen.getByText(
                'The latest run did not find duplicate candidates.',
            ),
        ).toBeVisible();
    });

    it('marks the selected cluster row', () => {
        render(
            <DedupClusterTable
                clusters={clusters}
                selectedClusterId="cluster-1"
                clusterHref={(id) => `/deduplication?cluster=${id}`}
            />,
        );

        const row = screen
            .getByText(
                'Digital coaching for cardiometabolic risk in primary care',
            )
            .closest('tr');

        expect(row).not.toBeNull();
        expect(within(row as HTMLElement).getByText('100%')).toBeVisible();
        expect(row).toHaveAttribute('data-state', 'selected');
    });
});
