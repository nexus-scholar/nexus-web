<?php

namespace App\Actions\Projects;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectCorpusDedupRun;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LockProjectCorpus
{
    public function __construct(
        private readonly ProjectCorpusMembershipHasher $membership,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(Project $project, User $actor, string $reason): string
    {
        $reason = trim($reason);

        if ($project->isLocked()) {
            throw ValidationException::withMessages([
                'corpus_lock' => __('This project is already locked.'),
            ]);
        }

        return DB::transaction(function () use ($actor, $project, $reason): string {
            $digest = $this->membership->handle($project);
            $run = $this->latestFreshRun($project, $digest['hash']);

            if (! $run) {
                throw ValidationException::withMessages([
                    'corpus_lock' => __('Run deduplication again before locking this corpus.'),
                ]);
            }

            if ($digest['unique_work_ids'] === []) {
                throw ValidationException::withMessages([
                    'corpus_lock' => __('Run search before locking the corpus.'),
                ]);
            }

            $now = now();
            $snapshotId = (string) Str::uuid();
            $this->assertDedupEvidenceMatchesRun($project, $run);
            $snapshotRows = $this->snapshotRows($project, $snapshotId, $digest['unique_work_ids'], $now);

            if (count($snapshotRows) !== $run->representative_count) {
                throw ValidationException::withMessages([
                    'corpus_lock' => __('Deduplication evidence no longer matches the lock snapshot. Run deduplication again.'),
                ]);
            }

            DB::table('corpus_snapshots')->insert([
                'id' => $snapshotId,
                'project_id' => $project->id,
                'locked_at' => $now,
                'work_count' => count($snapshotRows),
                'created_by' => (string) $actor->id,
                'lock_reason' => $reason,
                'metadata' => json_encode([
                    'source' => 'nexus-web',
                    'representative_snapshot' => true,
                    'dedup_run_id' => $run->id,
                    'membership_hash' => $digest['hash'],
                    'input_count' => $run->input_count,
                    'representative_count' => $run->representative_count,
                    'duplicate_cluster_count' => $run->duplicate_cluster_count,
                    'duplicates_removed' => $run->duplicates_removed,
                    'policy_stats' => $run->policy_stats,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('corpus_snapshot_works')->insert($snapshotRows);

            $project->forceFill([
                'status' => ProjectStatus::LockedCorpus,
                'locked_at' => $now,
                'locked_by' => (string) $actor->id,
                'lock_reason' => $reason,
                'unlocked_at' => null,
                'unlocked_by' => null,
                'unlock_reason' => null,
            ])->save();

            DB::table('dedup_clusters')
                ->where('project_id', $project->id)
                ->update(['is_locked' => true, 'updated_at' => $now]);

            DB::table('project_lock_audits')->insert([
                'id' => (string) Str::uuid(),
                'project_id' => $project->id,
                'action' => 'lock',
                'actor_id' => (string) $actor->id,
                'reason' => $reason,
                'metadata' => json_encode([
                    'snapshot_id' => $snapshotId,
                    'dedup_run_id' => $run->id,
                    'work_count' => count($snapshotRows),
                ]),
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->audit->handle(
                'project.corpus.locked',
                $project,
                $actor,
                $project->workspace,
                $reason,
                [
                    'snapshot_id' => $snapshotId,
                    'dedup_run_id' => $run->id,
                    'work_count' => count($snapshotRows),
                ],
            );

            return $snapshotId;
        });
    }

    private function assertDedupEvidenceMatchesRun(Project $project, ProjectCorpusDedupRun $run): void
    {
        $clusterCount = DB::table('dedup_clusters')
            ->where('project_id', $project->id)
            ->count();

        $duplicateMemberCount = DB::table('cluster_members as members')
            ->join('dedup_clusters as clusters', 'clusters.id', '=', 'members.cluster_id')
            ->where('clusters.project_id', $project->id)
            ->where('members.is_representative', false)
            ->count();

        if ($clusterCount !== $run->duplicate_cluster_count || $duplicateMemberCount !== $run->duplicate_member_count) {
            throw ValidationException::withMessages([
                'corpus_lock' => __('Deduplication evidence is incomplete. Run deduplication again.'),
            ]);
        }
    }

    private function latestFreshRun(Project $project, string $membershipHash): ?ProjectCorpusDedupRun
    {
        return ProjectCorpusDedupRun::query()
            ->where('project_id', $project->id)
            ->where('membership_hash', $membershipHash)
            ->latest()
            ->first();
    }

    /**
     * @param  list<string>  $draftWorkIds
     * @return list<array<string, mixed>>
     */
    private function snapshotRows(Project $project, string $snapshotId, array $draftWorkIds, mixed $now): array
    {
        $clusters = $this->clusters($project);
        $excludedToRepresentative = [];
        $representativeSources = [];

        foreach ($clusters as $cluster) {
            $representativeId = (string) $cluster['representative_work_id'];
            $memberIds = $cluster['member_ids'];

            if (! in_array($representativeId, $draftWorkIds, true)) {
                throw ValidationException::withMessages([
                    'corpus_lock' => __('A duplicate cluster representative is no longer in the draft corpus. Run deduplication again.'),
                ]);
            }

            $representativeSources[$representativeId] = $memberIds;
            foreach ($memberIds as $memberId) {
                if (! in_array($memberId, $draftWorkIds, true)) {
                    throw ValidationException::withMessages([
                        'corpus_lock' => __('A duplicate cluster member is no longer in the draft corpus. Run deduplication again.'),
                    ]);
                }

                if ($memberId !== $representativeId) {
                    $excludedToRepresentative[$memberId] = $representativeId;
                }
            }
        }

        $provenanceByWork = $this->draftProvenanceByWork($project, $draftWorkIds);
        $snapshotWorkIds = collect($draftWorkIds)
            ->reject(fn (string $workId): bool => isset($excludedToRepresentative[$workId]))
            ->values()
            ->all();

        return collect($snapshotWorkIds)
            ->map(function (string $workId) use ($now, $provenanceByWork, $representativeSources, $snapshotId): array {
                $sourceWorkIds = $representativeSources[$workId] ?? [$workId];
                $provenance = collect($sourceWorkIds)
                    ->flatMap(fn (string $sourceWorkId): array => $provenanceByWork[$sourceWorkId] ?? [])
                    ->values();

                return [
                    'id' => (string) Str::uuid(),
                    'snapshot_id' => $snapshotId,
                    'work_id' => $workId,
                    'search_query_ids' => json_encode($provenance
                        ->pluck('search_query_id')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all()),
                    'provider_aliases' => json_encode($provenance
                        ->pluck('provider_alias')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all()),
                    'provenance' => json_encode($provenance->all()),
                    'included_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->all();
    }

    /**
     * @return list<array{representative_work_id: string, member_ids: list<string>}>
     */
    private function clusters(Project $project): array
    {
        return DB::table('dedup_clusters as clusters')
            ->where('clusters.project_id', $project->id)
            ->whereNotNull('clusters.representative_work_id')
            ->orderBy('clusters.created_at')
            ->select(['clusters.id', 'clusters.representative_work_id'])
            ->get()
            ->map(function (object $cluster): ?array {
                $memberIds = DB::table('cluster_members')
                    ->where('cluster_id', $cluster->id)
                    ->orderByDesc('is_representative')
                    ->orderBy('work_id')
                    ->pluck('work_id')
                    ->map(fn (mixed $id): string => (string) $id)
                    ->all();

                if (count($memberIds) < 2) {
                    return null;
                }

                return [
                    'representative_work_id' => (string) $cluster->representative_work_id,
                    'member_ids' => $memberIds,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $workIds
     * @return array<string, list<array<string, mixed>>>
     */
    private function draftProvenanceByWork(Project $project, array $workIds): array
    {
        if ($workIds === []) {
            return [];
        }

        return DB::table('query_works')
            ->join('search_queries', 'search_queries.id', '=', 'query_works.search_query_id')
            ->leftJoin('project_search_run_items as items', 'items.core_search_query_id', '=', 'search_queries.id')
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
                'items.label',
            ])
            ->get()
            ->groupBy('work_id')
            ->map(fn (Collection $rows): array => $rows
                ->map(fn (object $row): array => [
                    'source_work_id' => (string) $row->work_id,
                    'search_query_id' => (string) $row->search_query_id,
                    'query_label' => $row->label ? (string) $row->label : str($row->query_text)->limit(80)->toString(),
                    'query_text' => (string) $row->query_text,
                    'provider_alias' => $row->provider_alias ? (string) $row->provider_alias : null,
                    'provider_work_id' => $row->provider_work_id ? (string) $row->provider_work_id : null,
                    'rank' => $row->rank === null ? null : (int) $row->rank,
                    'seen_at' => $row->seen_at ? (string) $row->seen_at : null,
                ])
                ->values()
                ->all())
            ->all();
    }
}
