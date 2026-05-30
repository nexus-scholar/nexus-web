import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { DedupReadinessRail } from '@/components/dedup-readiness-rail';
import type { DeduplicationPayload } from '@/types';

const payload: DeduplicationPayload = {
    clusters: [],
    fresh: true,
    latest_run: {
        completed_at: '2026-05-30T10:00:00Z',
        duplicate_cluster_count: 1,
        duplicate_member_count: 1,
        duplicates_removed: 1,
        duration_ms: 12,
        fresh: true,
        id: 'run-1',
        input_count: 12,
        metadata: {},
        policy_stats: {},
        representative_count: 11,
        status: 'completed',
    },
    lock: {
        available: true,
        blocked_reason: null,
    },
    membership_hash: 'hash',
    selectedCluster: null,
    state: 'duplicates_found',
    summary: {
        draft_unique_works: 12,
        duplicate_clusters: 1,
        duplicates_removed: 1,
        raw_query_links: 18,
        representative_works: 11,
    },
};

describe('DedupReadinessRail', () => {
    it('summarizes the lock-ready representative corpus', () => {
        render(<DedupReadinessRail deduplication={payload} />);

        expect(screen.getByText('Draft works')).toBeVisible();
        expect(screen.getByText('18 query links')).toBeVisible();
        expect(screen.getByText('Representatives')).toBeVisible();
        expect(screen.getByText('Duplicate clusters')).toBeVisible();
        expect(screen.getByText('Ready')).toBeVisible();
    });

    it('warns when the latest run is stale', () => {
        render(
            <DedupReadinessRail
                deduplication={{
                    ...payload,
                    fresh: false,
                    lock: {
                        available: false,
                        blocked_reason:
                            'Draft corpus changed since the latest deduplication run.',
                    },
                    state: 'stale',
                }}
            />,
        );

        expect(screen.getByText('Deduplication is stale')).toBeVisible();
        expect(screen.getByText('Blocked')).toBeVisible();
    });

    it('uses source wording after the corpus is locked', () => {
        render(
            <DedupReadinessRail
                deduplication={{
                    ...payload,
                    lock: {
                        available: false,
                        blocked_reason: 'Corpus is already locked.',
                    },
                    state: 'locked',
                }}
            />,
        );

        expect(screen.getByText('Source works')).toBeVisible();
    });
});
