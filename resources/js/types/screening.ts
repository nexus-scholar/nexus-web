export type ScreeningBatchStatus =
    | 'draft'
    | 'active'
    | 'conflicts'
    | 'completed'
    | 'cancelled';

export type ScreeningAssignmentStatus =
    | 'pending'
    | 'in_progress'
    | 'decided'
    | 'conflict'
    | 'resolved';

export type ScreeningConflictStatus = 'open' | 'resolved';

export type ScreeningDecision = 'include' | 'needs_review' | 'exclude';

export type ScreeningDecisionPayload = {
    id: string;
    decision: ScreeningDecision;
    decision_label: string;
    decision_source: string | null;
    reason: string | null;
    decided_at: string | null;
    decided_by: {
        id: number | null;
        name: string;
    };
    evidence: string[];
    uncertainty: string[];
    exclusion_basis: string[];
};

export type ScreeningSnapshot = {
    id: string;
    locked_at: string | null;
    work_count: number;
    lock_reason: string | null;
    representative_snapshot: boolean;
    metadata: Record<string, unknown>;
};

export type ScreeningProtocol = {
    title: string;
    research_question: string | null;
    inclusion_criteria: string | null;
    exclusion_criteria: string | null;
    language_policy: string | null;
    min_reviewer_count?: number;
    ai_screening_policy?: string | null;
    full_text_policy?: string | null;
};

export type ScreeningCounts = {
    assignments: {
        total: number;
        pending: number;
        in_progress: number;
        decided: number;
        conflict: number;
        resolved: number;
    };
    decisions: Record<ScreeningDecision, number>;
    conflicts: {
        open: number;
        resolved: number;
    };
};

export type ScreeningBatch = {
    id: string;
    status: ScreeningBatchStatus;
    status_label: string;
    stage: string;
    stage_label: string;
    required_reviewer_count: number;
    criteria_hash: string;
    snapshot_id: string | null;
    started_at: string | null;
    completed_at: string | null;
    counts: ScreeningCounts;
    progress_percent: number;
};

export type ScreeningReviewer = {
    id: number;
    name: string;
    email: string;
    role: 'reviewer' | 'adjudicator';
    role_label: string;
};

export type ScreeningWorkload = {
    user_id: number;
    name: string;
    email: string;
    counts: Record<ScreeningAssignmentStatus | 'total', number>;
};

export type ScreeningConflict = {
    id: string;
    status: ScreeningConflictStatus;
    status_label: string;
    work_id: string;
    work: {
        title: string;
        abstract: string | null;
        year: number | null;
        venue_name: string | null;
    };
    source_decisions: ScreeningDecisionPayload[];
    resolved_decision: ScreeningDecisionPayload | null;
    resolution_reason: string | null;
    opened_at: string | null;
    resolved_at: string | null;
    resolve_url: string;
};

export type ScreeningAuditEvent = {
    id: string;
    event_type: string;
    label: string;
    reason: string | null;
    occurred_at: string | null;
};

export type ScreeningOverviewPayload = {
    snapshot: ScreeningSnapshot | null;
    protocol: ScreeningProtocol;
    batch: ScreeningBatch | null;
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

export type ScreeningQueueAssignment = {
    id: string;
    status: ScreeningAssignmentStatus;
    status_label: string;
    work_id: string;
    title: string;
    year: number | null;
    venue_name: string | null;
    decision: ScreeningDecision | null;
    decision_label: string | null;
    decided_at: string | null;
    href: string;
};

export type ScreeningSelectedAssignment = {
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

export type ScreeningQueuePayload = {
    batch: {
        id: string;
        status: ScreeningBatchStatus;
        status_label: string;
        stage_label: string;
        required_reviewer_count: number;
        counts: Partial<ScreeningCounts>;
    } | null;
    assignments: ScreeningQueueAssignment[];
    selectedAssignment: ScreeningSelectedAssignment | null;
    protocol: ScreeningProtocol;
};
