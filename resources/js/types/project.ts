import type { ReactNode } from 'react';

export type ReviewType =
    | 'systematic_review'
    | 'scoping_review'
    | 'thesis_review'
    | 'living_review'
    | 'evidence_map';

export type ProjectStatus =
    | 'draft'
    | 'ready_for_search'
    | 'searching'
    | 'draft_corpus'
    | 'locked_corpus'
    | 'screening'
    | 'adjudication'
    | 'exporting'
    | 'archived';

export type ProtocolStatus = 'draft' | 'complete' | 'locked' | 'amended';

export type ProjectRole = 'owner' | 'reviewer' | 'adjudicator' | 'viewer';

export type ProtocolReadinessItem = {
    id: string;
    label: string;
    complete: boolean;
    description?: string;
    required?: boolean;
    value?: ReactNode;
    action?: ReactNode;
};
