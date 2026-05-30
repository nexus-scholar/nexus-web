<?php

namespace App\Actions\Projects;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

class ProjectCorpusMembershipHasher
{
    /**
     * @return array{hash: string, unique_work_ids: list<string>, raw_query_links: int}
     */
    public function handle(Project $project): array
    {
        $rows = DB::table('query_works')
            ->join('search_queries', 'search_queries.id', '=', 'query_works.search_query_id')
            ->where('search_queries.project_id', $project->id)
            ->orderBy('query_works.search_query_id')
            ->orderBy('query_works.work_id')
            ->orderBy('query_works.provider_alias')
            ->orderBy('query_works.provider_work_id')
            ->orderBy('query_works.rank')
            ->select([
                'query_works.search_query_id',
                'query_works.work_id',
                'query_works.provider_alias',
                'query_works.provider_work_id',
                'query_works.rank',
            ])
            ->get();

        $uniqueWorkIds = $rows
            ->pluck('work_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        $fingerprint = [
            'query_links' => $rows
                ->map(fn (object $row): array => [
                    'search_query_id' => (string) $row->search_query_id,
                    'work_id' => (string) $row->work_id,
                    'provider_alias' => $row->provider_alias ? (string) $row->provider_alias : null,
                    'provider_work_id' => $row->provider_work_id ? (string) $row->provider_work_id : null,
                    'rank' => $row->rank === null ? null : (int) $row->rank,
                ])
                ->values()
                ->all(),
            'works' => $this->workFingerprints($uniqueWorkIds),
        ];

        return [
            'hash' => hash('sha256', json_encode($fingerprint, JSON_THROW_ON_ERROR)),
            'unique_work_ids' => $uniqueWorkIds,
            'raw_query_links' => $rows->count(),
        ];
    }

    /**
     * @param  list<string>  $workIds
     * @return list<array<string, mixed>>
     */
    private function workFingerprints(array $workIds): array
    {
        if ($workIds === []) {
            return [];
        }

        $works = DB::table('scholarly_works')
            ->whereIn('id', $workIds)
            ->orderBy('id')
            ->select([
                'id',
                'title',
                'abstract',
                'year',
                'venue_name',
                'venue_issn',
                'venue_type',
                'url',
                'language',
                'cited_by_count',
                'is_retracted',
            ])
            ->get()
            ->keyBy('id');

        $identifiers = DB::table('work_external_ids')
            ->whereIn('work_id', $workIds)
            ->orderBy('work_id')
            ->orderBy('namespace')
            ->orderBy('value')
            ->select(['work_id', 'namespace', 'value', 'is_primary'])
            ->get()
            ->groupBy('work_id')
            ->map(fn ($rows): array => $rows
                ->map(fn (object $row): array => [
                    'namespace' => (string) $row->namespace,
                    'value' => (string) $row->value,
                    'is_primary' => (bool) $row->is_primary,
                ])
                ->values()
                ->all());

        $authors = DB::table('work_authors')
            ->whereIn('work_id', $workIds)
            ->orderBy('work_id')
            ->orderBy('position')
            ->select(['work_id', 'author_id', 'position', 'is_corresponding'])
            ->get()
            ->groupBy('work_id')
            ->map(fn ($rows): array => $rows
                ->map(fn (object $row): array => [
                    'author_id' => (string) $row->author_id,
                    'position' => (int) $row->position,
                    'is_corresponding' => (bool) $row->is_corresponding,
                ])
                ->values()
                ->all());

        return collect($workIds)
            ->sort()
            ->values()
            ->map(function (string $workId) use ($authors, $identifiers, $works): array {
                $work = $works[$workId] ?? null;

                return [
                    'id' => $workId,
                    'exists' => $work !== null,
                    'title' => $work ? (string) $work->title : null,
                    'abstract_hash' => $work && $work->abstract !== null
                        ? hash('sha256', (string) $work->abstract)
                        : null,
                    'year' => $work && $work->year !== null ? (int) $work->year : null,
                    'venue_name' => $work && $work->venue_name !== null ? (string) $work->venue_name : null,
                    'venue_issn' => $work && $work->venue_issn !== null ? (string) $work->venue_issn : null,
                    'venue_type' => $work && $work->venue_type !== null ? (string) $work->venue_type : null,
                    'url' => $work && $work->url !== null ? (string) $work->url : null,
                    'language' => $work && $work->language !== null ? (string) $work->language : null,
                    'cited_by_count' => $work ? (int) $work->cited_by_count : null,
                    'is_retracted' => $work ? (bool) $work->is_retracted : null,
                    'identifiers' => $identifiers[$workId] ?? [],
                    'authors' => $authors[$workId] ?? [],
                ];
            })
            ->all();
    }
}
