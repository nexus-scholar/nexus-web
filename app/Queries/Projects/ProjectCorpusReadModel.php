<?php

namespace App\Queries\Projects;

use App\Models\Project;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ProjectCorpusReadModel
{
    /**
     * @return array<string, mixed>
     */
    public function forProject(Project $project, CorpusFilters $filters): array
    {
        $snapshot = $this->activeSnapshot($project);
        $source = $snapshot ? 'locked' : 'draft';
        $query = $this->applySorting($this->corpusQuery($project, $snapshot, $filters), $filters);

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query
            ->paginate($filters->perPage)
            ->appends($filters->queryParameters());

        $pageRecords = $this->recordsForRows($project, $snapshot, $paginator->getCollection(), false);
        $selectedRecord = $this->selectedRecord($project, $snapshot, $filters);

        return [
            'source' => $source,
            'status_label' => $source === 'locked' ? 'Locked corpus' : 'Draft corpus',
            'snapshot' => $snapshot ? $this->snapshotPayload($snapshot) : null,
            'metrics' => $this->metrics($project, $snapshot),
            'filters' => $filters->toArray(),
            'filterOptions' => $this->filterOptions($project, $snapshot),
            'records' => [
                'data' => $pageRecords->values()->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'from' => $paginator->firstItem(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ],
                'links' => [
                    'first' => $paginator->url(1),
                    'last' => $paginator->url($paginator->lastPage()),
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
            ],
            'selectedRecord' => $selectedRecord,
        ];
    }

    private function activeSnapshot(Project $project): ?object
    {
        if (! $project->isLocked()) {
            return null;
        }

        return DB::table('corpus_snapshots')
            ->where('project_id', $project->id)
            ->orderByDesc('locked_at')
            ->orderByDesc('created_at')
            ->first();
    }

    private function corpusQuery(Project $project, ?object $snapshot, CorpusFilters $filters): Builder
    {
        $query = $this->baseCorpusQuery($project, $snapshot);

        if ($filters->search) {
            $term = '%'.strtolower($filters->search).'%';
            $query->where(function (Builder $query) use ($term): void {
                $query
                    ->whereRaw('lower(works.title) like ?', [$term])
                    ->orWhereRaw('lower(coalesce(works.abstract, \'\')) like ?', [$term]);
            });
        }

        if ($filters->provider) {
            $this->whereHasProvider($query, $project, $snapshot, $filters->provider);
        }

        if ($filters->searchQueryId) {
            $this->whereHasSearchQuery($query, $project, $snapshot, $filters->searchQueryId);
        }

        if ($filters->yearFrom !== null) {
            $query->where('works.year', '>=', $filters->yearFrom);
        }

        if ($filters->yearTo !== null) {
            $query->where('works.year', '<=', $filters->yearTo);
        }

        if ($filters->identifier) {
            $query->whereExists(function (Builder $query) use ($filters): void {
                $query
                    ->selectRaw('1')
                    ->from('work_external_ids as ids')
                    ->whereColumn('ids.work_id', 'works.id')
                    ->where('ids.namespace', $filters->identifier);
            });
        }

        if ($filters->missingAbstract) {
            $query->where(function (Builder $query): void {
                $query->whereNull('works.abstract')->orWhere('works.abstract', '');
            });
        }

        if ($filters->missingIdentifier) {
            $query->whereNotExists(function (Builder $query): void {
                $query
                    ->selectRaw('1')
                    ->from('work_external_ids as ids')
                    ->whereColumn('ids.work_id', 'works.id');
            });
        }

        if ($filters->retracted) {
            $query->where('works.is_retracted', true);
        }

        if ($filters->duplicateStatus === 'in_cluster') {
            $this->whereDuplicateCluster($query, $project);
        }

        if ($filters->duplicateStatus === 'not_clustered') {
            $this->whereNotDuplicateCluster($query, $project);
        }

        return $query;
    }

    private function applySorting(Builder $query, CorpusFilters $filters): Builder
    {
        $direction = $filters->direction === 'asc' ? 'asc' : 'desc';

        match ($filters->sort) {
            'title' => $query->orderBy('works.title', $direction),
            'cited_by_count' => $query->orderBy('works.cited_by_count', $direction),
            'retrieved_at' => $query->orderBy('works.retrieved_at', $direction),
            default => $query->orderBy('works.year', $direction),
        };

        if ($filters->sort !== 'title') {
            $query->orderBy('works.title');
        }

        return $query->orderBy('works.id');
    }

    private function baseCorpusQuery(Project $project, ?object $snapshot): Builder
    {
        $query = DB::table('scholarly_works as works')
            ->select([
                'works.id',
                'works.title',
                'works.abstract',
                'works.year',
                'works.venue_name',
                'works.venue_type',
                'works.url',
                'works.language',
                'works.cited_by_count',
                'works.is_retracted',
                'works.retrieved_at',
            ]);

        if ($snapshot) {
            return $query
                ->join('corpus_snapshot_works as snapshot_works', 'snapshot_works.work_id', '=', 'works.id')
                ->where('snapshot_works.snapshot_id', $snapshot->id);
        }

        return $query->joinSub($this->draftMembershipQuery($project), 'membership', function ($join): void {
            $join->on('membership.work_id', '=', 'works.id');
        });
    }

    private function draftMembershipQuery(Project $project): Builder
    {
        return DB::table('query_works')
            ->join('search_queries', 'search_queries.id', '=', 'query_works.search_query_id')
            ->where('search_queries.project_id', $project->id)
            ->distinct()
            ->select('query_works.work_id');
    }

    private function whereHasProvider(Builder $query, Project $project, ?object $snapshot, string $provider): void
    {
        if ($snapshot) {
            $query->where('snapshot_works.provider_aliases', 'like', '%"'.$provider.'"%');

            return;
        }

        $query->whereExists(function (Builder $query) use ($project, $provider): void {
            $query
                ->selectRaw('1')
                ->from('query_works as provider_query_works')
                ->join('search_queries as provider_search_queries', 'provider_search_queries.id', '=', 'provider_query_works.search_query_id')
                ->whereColumn('provider_query_works.work_id', 'works.id')
                ->where('provider_search_queries.project_id', $project->id)
                ->where('provider_query_works.provider_alias', $provider);
        });
    }

    private function whereHasSearchQuery(Builder $query, Project $project, ?object $snapshot, string $searchQueryId): void
    {
        if ($snapshot) {
            $query->where('snapshot_works.search_query_ids', 'like', '%"'.$searchQueryId.'"%');

            return;
        }

        $query->whereExists(function (Builder $query) use ($project, $searchQueryId): void {
            $query
                ->selectRaw('1')
                ->from('query_works as search_query_works')
                ->join('search_queries as selected_search_queries', 'selected_search_queries.id', '=', 'search_query_works.search_query_id')
                ->whereColumn('search_query_works.work_id', 'works.id')
                ->where('selected_search_queries.project_id', $project->id)
                ->where('search_query_works.search_query_id', $searchQueryId);
        });
    }

    private function whereDuplicateCluster(Builder $query, Project $project): void
    {
        $query->whereExists(function (Builder $query) use ($project): void {
            $query
                ->selectRaw('1')
                ->from('cluster_members as cluster_members_filter')
                ->join('dedup_clusters as clusters_filter', 'clusters_filter.id', '=', 'cluster_members_filter.cluster_id')
                ->whereColumn('cluster_members_filter.work_id', 'works.id')
                ->where('clusters_filter.project_id', $project->id);
        });
    }

    private function whereNotDuplicateCluster(Builder $query, Project $project): void
    {
        $query->whereNotExists(function (Builder $query) use ($project): void {
            $query
                ->selectRaw('1')
                ->from('cluster_members as cluster_members_filter')
                ->join('dedup_clusters as clusters_filter', 'clusters_filter.id', '=', 'cluster_members_filter.cluster_id')
                ->whereColumn('cluster_members_filter.work_id', 'works.id')
                ->where('clusters_filter.project_id', $project->id);
        });
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function recordsForRows(Project $project, ?object $snapshot, Collection $rows, bool $includeDetail): Collection
    {
        $workIds = $rows->pluck('id')->values()->all();

        if ($workIds === []) {
            return collect();
        }

        $authors = $includeDetail ? $this->authorsByWork($workIds) : [];
        $authorCounts = $includeDetail ? [] : $this->authorCountsByWork($workIds);
        $identifiers = $this->identifiersByWork($workIds);
        $providers = $this->providersByWork($workIds);
        $duplicates = $this->duplicatesByWork($project, $workIds);
        $queryLabels = $this->queryLabels($project);
        $provenance = $includeDetail ? ($snapshot
            ? $this->lockedProvenanceByWork($snapshot, $workIds, $queryLabels)
            : $this->draftProvenanceByWork($project, $workIds, $queryLabels)) : [];
        $provenanceCounts = $includeDetail ? [] : $this->provenanceCountsByWork($project, $snapshot, $workIds);

        return $rows->map(fn (object $row): array => $this->workPayload(
            $row,
            $authors[$row->id] ?? [],
            $identifiers[$row->id] ?? [],
            $providers[$row->id] ?? [],
            $provenance[$row->id] ?? [],
            $duplicates[$row->id] ?? null,
            $includeDetail,
            [
                'authors' => $authorCounts[$row->id] ?? null,
                'provenance' => $provenanceCounts[$row->id] ?? null,
            ],
        ));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $pageRecords
     * @return array<string, mixed>|null
     */
    private function selectedRecord(
        Project $project,
        ?object $snapshot,
        CorpusFilters $filters,
    ): ?array {
        if (! $filters->work) {
            return null;
        }

        $row = $this->corpusQuery($project, $snapshot, $filters)
            ->where('works.id', $filters->work)
            ->first();

        return $row ? $this->recordsForRows($project, $snapshot, collect([$row]), true)->first() : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function metrics(Project $project, ?object $snapshot): array
    {
        $base = $this->baseCorpusQuery($project, $snapshot);
        $yearRange = (clone $base)
            ->whereNotNull('works.year')
            ->getQuery()
            ->selectRaw('MIN(works.year) as min_year, MAX(works.year) as max_year')
            ->first();

        $providers = $this->providerOptions($project, $snapshot);

        return [
            'unique_works' => (clone $base)->count(),
            'raw_query_links' => $snapshot ? $this->lockedRawQueryLinks($snapshot) : $this->draftRawQueryLinks($project),
            'search_queries' => $this->searchQueryCount($project, $snapshot),
            'provider_coverage' => count($providers),
            'providers' => $providers,
            'year_range' => [
                'from' => $yearRange?->min_year ? (int) $yearRange->min_year : null,
                'to' => $yearRange?->max_year ? (int) $yearRange->max_year : null,
            ],
            'missing_abstracts' => (clone $base)
                ->where(function (Builder $query): void {
                    $query->whereNull('works.abstract')->orWhere('works.abstract', '');
                })
                ->count(),
            'missing_identifiers' => (clone $base)
                ->whereNotExists(function (Builder $query): void {
                    $query
                        ->selectRaw('1')
                        ->from('work_external_ids as ids')
                        ->whereColumn('ids.work_id', 'works.id');
                })
                ->count(),

            'retracted_records' => (clone $base)
                ->where('works.is_retracted', true)
                ->count(),

            'duplicate_clusters' => DB::table('dedup_clusters')
                ->where('project_id', $project->id)
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(Project $project, ?object $snapshot): array
    {
        return [
            'providers' => $this->providerOptions($project, $snapshot),
            'search_queries' => $this->searchQueryOptions($project),
            'identifiers' => $this->identifierOptions($project, $snapshot),
            'duplicate_statuses' => [
                ['value' => 'all', 'label' => 'All records'],
                ['value' => 'in_cluster', 'label' => 'In duplicate cluster'],
                ['value' => 'not_clustered', 'label' => 'Not clustered'],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function providerOptions(Project $project, ?object $snapshot): array
    {
        if ($snapshot) {
            return DB::table('corpus_snapshot_works')
                ->where('snapshot_id', $snapshot->id)
                ->pluck('provider_aliases')
                ->flatMap(fn (mixed $aliases): array => $this->decodeList($aliases))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        return DB::table('query_works')
            ->join('search_queries', 'search_queries.id', '=', 'query_works.search_query_id')
            ->where('search_queries.project_id', $project->id)
            ->whereNotNull('query_works.provider_alias')
            ->distinct()
            ->orderBy('query_works.provider_alias')
            ->pluck('query_works.provider_alias')
            ->all();
    }

    /**
     * @return list<array{id: string, label: string, query: string}>
     */
    private function searchQueryOptions(Project $project): array
    {
        return DB::table('search_queries as queries')
            ->leftJoin('project_search_run_items as items', 'items.core_search_query_id', '=', 'queries.id')
            ->where('queries.project_id', $project->id)
            ->orderBy('queries.created_at')
            ->select(['queries.id', 'queries.query_text', 'items.label'])
            ->get()
            ->unique('id')
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'label' => (string) ($row->label ?: str($row->query_text)->limit(80)),
                'query' => (string) $row->query_text,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function identifierOptions(Project $project, ?object $snapshot): array
    {
        return $this->baseCorpusQuery($project, $snapshot)
            ->join('work_external_ids as ids', 'ids.work_id', '=', 'works.id')
            ->distinct()
            ->orderBy('ids.namespace')
            ->select('ids.namespace')
            ->get()
            ->pluck('namespace')
            ->all();
    }

    /**
     * @param  list<string>  $workIds
     * @return array<string, int>
     */
    private function authorCountsByWork(array $workIds): array
    {
        return DB::table('work_authors')
            ->whereIn('work_id', $workIds)
            ->select('work_id')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('work_id')
            ->pluck('aggregate', 'work_id')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @param  list<string>  $workIds
     * @return array<string, int>
     */
    private function provenanceCountsByWork(Project $project, ?object $snapshot, array $workIds): array
    {
        if ($snapshot) {
            return DB::table('corpus_snapshot_works')
                ->where('snapshot_id', $snapshot->id)
                ->whereIn('work_id', $workIds)
                ->select(['work_id', 'provider_aliases', 'provenance'])
                ->get()
                ->mapWithKeys(fn (object $row): array => [
                    $row->work_id => count($this->decodeList($row->provenance))
                        ?: count($this->decodeList($row->provider_aliases)),
                ])
                ->all();
        }

        return DB::table('query_works')
            ->join('search_queries', 'search_queries.id', '=', 'query_works.search_query_id')
            ->where('search_queries.project_id', $project->id)
            ->whereIn('query_works.work_id', $workIds)
            ->select('query_works.work_id')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('query_works.work_id')
            ->pluck('aggregate', 'query_works.work_id')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @param  list<string>  $workIds
     * @return array<string, list<array<string, mixed>>>
     */
    private function authorsByWork(array $workIds): array
    {
        return DB::table('work_authors as work_authors')
            ->join('authors', 'authors.id', '=', 'work_authors.author_id')
            ->whereIn('work_authors.work_id', $workIds)
            ->orderBy('work_authors.position')
            ->select([
                'work_authors.work_id',
                'authors.full_name',
                'authors.orcid',
                'work_authors.position',
                'work_authors.is_corresponding',
            ])
            ->get()
            ->groupBy('work_id')
            ->map(fn (Collection $rows): array => $rows
                ->map(fn (object $row): array => [
                    'name' => (string) $row->full_name,
                    'orcid' => $row->orcid ? (string) $row->orcid : null,
                    'position' => (int) $row->position,
                    'is_corresponding' => (bool) $row->is_corresponding,
                ])
                ->values()
                ->all())
            ->all();
    }

    /**
     * @param  list<string>  $workIds
     * @return array<string, list<array<string, mixed>>>
     */
    private function identifiersByWork(array $workIds): array
    {
        return DB::table('work_external_ids')
            ->whereIn('work_id', $workIds)
            ->orderByDesc('is_primary')
            ->orderBy('namespace')
            ->select(['work_id', 'namespace', 'value', 'is_primary'])
            ->get()
            ->groupBy('work_id')
            ->map(fn (Collection $rows): array => $rows
                ->map(fn (object $row): array => [
                    'namespace' => (string) $row->namespace,
                    'value' => (string) $row->value,
                    'is_primary' => (bool) $row->is_primary,
                ])
                ->values()
                ->all())
            ->all();
    }

    /**
     * @param  list<string>  $workIds
     * @return array<string, list<array<string, mixed>>>
     */
    private function providersByWork(array $workIds): array
    {
        return DB::table('work_providers')
            ->whereIn('work_id', $workIds)
            ->orderBy('provider_alias')
            ->select(['work_id', 'provider_alias', 'provider_work_id', 'first_seen_at', 'last_seen_at'])
            ->get()
            ->groupBy('work_id')
            ->map(fn (Collection $rows): array => $rows
                ->map(fn (object $row): array => [
                    'provider_alias' => (string) $row->provider_alias,
                    'provider_work_id' => $row->provider_work_id ? (string) $row->provider_work_id : null,
                    'first_seen_at' => $this->dateString($row->first_seen_at),
                    'last_seen_at' => $this->dateString($row->last_seen_at),
                ])
                ->values()
                ->all())
            ->all();
    }

    /**
     * @param  list<string>  $workIds
     * @return array<string, list<array<string, mixed>>>
     */
    private function draftProvenanceByWork(Project $project, array $workIds, array $queryLabels): array
    {
        return DB::table('query_works')
            ->join('search_queries', 'search_queries.id', '=', 'query_works.search_query_id')
            ->where('search_queries.project_id', $project->id)
            ->whereIn('query_works.work_id', $workIds)
            ->orderBy('query_works.seen_at')
            ->orderBy('query_works.rank')
            ->select([
                'query_works.work_id',
                'query_works.search_query_id',
                'query_works.provider_alias',
                'query_works.provider_work_id',
                'query_works.rank',
                'query_works.seen_at',
                'search_queries.query_text',
            ])
            ->get()
            ->groupBy('work_id')
            ->map(fn (Collection $rows): array => $rows
                ->map(fn (object $row): array => [
                    'search_query_id' => (string) $row->search_query_id,
                    'search_query_label' => $queryLabels[$row->search_query_id] ?? str($row->query_text)->limit(80)->toString(),
                    'query_text' => (string) $row->query_text,
                    'provider_alias' => $row->provider_alias ? (string) $row->provider_alias : null,
                    'provider_work_id' => $row->provider_work_id ? (string) $row->provider_work_id : null,
                    'rank' => $row->rank === null ? null : (int) $row->rank,
                    'seen_at' => $this->dateString($row->seen_at),
                ])
                ->values()
                ->all())
            ->all();
    }

    /**
     * @param  list<string>  $workIds
     * @return array<string, list<array<string, mixed>>>
     */
    private function lockedProvenanceByWork(object $snapshot, array $workIds, array $queryLabels): array
    {
        return DB::table('corpus_snapshot_works')
            ->where('snapshot_id', $snapshot->id)
            ->whereIn('work_id', $workIds)
            ->select(['work_id', 'search_query_ids', 'provider_aliases', 'provenance', 'included_at'])
            ->get()
            ->mapWithKeys(function (object $row) use ($queryLabels): array {
                $provenance = collect($this->decodeList($row->provenance))
                    ->map(fn (mixed $item): array => is_array($item) ? [
                        'search_query_id' => (string) ($item['search_query_id'] ?? ''),
                        'search_query_label' => $queryLabels[$item['search_query_id'] ?? ''] ?? ($item['query_label'] ?? 'Snapshot query'),
                        'query_text' => $item['query_text'] ?? null,
                        'provider_alias' => $item['provider_alias'] ?? null,
                        'provider_work_id' => $item['provider_work_id'] ?? null,
                        'rank' => isset($item['rank']) ? (int) $item['rank'] : null,
                        'seen_at' => $item['seen_at'] ?? $this->dateString($row->included_at),
                    ] : [])
                    ->filter(fn (array $item): bool => $item !== [])
                    ->values()
                    ->all();

                if ($provenance === []) {
                    $searchQueryIds = $this->decodeList($row->search_query_ids);
                    $providerAliases = $this->decodeList($row->provider_aliases);

                    $provenance = collect($providerAliases ?: [null])
                        ->map(fn (?string $provider): array => [
                            'search_query_id' => $searchQueryIds[0] ?? null,
                            'search_query_label' => $queryLabels[$searchQueryIds[0] ?? ''] ?? 'Snapshot query',
                            'query_text' => null,
                            'provider_alias' => $provider,
                            'provider_work_id' => null,
                            'rank' => null,
                            'seen_at' => $this->dateString($row->included_at),
                        ])
                        ->values()
                        ->all();
                }

                return [(string) $row->work_id => $provenance];
            })
            ->all();
    }

    /**
     * @param  list<string>  $workIds
     * @return array<string, array<string, mixed>>
     */
    private function duplicatesByWork(Project $project, array $workIds): array
    {
        return DB::table('cluster_members as members')
            ->join('dedup_clusters as clusters', 'clusters.id', '=', 'members.cluster_id')
            ->where('clusters.project_id', $project->id)
            ->whereIn('members.work_id', $workIds)
            ->select([
                'members.work_id',
                'members.cluster_id',
                'members.is_representative',
                'members.reason',
                'members.confidence as member_confidence',
                'clusters.cluster_size',
                'clusters.strategy',
                'clusters.confidence as cluster_confidence',
            ])
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (string) $row->work_id => [
                    'cluster_id' => (string) $row->cluster_id,
                    'cluster_size' => (int) $row->cluster_size,
                    'strategy' => (string) $row->strategy,
                    'is_representative' => (bool) $row->is_representative,
                    'reason' => $row->reason ? (string) $row->reason : null,
                    'confidence' => $row->member_confidence !== null
                        ? (float) $row->member_confidence
                        : ($row->cluster_confidence !== null ? (float) $row->cluster_confidence : null),
                ],
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function queryLabels(Project $project): array
    {
        return collect($this->searchQueryOptions($project))
            ->mapWithKeys(fn (array $query): array => [$query['id'] => $query['label']])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $authors
     * @param  list<array<string, mixed>>  $identifiers
     * @param  list<array<string, mixed>>  $providers
     * @param  list<array<string, mixed>>  $provenance
     * @param  array<string, mixed>|null  $duplicate
     * @return array<string, mixed>
     */
    private function workPayload(
        object $row,
        array $authors,
        array $identifiers,
        array $providers,
        array $provenance,
        ?array $duplicate,
        bool $includeDetail,
        array $countOverrides = [],
    ): array {
        $missingAbstract = blank($row->abstract);
        $missingIdentifier = $identifiers === [];
        $isRetracted = (bool) $row->is_retracted;

        return [
            'id' => (string) $row->id,
            'title' => (string) $row->title,
            'abstract' => $includeDetail && $row->abstract ? (string) $row->abstract : null,
            'abstract_preview' => $row->abstract ? str($row->abstract)->limit(260)->toString() : null,
            'year' => $row->year === null ? null : (int) $row->year,
            'venue_name' => $row->venue_name ? (string) $row->venue_name : null,
            'venue_type' => $row->venue_type ? (string) $row->venue_type : null,
            'url' => $row->url ? (string) $row->url : null,
            'language' => $row->language ? (string) $row->language : null,
            'cited_by_count' => (int) $row->cited_by_count,
            'is_retracted' => $isRetracted,
            'retrieved_at' => $this->dateString($row->retrieved_at),
            'authors' => $authors,
            'identifiers' => $identifiers,
            'providers' => $providers,
            'provenance' => $provenance,
            'duplicate' => $duplicate,
            'metadata_flags' => [
                'missing_abstract' => $missingAbstract,
                'missing_identifier' => $missingIdentifier,
                'retracted' => $isRetracted,
                'in_duplicate_cluster' => $duplicate !== null,
            ],
            'quality_label' => match (true) {
                $isRetracted => 'Retracted',
                $missingAbstract && $missingIdentifier => 'Needs metadata',
                $missingAbstract => 'Missing abstract',
                $missingIdentifier => 'Missing identifier',
                $duplicate !== null => 'Duplicate candidate',
                default => 'Ready',
            },
            'counts' => [
                'authors' => $countOverrides['authors'] ?? count($authors),
                'identifiers' => count($identifiers),
                'providers' => count($providers),
                'provenance' => $countOverrides['provenance'] ?? count($provenance),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotPayload(object $snapshot): array
    {
        return [
            'id' => (string) $snapshot->id,
            'locked_at' => $this->dateString($snapshot->locked_at),
            'work_count' => (int) $snapshot->work_count,
            'created_by' => $snapshot->created_by ? (string) $snapshot->created_by : null,
            'lock_reason' => $snapshot->lock_reason ? (string) $snapshot->lock_reason : null,
            'metadata' => $this->decodeObject($snapshot->metadata),
        ];
    }

    private function draftRawQueryLinks(Project $project): int
    {
        return DB::table('query_works')
            ->join('search_queries', 'search_queries.id', '=', 'query_works.search_query_id')
            ->where('search_queries.project_id', $project->id)
            ->count();
    }

    private function lockedRawQueryLinks(object $snapshot): int
    {
        return DB::table('corpus_snapshot_works')
            ->where('snapshot_id', $snapshot->id)
            ->pluck('provenance')
            ->sum(fn (mixed $provenance): int => max(1, count($this->decodeList($provenance))));
    }

    private function searchQueryCount(Project $project, ?object $snapshot): int
    {
        if ($snapshot) {
            return DB::table('corpus_snapshot_works')
                ->where('snapshot_id', $snapshot->id)
                ->pluck('search_query_ids')
                ->flatMap(fn (mixed $ids): array => $this->decodeList($ids))
                ->unique()
                ->count();
        }

        return DB::table('search_queries')
            ->where('project_id', $project->id)
            ->whereExists(function (Builder $query): void {
                $query
                    ->selectRaw('1')
                    ->from('query_works')
                    ->whereColumn('query_works.search_query_id', 'search_queries.id');
            })
            ->count();
    }

    /**
     * @return list<mixed>
     */
    private function decodeList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeObject(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function dateString(mixed $value): ?string
    {
        return $value ? (string) $value : null;
    }
}
