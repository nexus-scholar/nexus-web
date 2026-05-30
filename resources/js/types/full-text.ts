import type { ScreeningDecision } from './screening';

export type FullTextBatchStatus =
    | 'queued'
    | 'running'
    | 'completed'
    | 'completed_with_failures'
    | 'failed'
    | 'cancelled';

export type FullTextItemStatus =
    | 'not_started'
    | 'queued'
    | 'running'
    | 'success'
    | 'failed'
    | 'skipped'
    | 'manual_needed';

export type FullTextWork = {
    id: string;
    title: string;
    abstract: string | null;
    year: number | null;
    venue_name: string | null;
    venue_type: string | null;
    language: string | null;
    cited_by_count: number;
    is_retracted: boolean;
    identifiers: Array<{
        namespace: string;
        value: string;
        is_primary: boolean;
    }>;
    providers: Array<{
        provider_alias: string;
        provider_work_id: string | null;
    }>;
};

export type FullTextSourceAttempt = {
    id: string;
    source_alias: string | null;
    source_url: string | null;
    status: 'success' | 'failure' | 'skipped';
    status_label: string;
    http_status: number | null;
    file_path: string | null;
    duration_ms: number | null;
    error_message: string | null;
    attempted_at: string | null;
    metadata: Record<string, unknown> | null;
};

export type FullTextItem = {
    id: string;
    work_id: string;
    screening_decision: ScreeningDecision;
    screening_decision_label: string;
    screening_reason: string | null;
    status: FullTextItemStatus;
    status_label: string;
    source_alias: string | null;
    artifact_type: string | null;
    artifact_path: string | null;
    http_status: number | null;
    error_message: string | null;
    metadata: Record<string, unknown> | null;
    download_url: string | null;
    started_at: string | null;
    completed_at: string | null;
    work: FullTextWork;
    source_attempts: FullTextSourceAttempt[];
};

export type FullTextBatch = {
    id: string;
    status: FullTextBatchStatus;
    status_label: string;
    screening_batch_id: string;
    snapshot_id: string;
    candidate_count: number;
    success_count: number;
    failed_count: number;
    skipped_count: number;
    manual_needed_count: number;
    progress_percent: number;
    destination_folder: string;
    started_at: string | null;
    completed_at: string | null;
};

export type FullTextOverviewPayload = {
    readiness: {
        ready: boolean;
        blockers: string[];
        counts: {
            include: number;
            needs_review: number;
            candidate_count: number;
            excluded: number;
            total_works: number;
        };
        screening_batch: {
            id: string;
            completed_at: string | null;
            status: string;
            status_label: string;
        } | null;
        snapshot: {
            id: string;
            locked_at: string | null;
            work_count: number;
        } | null;
    };
    protocol: {
        full_text_policy: string;
        full_text_policy_label: string;
    };
    sourcePolicy: Array<{
        alias: string;
        label: string;
        enabled: boolean;
    }>;
    batch: FullTextBatch | null;
    items: FullTextItem[];
    selectedItem: FullTextItem | null;
    recentAuditEvents: Array<{
        id: string;
        event_type: string;
        label: string;
        reason: string | null;
        occurred_at: string | null;
    }>;
    actor: {
        id: number;
        project_role: string | null;
    };
};
