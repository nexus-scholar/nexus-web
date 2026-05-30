export type CorpusSource = 'draft' | 'locked';

export type CorpusMetrics = {
    unique_works: number;
    raw_query_links: number;
    search_queries: number;
    provider_coverage: number;
    providers: string[];
    year_range: {
        from: number | null;
        to: number | null;
    };
    missing_abstracts: number;
    missing_identifiers: number;
    retracted_records: number;
    duplicate_clusters: number;
};

export type CorpusFilters = {
    q: string | null;
    provider: string | null;
    search_query: string | null;
    year_from: number | null;
    year_to: number | null;
    identifier: string | null;
    missing_abstract: boolean;
    missing_identifier: boolean;
    retracted: boolean;
    duplicate_status: 'all' | 'in_cluster' | 'not_clustered';
    sort: CorpusSortKey;
    direction: CorpusSortDirection;
    work: string | null;
    per_page: number;
};

export type CorpusSortKey =
    | 'title'
    | 'year'
    | 'cited_by_count'
    | 'retrieved_at';

export type CorpusSortDirection = 'asc' | 'desc';

export type CorpusFilterOptions = {
    providers: string[];
    search_queries: Array<{
        id: string;
        label: string;
        query: string;
    }>;
    identifiers: string[];
    duplicate_statuses: Array<{
        value: CorpusFilters['duplicate_status'];
        label: string;
    }>;
};

export type CorpusRecord = {
    id: string;
    title: string;
    abstract: string | null;
    abstract_preview: string | null;
    year: number | null;
    venue_name: string | null;
    venue_type: string | null;
    url: string | null;
    language: string | null;
    cited_by_count: number;
    is_retracted: boolean;
    retrieved_at: string | null;
    authors: CorpusAuthor[];
    identifiers: CorpusIdentifier[];
    providers: CorpusProvider[];
    provenance: CorpusProvenance[];
    duplicate: CorpusDuplicate | null;
    metadata_flags: CorpusMetadataFlags;
    quality_label: string;
    counts: {
        authors: number;
        identifiers: number;
        providers: number;
        provenance: number;
    };
};

export type CorpusAuthor = {
    name: string;
    orcid: string | null;
    position: number;
    is_corresponding: boolean;
};

export type CorpusIdentifier = {
    namespace: string;
    value: string;
    is_primary: boolean;
};

export type CorpusProvider = {
    provider_alias: string;
    provider_work_id: string | null;
    first_seen_at: string | null;
    last_seen_at: string | null;
};

export type CorpusProvenance = {
    search_query_id: string | null;
    search_query_label: string;
    query_text: string | null;
    provider_alias: string | null;
    provider_work_id: string | null;
    rank: number | null;
    seen_at: string | null;
};

export type CorpusDuplicate = {
    cluster_id: string;
    cluster_size: number;
    strategy: string;
    is_representative: boolean;
    reason: string | null;
    confidence: number | null;
};

export type CorpusMetadataFlags = {
    missing_abstract: boolean;
    missing_identifier: boolean;
    retracted: boolean;
    in_duplicate_cluster: boolean;
};

export type CorpusSnapshot = {
    id: string;
    locked_at: string | null;
    work_count: number;
    created_by: string | null;
    lock_reason: string | null;
    metadata: Record<string, unknown>;
};

export type PaginatedCorpusRecords = {
    data: CorpusRecord[];
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
    };
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
};
