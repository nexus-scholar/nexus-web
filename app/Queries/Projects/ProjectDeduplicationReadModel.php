<?php

namespace App\Queries\Projects;

use App\Actions\Projects\ProjectCorpusMembershipHasher;
use App\Models\Project;
use App\Models\ProjectCorpusDedupRun;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectDeduplicationReadModel
{
    public function __construct(
        private readonly ProjectCorpusMembershipHasher $membership,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forProject(Project $project, ?string $selectedClusterId = null): array
    {
        $digest = $this->membership->handle($project);
        $latestRun = ProjectCorpusDedupRun::query()
            ->where('project_id', $project->id)
            ->latest()
            ->first();

        $fresh = $latestRun !== null && hash_equals($latestRun->membership_hash, $digest['hash']);
        $clusters = $this->clusters($project);
        $selectedCluster = $selectedClusterId
            ? $this->clusterDetail($project, $selectedClusterId)
            : null;

        return [
            'state' => $this->state($project, $latestRun, $fresh, $clusters->count()),
            'fresh' => $fresh,
            'membership_hash' => $digest['hash'],
            'summary' => [
                'draft_unique_works' => count($digest['unique_work_ids']),
                'raw_query_links' => $digest['raw_query_links'],
                'representative_works' => $fresh && $latestRun
                    ? $latestRun->representative_count
                    : count($digest['unique_work_ids']),
                'duplicates_removed' => $fresh && $latestRun ? $latestRun->duplicates_removed : 0,
                'duplicate_clusters' => $clusters->count(),
            ],
            'latest_run' => $latestRun ? $this->runPayload($latestRun, $fresh) : null,
            'clusters' => $clusters->values()->all(),
            'selectedCluster' => $selectedCluster,
            'lock' => [
                'available' => $project->isLocked()
                    ? false
                    : $fresh && $latestRun !== null && count($digest['unique_work_ids']) > 0,
                'blocked_reason' => $this->lockBlockedReason($project, $latestRun, $fresh, count($digest['unique_work_ids'])),
            ],
        ];
    }

    private function state(Project $project, ?ProjectCorpusDedupRun $latestRun, bool $fresh, int $clusterCount): string
    {
        if ($project->isLocked()) {
            return 'locked';
        }

        if ($latestRun === null) {
            return 'not_run';
        }

        if (! $fresh) {
            return 'stale';
        }

        return $clusterCount > 0 ? 'duplicates_found' : 'clear';
    }

    private function lockBlockedReason(Project $project, ?ProjectCorpusDedupRun $latestRun, bool $fresh, int $workCount): ?string
    {
        if ($project->isLocked()) {
            return __('Corpus is already locked.');
        }

        if ($workCount === 0) {
            return __('Run search before locking the corpus.');
        }

        if ($latestRun === null) {
            return __('Run deduplication before locking.');
        }

        if (! $fresh) {
            return __('Draft corpus changed since the latest deduplication run.');
        }

        return null;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function clusters(Project $project): Collection
    {
        $clusters = DB::table('dedup_clusters')
            ->where('project_id', $project->id)
            ->orderByDesc('confidence')
            ->orderByDesc('cluster_size')
            ->orderBy('created_at')
            ->select([
                'id',
                'strategy',
                'representative_work_id',
                'cluster_size',
                'confidence',
                'metadata',
                'is_locked',
                'created_at',
            ])
            ->get();

        $representativeIds = $clusters
            ->pluck('representative_work_id')
            ->filter()
            ->values()
            ->all();
        $titles = $this->workTitles($representativeIds);

        return $clusters->map(function (object $cluster) use ($titles): array {
            $metadata = $this->decodeObject($cluster->metadata);
            $reasons = collect($metadata['evidence'] ?? [])
                ->pluck('reason')
                ->filter()
                ->unique()
                ->values()
                ->all();

            return [
                'id' => (string) $cluster->id,
                'strategy' => (string) $cluster->strategy,
                'representative_work_id' => $cluster->representative_work_id ? (string) $cluster->representative_work_id : null,
                'representative_title' => $titles[$cluster->representative_work_id] ?? null,
                'cluster_size' => (int) $cluster->cluster_size,
                'confidence' => $cluster->confidence === null ? null : (float) $cluster->confidence,
                'reasons' => $reasons,
                'is_locked' => (bool) $cluster->is_locked,
                'created_at' => $this->dateString($cluster->created_at),
            ];
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function clusterDetail(Project $project, string $clusterId): ?array
    {
        $cluster = DB::table('dedup_clusters')
            ->where('project_id', $project->id)
            ->where('id', $clusterId)
            ->first();

        if (! $cluster) {
            return null;
        }

        $members = DB::table('cluster_members as members')
            ->join('scholarly_works as works', 'works.id', '=', 'members.work_id')
            ->where('members.cluster_id', $clusterId)
            ->orderByDesc('members.is_representative')
            ->orderByDesc('members.confidence')
            ->orderBy('works.title')
            ->select([
                'members.work_id',
                'members.is_representative',
                'members.reason',
                'members.confidence',
                'works.title',
                'works.year',
                'works.venue_name',
                'works.cited_by_count',
            ])
            ->get();

        $providers = $this->providersByWork($members->pluck('work_id')->all());
        $metadata = $this->decodeObject($cluster->metadata);

        return [
            'id' => (string) $cluster->id,
            'strategy' => (string) $cluster->strategy,
            'representative_work_id' => $cluster->representative_work_id ? (string) $cluster->representative_work_id : null,
            'cluster_size' => (int) $cluster->cluster_size,
            'confidence' => $cluster->confidence === null ? null : (float) $cluster->confidence,
            'metadata' => $metadata,
            'members' => $members
                ->map(fn (object $member): array => [
                    'work_id' => (string) $member->work_id,
                    'title' => (string) $member->title,
                    'year' => $member->year === null ? null : (int) $member->year,
                    'venue_name' => $member->venue_name ? (string) $member->venue_name : null,
                    'cited_by_count' => (int) $member->cited_by_count,
                    'is_representative' => (bool) $member->is_representative,
                    'reason' => $member->reason ? (string) $member->reason : null,
                    'confidence' => $member->confidence === null ? null : (float) $member->confidence,
                    'providers' => $providers[$member->work_id] ?? [],
                ])
                ->values()
                ->all(),
            'evidence' => collect($metadata['evidence'] ?? [])->values()->all(),
        ];
    }

    /**
     * @param  list<string>  $workIds
     * @return array<string, string>
     */
    private function workTitles(array $workIds): array
    {
        if ($workIds === []) {
            return [];
        }

        return DB::table('scholarly_works')
            ->whereIn('id', $workIds)
            ->pluck('title', 'id')
            ->map(fn (mixed $title): string => (string) $title)
            ->all();
    }

    /**
     * @param  list<string>  $workIds
     * @return array<string, list<string>>
     */
    private function providersByWork(array $workIds): array
    {
        if ($workIds === []) {
            return [];
        }

        return DB::table('work_providers')
            ->whereIn('work_id', $workIds)
            ->orderBy('provider_alias')
            ->select(['work_id', 'provider_alias'])
            ->get()
            ->groupBy('work_id')
            ->map(fn (Collection $rows): array => $rows
                ->pluck('provider_alias')
                ->map(fn (mixed $provider): string => (string) $provider)
                ->unique()
                ->values()
                ->all())
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function runPayload(ProjectCorpusDedupRun $run, bool $fresh): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'fresh' => $fresh,
            'input_count' => $run->input_count,
            'representative_count' => $run->representative_count,
            'duplicate_cluster_count' => $run->duplicate_cluster_count,
            'duplicate_member_count' => $run->duplicate_member_count,
            'duplicates_removed' => $run->duplicates_removed,
            'duration_ms' => $run->duration_ms,
            'policy_stats' => $run->policy_stats ?? [],
            'metadata' => $run->metadata ?? [],
            'completed_at' => $run->completed_at?->toISOString(),
        ];
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
