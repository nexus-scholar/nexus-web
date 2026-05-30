export type DeduplicationState =
    | 'not_run'
    | 'stale'
    | 'duplicates_found'
    | 'clear'
    | 'locked';

export type DeduplicationSummary = {
    draft_unique_works: number;
    raw_query_links: number;
    representative_works: number;
    duplicates_removed: number;
    duplicate_clusters: number;
};

export type DeduplicationRun = {
    id: string;
    status: string;
    fresh: boolean;
    input_count: number;
    representative_count: number;
    duplicate_cluster_count: number;
    duplicate_member_count: number;
    duplicates_removed: number;
    duration_ms: number | null;
    policy_stats: Record<string, number>;
    metadata: Record<string, unknown>;
    completed_at: string | null;
};

export type DeduplicationCluster = {
    id: string;
    strategy: string;
    representative_work_id: string | null;
    representative_title: string | null;
    cluster_size: number;
    confidence: number | null;
    reasons: string[];
    is_locked: boolean;
    created_at: string | null;
};

export type DeduplicationClusterDetail = {
    id: string;
    strategy: string;
    representative_work_id: string | null;
    cluster_size: number;
    confidence: number | null;
    metadata: Record<string, unknown>;
    members: DeduplicationClusterMember[];
    evidence: DeduplicationEvidence[];
};

export type DeduplicationClusterMember = {
    work_id: string;
    title: string;
    year: number | null;
    venue_name: string | null;
    cited_by_count: number;
    is_representative: boolean;
    reason: string | null;
    confidence: number | null;
    providers: string[];
};

export type DeduplicationEvidence = {
    work_ids?: string[];
    reason?: string;
    confidence?: number;
    source?: string;
    namespace?: string;
    value?: string;
    primary_id?: string;
    secondary_id?: string;
};

export type DeduplicationPayload = {
    state: DeduplicationState;
    fresh: boolean;
    membership_hash: string;
    summary: DeduplicationSummary;
    latest_run: DeduplicationRun | null;
    clusters: DeduplicationCluster[];
    selectedCluster: DeduplicationClusterDetail | null;
    lock: {
        available: boolean;
        blocked_reason: string | null;
    };
};
