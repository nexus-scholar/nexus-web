import type {
    ScreeningAssignmentStatus,
    ScreeningAuditEvent,
    ScreeningBatchStatus,
    ScreeningConflict,
    ScreeningCounts,
    ScreeningDecision,
    ScreeningProtocol,
    ScreeningReviewer,
    ScreeningWorkload,
} from './screening';

export type FullTextScreeningReadiness = {
    ready: boolean;
    blockers: string[];
    counts: {
        title_abstract_candidates: number;
        screenable: number;
        include: number;
        needs_review: number;
        excluded_at_title_abstract: number;
    };
    follow_up: {
        failed: number;
        skipped: number;
        manual_needed: number;
        missing_item: number;
        total: number;
    };
    title_abstract_batch: {
        id: string;
        completed_at: string | null;
        status: string;
        status_label: string;
    } | null;
    full_text_batch: {
        id: string;
        status: string;
        status_label: string;
        candidate_count: number;
        success_count: number;
        failed_count: number;
        skipped_count: number;
        manual_needed_count: number;
        completed_at: string | null;
    } | null;
    snapshot: {
        id: string;
        locked_at: string | null;
        work_count: number;
    } | null;
};

export type FullTextScreeningBatch = {
    id: string;
    status: ScreeningBatchStatus;
    status_label: string;
    stage: 'full_text';
    stage_label: string;
    required_reviewer_count: number;
    criteria_hash: string;
    snapshot_id: string | null;
    source_full_text_batch_id: string | null;
    started_at: string | null;
    completed_at: string | null;
    counts: ScreeningCounts;
    progress_percent: number;
};

export type FullTextScreeningOverviewPayload = {
    readiness: FullTextScreeningReadiness;
    protocol: ScreeningProtocol & {
        full_text_policy_label: string;
    };
    batch: FullTextScreeningBatch | null;
    setup: {
        default_required_reviewer_count: number;
        available_reviewers: ScreeningReviewer[];
    };
    workload: ScreeningWorkload[];
    conflicts: ScreeningConflict[];
    selectedConflict: ScreeningConflict | null;
    recentAuditEvents: ScreeningAuditEvent[];
    nextReviewerAssignmentUrl: string | null;
    actor: {
        id: number;
        project_role: string | null;
    };
};

export type FullTextScreeningQueueAssignment = {
    id: string;
    status: ScreeningAssignmentStatus;
    status_label: string;
    work_id: string;
    title: string;
    year: number | null;
    venue_name: string | null;
    decision: ScreeningDecision | null;
    decision_label: string | null;
    source_alias: string | null;
    artifact_type: string | null;
    decided_at: string | null;
    href: string;
};

export type FullTextScreeningSelectedAssignment = {
    id: string;
    status: ScreeningAssignmentStatus;
    status_label: string;
    decision_url: string;
    can_record_decision: boolean;
    decision: {
        id: string;
        decision: ScreeningDecision;
        decision_label: string;
        reason: string | null;
        evidence: string[];
        uncertainty: string[];
        exclusion_basis: string[];
        decided_at: string | null;
    } | null;
    title_abstract_handoff: {
        decision: ScreeningDecision | null;
        decision_label: string | null;
        reason: string | null;
    };
    artifact: {
        id: string;
        status: string;
        status_label: string;
        source_alias: string | null;
        artifact_type: string | null;
        artifact_path: string | null;
        download_url: string | null;
        completed_at: string | null;
        metadata: Record<string, unknown>;
    } | null;
    work: {
        id: string;
        title: string;
        abstract: string | null;
        year: number | null;
        venue_name: string | null;
        venue_type: string | null;
        language: string | null;
        cited_by_count: number;
        identifiers: Array<{
            namespace: string;
            value: string;
            is_primary: boolean;
        }>;
        providers: Array<{
            provider_alias: string;
            provider_work_id: string | null;
        }>;
        provenance: Array<{
            query_label: string;
            provider_alias: string | null;
            provider_work_id: string | null;
            rank: number | null;
            seen_at: string | null;
        }>;
    };
};

export type FullTextScreeningQueuePayload = {
    batch: {
        id: string;
        status: ScreeningBatchStatus;
        status_label: string;
        stage_label: string;
        required_reviewer_count: number;
        counts: Partial<ScreeningCounts>;
    } | null;
    assignments: FullTextScreeningQueueAssignment[];
    selectedAssignment: FullTextScreeningSelectedAssignment | null;
    protocol: ScreeningProtocol;
};
