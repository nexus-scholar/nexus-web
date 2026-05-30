<?php

namespace App\Actions\Projects;

use App\Actions\Audit\RecordAuditEvent;
use App\Models\Project;
use App\Models\ProjectCorpusDedupRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Nexus\Deduplication\Application\DeduplicateCorpus;
use Nexus\Deduplication\Application\DeduplicateCorpusHandler;
use Nexus\Deduplication\Application\DeduplicateCorpusResult;
use Nexus\Deduplication\Domain\Duplicate;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkIdNamespace;

class RunProjectCorpusDeduplication
{
    private const EXACT_IDENTIFIER_REASONS = [
        'doi' => 'doi_match',
        'arxiv' => 'arxiv_match',
        'openalex' => 'openalex_match',
        's2' => 's2_match',
        'semantic_scholar' => 's2_match',
        'pubmed' => 'pubmed_match',
    ];

    public function __construct(
        private readonly BuildProjectCorpusSlice $corpusSlice,
        private readonly ProjectCorpusMembershipHasher $membership,
        private readonly DeduplicateCorpusHandler $deduplicate,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(Project $project, User $actor): ProjectCorpusDedupRun
    {
        if ($project->isLocked()) {
            throw ValidationException::withMessages([
                'corpus' => __('Locked projects cannot be deduplicated.'),
            ]);
        }

        $digest = $this->membership->handle($project);

        if ($digest['unique_work_ids'] === []) {
            throw ValidationException::withMessages([
                'corpus' => __('Run search before deduplicating the corpus.'),
            ]);
        }

        $slice = $this->corpusSlice->handle($project);
        if (count($slice->all()) !== count($digest['unique_work_ids'])) {
            throw ValidationException::withMessages([
                'corpus' => __('The draft corpus contains records that could not be loaded. Refresh the search results before deduplicating.'),
            ]);
        }

        $result = $this->deduplicate->handle(new DeduplicateCorpus(
            corpus: $slice,
            projectId: (string) $project->id,
        ));

        $assembled = $this->assembleClusters(
            $digest['unique_work_ids'],
            $slice->all(),
            $result,
        );

        return DB::transaction(function () use ($actor, $assembled, $digest, $project, $result): ProjectCorpusDedupRun {
            $currentDigest = $this->membership->handle($project);

            if ($currentDigest['hash'] !== $digest['hash']) {
                throw ValidationException::withMessages([
                    'corpus' => __('The draft corpus changed while deduplication was running. Run deduplication again.'),
                ]);
            }

            DB::table('dedup_clusters')
                ->where('project_id', $project->id)
                ->where('is_locked', false)
                ->delete();

            $now = now();

            foreach ($assembled['clusters'] as $cluster) {
                DB::table('dedup_clusters')->insert([
                    'id' => $cluster['id'],
                    'project_id' => $project->id,
                    'strategy' => 'core-v1-plus-exact-identifiers',
                    'thresholds' => json_encode([
                        'title_fuzzy' => 0.95,
                        'exact_identifiers' => array_keys(self::EXACT_IDENTIFIER_REASONS),
                    ]),
                    'representative_work_id' => $cluster['representative_work_id'],
                    'cluster_size' => $cluster['cluster_size'],
                    'confidence' => $cluster['confidence'],
                    'metadata' => json_encode($cluster['metadata']),
                    'is_locked' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('cluster_members')->insert(collect($cluster['members'])
                    ->map(fn (array $member): array => [
                        'id' => (string) Str::uuid(),
                        'cluster_id' => $cluster['id'],
                        'work_id' => $member['work_id'],
                        'is_representative' => $member['is_representative'],
                        'reason' => $member['reason'],
                        'confidence' => $member['confidence'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all());
            }

            $duplicatesRemoved = collect($assembled['clusters'])
                ->sum(fn (array $cluster): int => $cluster['cluster_size'] - 1);

            $policyStats = $result->policyStats;
            foreach ($assembled['exact_policy_stats'] as $reason => $count) {
                $policyStats['persisted_'.$reason] = $count;
            }

            $run = ProjectCorpusDedupRun::create([
                'project_id' => $project->id,
                'ran_by' => $actor->id,
                'status' => 'completed',
                'membership_hash' => $digest['hash'],
                'input_count' => count($digest['unique_work_ids']),
                'representative_count' => count($digest['unique_work_ids']) - $duplicatesRemoved,
                'duplicate_cluster_count' => count($assembled['clusters']),
                'duplicate_member_count' => $duplicatesRemoved,
                'duplicates_removed' => $duplicatesRemoved,
                'duration_ms' => $result->durationMs,
                'policy_stats' => $policyStats,
                'metadata' => [
                    'core_input_count' => $result->inputCount,
                    'core_unique_count' => $result->uniqueCount,
                    'core_duplicates_removed' => $result->duplicatesRemoved,
                    'raw_query_links' => $digest['raw_query_links'],
                    'cluster_strategy' => 'core-v1-plus-exact-identifiers',
                ],
                'completed_at' => $now,
            ]);

            $this->audit->handle(
                'project.corpus.deduplicated',
                $project,
                $actor,
                $project->workspace,
                metadata: [
                    'dedup_run_id' => $run->id,
                    'duplicate_clusters' => count($assembled['clusters']),
                    'duplicates_removed' => $duplicatesRemoved,
                ],
            );

            return $run;
        });
    }

    /**
     * @param  list<string>  $workIds
     * @param  list<ScholarlyWork>  $works
     * @return array{clusters: list<array<string, mixed>>, exact_policy_stats: array<string, int>}
     */
    private function assembleClusters(array $workIds, array $works, DeduplicateCorpusResult $result): array
    {
        $parent = array_fill_keys($workIds, null);
        foreach ($workIds as $workId) {
            $parent[$workId] = $workId;
        }

        $workIdMap = [];
        $workByInternalId = [];
        foreach ($works as $work) {
            $internalId = $this->internalWorkId($work);

            if ($internalId === null) {
                continue;
            }

            $workByInternalId[$internalId] = $work;
            foreach ($work->ids()->all() as $id) {
                $workIdMap[$id->toString()] = $internalId;
            }
        }

        $evidenceByPair = [];
        $coreRepresentatives = [];

        foreach ($result->clusters->all() as $cluster) {
            if ($cluster->size() < 2) {
                continue;
            }

            $memberIds = collect($cluster->members())
                ->map(fn (ScholarlyWork $work): ?string => $this->internalWorkId($work))
                ->filter()
                ->values()
                ->all();

            if (count($memberIds) < 2) {
                continue;
            }

            $representativeId = $cluster->representative()
                ? $this->internalWorkId($cluster->representative())
                : null;

            foreach ($memberIds as $memberId) {
                if ($representativeId !== null) {
                    $coreRepresentatives[$memberId] = $representativeId;
                }

                $this->union($parent, $memberIds[0], $memberId);
            }

            foreach ($cluster->duplicateEvidence() as $duplicate) {
                $this->recordCoreEvidence($evidenceByPair, $workIdMap, $duplicate);
            }
        }

        $exactPolicyStats = [];
        foreach ($this->exactIdentifierGroups($workIds) as $group) {
            $members = $group['work_ids'];

            for ($index = 1; $index < count($members); $index++) {
                $this->union($parent, $members[0], $members[$index]);
                $this->recordEvidence(
                    $evidenceByPair,
                    $members[0],
                    $members[$index],
                    $group['reason'],
                    1.0,
                    'exact_identifier',
                    [
                        'namespace' => $group['namespace'],
                        'value' => $group['value'],
                    ],
                );

                $exactPolicyStats[$group['reason']] = ($exactPolicyStats[$group['reason']] ?? 0) + 1;
            }
        }

        $groups = [];
        foreach ($workIds as $workId) {
            $groups[$this->find($parent, $workId)][] = $workId;
        }

        $workScores = $this->workScores(array_keys($workByInternalId));

        $clusters = collect($groups)
            ->filter(fn (array $members): bool => count($members) > 1)
            ->values()
            ->map(function (array $members) use ($coreRepresentatives, $evidenceByPair, $workScores): array {
                $representativeId = $this->representativeForGroup($members, $coreRepresentatives, $workScores);
                $evidence = $this->evidenceForGroup($members, $evidenceByPair);
                $confidence = collect($evidence)->max('confidence');

                return [
                    'id' => (string) Str::uuid(),
                    'representative_work_id' => $representativeId,
                    'cluster_size' => count($members),
                    'confidence' => $confidence === null ? null : (float) $confidence,
                    'members' => collect($members)
                        ->map(function (string $workId) use ($evidence, $representativeId): array {
                            $memberEvidence = $this->evidenceForMember($workId, $evidence);

                            return [
                                'work_id' => $workId,
                                'is_representative' => $workId === $representativeId,
                                'reason' => $memberEvidence['reason'] ?? null,
                                'confidence' => isset($memberEvidence['confidence'])
                                    ? (float) $memberEvidence['confidence']
                                    : null,
                            ];
                        })
                        ->all(),
                    'metadata' => [
                        'evidence' => $evidence,
                    ],
                ];
            })
            ->all();

        return [
            'clusters' => $clusters,
            'exact_policy_stats' => $exactPolicyStats,
        ];
    }

    private function internalWorkId(ScholarlyWork $work): ?string
    {
        return $work->ids()->findByNamespace(WorkIdNamespace::INTERNAL)?->value;
    }

    /**
     * @param  array<string, string>  $workIdMap
     * @param  array<string, array<string, mixed>>  $evidenceByPair
     */
    private function recordCoreEvidence(array &$evidenceByPair, array $workIdMap, Duplicate $duplicate): void
    {
        $first = $workIdMap[$duplicate->primaryId->toString()] ?? null;
        $second = $workIdMap[$duplicate->secondaryId->toString()] ?? null;

        if ($first === null || $second === null || $first === $second) {
            return;
        }

        $this->recordEvidence(
            $evidenceByPair,
            $first,
            $second,
            $duplicate->reason->value,
            $duplicate->confidence,
            'core',
            [
                'primary_id' => $duplicate->primaryId->toString(),
                'secondary_id' => $duplicate->secondaryId->toString(),
            ],
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $evidenceByPair
     * @param  array<string, mixed>  $metadata
     */
    private function recordEvidence(
        array &$evidenceByPair,
        string $first,
        string $second,
        string $reason,
        float $confidence,
        string $source,
        array $metadata = [],
    ): void {
        $key = $this->pairKey($first, $second);

        $existing = $evidenceByPair[$key]['confidence'] ?? null;
        if ($existing !== null && (float) $existing >= $confidence) {
            return;
        }

        $evidenceByPair[$key] = [
            'work_ids' => [$first, $second],
            'reason' => $reason,
            'confidence' => $confidence,
            'source' => $source,
            ...$metadata,
        ];
    }

    /**
     * @param  list<string>  $workIds
     * @return list<array{namespace: string, value: string, reason: string, work_ids: list<string>}>
     */
    private function exactIdentifierGroups(array $workIds): array
    {
        if ($workIds === []) {
            return [];
        }

        return DB::table('work_external_ids')
            ->whereIn('work_id', $workIds)
            ->whereIn('namespace', array_keys(self::EXACT_IDENTIFIER_REASONS))
            ->select(['work_id', 'namespace', 'value'])
            ->get()
            ->groupBy(fn (object $row): string => $row->namespace.':'.$this->normalizeIdentifier((string) $row->namespace, (string) $row->value))
            ->map(function ($rows): ?array {
                $first = $rows->first();
                $workIds = $rows
                    ->pluck('work_id')
                    ->map(fn (mixed $id): string => (string) $id)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                if (count($workIds) < 2) {
                    return null;
                }

                return [
                    'namespace' => (string) $first->namespace,
                    'value' => $this->normalizeIdentifier((string) $first->namespace, (string) $first->value),
                    'reason' => self::EXACT_IDENTIFIER_REASONS[(string) $first->namespace],
                    'work_ids' => $workIds,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeIdentifier(string $namespace, string $value): string
    {
        $value = trim($value);

        if ($namespace === 'doi') {
            $value = preg_replace('/^(https?:\/\/(dx\.)?doi\.org\/|doi:)/i', '', $value) ?? $value;
        }

        return strtolower($value);
    }

    /**
     * @param  list<string>  $workIds
     * @return array<string, int>
     */
    private function workScores(array $workIds): array
    {
        if ($workIds === []) {
            return [];
        }

        $rows = DB::table('scholarly_works')
            ->whereIn('id', $workIds)
            ->select(['id', 'abstract', 'venue_name', 'year', 'cited_by_count', 'is_retracted'])
            ->get()
            ->keyBy('id');

        $doiWorkIds = DB::table('work_external_ids')
            ->whereIn('work_id', $workIds)
            ->where('namespace', 'doi')
            ->pluck('work_id')
            ->flip();

        $authorCounts = DB::table('work_authors')
            ->whereIn('work_id', $workIds)
            ->select('work_id')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('work_id')
            ->pluck('aggregate', 'work_id');

        $scores = [];
        foreach ($workIds as $workId) {
            $row = $rows[$workId] ?? null;

            if (! $row) {
                $scores[$workId] = 0;

                continue;
            }

            $scores[$workId] = 0
                + ($doiWorkIds->has($workId) ? 2 : 0)
                + (blank($row->abstract) ? 0 : 2)
                + (blank($row->venue_name) ? 0 : 1)
                + ((int) ($authorCounts[$workId] ?? 0) > 0 ? 1 : 0)
                + ($row->year === null ? 0 : 1)
                + ($row->cited_by_count === null ? 0 : 1)
                + ((bool) $row->is_retracted ? 0 : 1);
        }

        return $scores;
    }

    /**
     * @param  list<string>  $members
     * @param  array<string, string>  $coreRepresentatives
     * @param  array<string, int>  $workScores
     */
    private function representativeForGroup(array $members, array $coreRepresentatives, array $workScores): string
    {
        foreach ($members as $member) {
            $representative = $coreRepresentatives[$member] ?? null;

            if ($representative !== null && in_array($representative, $members, true)) {
                return $representative;
            }
        }

        return collect($members)
            ->sortByDesc(fn (string $workId): string => sprintf('%03d:%s', $workScores[$workId] ?? 0, $workId))
            ->first();
    }

    /**
     * @param  list<string>  $members
     * @param  array<string, array<string, mixed>>  $evidenceByPair
     * @return list<array<string, mixed>>
     */
    private function evidenceForGroup(array $members, array $evidenceByPair): array
    {
        $memberLookup = array_fill_keys($members, true);

        return collect($evidenceByPair)
            ->filter(fn (array $evidence): bool => isset($memberLookup[$evidence['work_ids'][0]], $memberLookup[$evidence['work_ids'][1]]))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $evidence
     * @return array<string, mixed>|null
     */
    private function evidenceForMember(string $workId, array $evidence): ?array
    {
        foreach ($evidence as $item) {
            if (in_array($workId, $item['work_ids'], true)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $parent
     */
    private function union(array &$parent, string $first, string $second): void
    {
        if (! isset($parent[$first], $parent[$second])) {
            return;
        }

        $firstRoot = $this->find($parent, $first);
        $secondRoot = $this->find($parent, $second);

        if ($firstRoot !== $secondRoot) {
            $parent[$secondRoot] = $firstRoot;
        }
    }

    /**
     * @param  array<string, string>  $parent
     */
    private function find(array &$parent, string $workId): string
    {
        if ($parent[$workId] !== $workId) {
            $parent[$workId] = $this->find($parent, $parent[$workId]);
        }

        return $parent[$workId];
    }

    private function pairKey(string $first, string $second): string
    {
        return $first < $second ? "{$first}|{$second}" : "{$second}|{$first}";
    }
}
