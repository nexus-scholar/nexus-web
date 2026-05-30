<?php

namespace App\Actions\Projects;

use App\Enums\ProjectScreeningAssignmentStatus;
use App\Enums\ProjectScreeningBatchStatus;
use App\Enums\ProjectScreeningConflictStatus;
use App\Models\Project;
use App\Models\ProjectScreeningBatch;
use App\Models\ProjectScreeningConflict;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Nexus\Screening\Domain\ScreeningDecision;
use Nexus\Screening\Domain\ScreeningStage;

class BuildProjectFullTextCandidates
{
    /**
     * @return array{
     *     ready: bool,
     *     blockers: list<string>,
     *     screening_batch: ProjectScreeningBatch|null,
     *     snapshot: object|null,
     *     counts: array<string, int>,
     *     candidates: list<array<string, mixed>>
     * }
     */
    public function handle(Project $project): array
    {
        $project->loadMissing(['protocol']);

        $batch = $this->latestCompletedBatch($project);
        $snapshot = $this->latestSnapshot($project);
        $blockers = [];

        if (! $batch instanceof ProjectScreeningBatch) {
            $blockers[] = 'Complete title and abstract screening before full-text retrieval.';
        }

        if (! $snapshot) {
            $blockers[] = 'Lock a representative corpus snapshot before full-text retrieval.';
        }

        if ($batch instanceof ProjectScreeningBatch && $snapshot && (string) $batch->snapshot_id !== (string) $snapshot->id) {
            $blockers[] = 'The completed screening batch is not tied to the latest locked snapshot.';
        }

        if (($project->protocol?->full_text_policy ?? 'optional') === 'manual_uploads_only') {
            $blockers[] = 'This protocol allows manual uploads only; automatic retrieval is disabled.';
        }

        $candidates = $batch instanceof ProjectScreeningBatch ? $this->candidatesForBatch($batch) : [];
        if ($batch instanceof ProjectScreeningBatch && $candidates === []) {
            $blockers[] = 'No included or maybe records are ready for full-text retrieval.';
        }

        $counts = [
            'include' => collect($candidates)->where('screening_decision', ScreeningDecision::INCLUDE->value)->count(),
            'needs_review' => collect($candidates)->where('screening_decision', ScreeningDecision::NEEDS_REVIEW->value)->count(),
            'candidate_count' => count($candidates),
            'excluded' => (int) data_get($batch?->counts, 'outcomes.excluded', 0),
            'total_works' => (int) data_get($batch?->counts, 'outcomes.total_works', 0),
        ];

        return [
            'ready' => $blockers === [],
            'blockers' => $blockers,
            'screening_batch' => $batch,
            'snapshot' => $snapshot,
            'counts' => $counts,
            'candidates' => $candidates,
        ];
    }

    private function latestCompletedBatch(Project $project): ?ProjectScreeningBatch
    {
        return ProjectScreeningBatch::query()
            ->where('project_id', $project->id)
            ->where('stage', ScreeningStage::TITLE_ABSTRACT->value)
            ->where('status', ProjectScreeningBatchStatus::Completed->value)
            ->latest('completed_at')
            ->latest()
            ->first();
    }

    private function latestSnapshot(Project $project): ?object
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

    /**
     * @return list<array<string, mixed>>
     */
    private function candidatesForBatch(ProjectScreeningBatch $batch): array
    {
        $outcomes = $this->finalOutcomes($batch)
            ->filter(fn (array $outcome): bool => in_array($outcome['screening_decision'], [
                ScreeningDecision::INCLUDE->value,
                ScreeningDecision::NEEDS_REVIEW->value,
            ], true))
            ->sortBy('sort_order')
            ->values();

        if ($outcomes->isEmpty()) {
            return [];
        }

        $workIds = $outcomes->pluck('work_id')->all();
        $works = DB::table('scholarly_works')
            ->whereIn('id', $workIds)
            ->get(['id', 'title', 'abstract', 'year', 'venue_name', 'venue_type', 'language', 'cited_by_count', 'is_retracted'])
            ->keyBy('id');
        $identifiers = $this->identifiersByWork($workIds);
        $providers = $this->providersByWork($workIds);

        return $outcomes
            ->map(function (array $outcome) use ($identifiers, $providers, $works): array {
                $work = $works[$outcome['work_id']] ?? null;

                return [
                    ...$outcome,
                    'work' => [
                        'id' => $outcome['work_id'],
                        'title' => $work?->title ? (string) $work->title : 'Unknown work',
                        'abstract' => $work?->abstract ? (string) $work->abstract : null,
                        'year' => $work?->year === null ? null : (int) $work->year,
                        'venue_name' => $work?->venue_name ? (string) $work->venue_name : null,
                        'venue_type' => $work?->venue_type ? (string) $work->venue_type : null,
                        'language' => $work?->language ? (string) $work->language : null,
                        'cited_by_count' => $work?->cited_by_count === null ? 0 : (int) $work->cited_by_count,
                        'is_retracted' => (bool) ($work?->is_retracted ?? false),
                        'identifiers' => $identifiers[$outcome['work_id']] ?? [],
                        'providers' => $providers[$outcome['work_id']] ?? [],
                    ],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function finalOutcomes(ProjectScreeningBatch $batch): Collection
    {
        $assignmentsByWork = DB::table('project_screening_assignments')
            ->where('batch_id', $batch->id)
            ->orderBy('sort_order')
            ->get(['work_id', 'status', 'screening_decision_id', 'sort_order'])
            ->groupBy('work_id');

        $resolvedConflictDecisionIds = ProjectScreeningConflict::query()
            ->where('batch_id', $batch->id)
            ->where('status', ProjectScreeningConflictStatus::Resolved->value)
            ->whereNotNull('resolved_decision_id')
            ->pluck('resolved_decision_id', 'work_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        $decisionIds = $assignmentsByWork
            ->flatMap(fn ($assignments) => $assignments->pluck('screening_decision_id'))
            ->merge(array_values($resolvedConflictDecisionIds))
            ->filter()
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        $decisionsById = $decisionIds === []
            ? collect()
            : DB::table('screening_decisions')
                ->whereIn('id', $decisionIds)
                ->get(['id', 'decision', 'reason'])
                ->keyBy('id');

        return $assignmentsByWork
            ->map(function (Collection $assignments, string $workId) use ($batch, $decisionsById, $resolvedConflictDecisionIds): ?array {
                $decisionId = null;
                $resolvedConflictDecisionId = $resolvedConflictDecisionIds[$workId] ?? null;

                if ($resolvedConflictDecisionId) {
                    $decisionId = $resolvedConflictDecisionId;
                } else {
                    $linkedDecisionIds = $assignments
                        ->pluck('screening_decision_id')
                        ->filter()
                        ->map(fn (mixed $id): string => (string) $id)
                        ->values();
                    $allAssignmentsResolved = $assignments->isNotEmpty()
                        && $assignments->every(
                            fn (object $assignment): bool => (string) $assignment->status === ProjectScreeningAssignmentStatus::Resolved->value,
                        );

                    if ($allAssignmentsResolved && $linkedDecisionIds->count() >= $batch->required_reviewer_count) {
                        $uniqueDecisionValues = $linkedDecisionIds
                            ->map(fn (string $id): ?string => $decisionsById[$id]->decision ?? null)
                            ->filter()
                            ->unique()
                            ->values();

                        if ($uniqueDecisionValues->count() === 1) {
                            $decisionId = $linkedDecisionIds->first();
                        }
                    }
                }

                if (! $decisionId || ! isset($decisionsById[$decisionId])) {
                    return null;
                }

                $decision = $decisionsById[$decisionId];

                return [
                    'work_id' => $workId,
                    'screening_decision_id' => (string) $decisionId,
                    'screening_decision' => (string) $decision->decision,
                    'screening_decision_label' => $this->decisionLabel((string) $decision->decision),
                    'screening_reason' => $decision->reason ? (string) $decision->reason : null,
                    'sort_order' => (int) ($assignments->min('sort_order') ?? 0),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  list<string>  $workIds
     * @return array<string, list<array<string, mixed>>>
     */
    private function identifiersByWork(array $workIds): array
    {
        return DB::table('work_external_ids')
            ->whereIn('work_id', $workIds)
            ->orderBy('namespace')
            ->get(['work_id', 'namespace', 'value', 'is_primary'])
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
            ->get(['work_id', 'provider_alias', 'provider_work_id'])
            ->groupBy('work_id')
            ->map(fn (Collection $rows): array => $rows
                ->map(fn (object $row): array => [
                    'provider_alias' => (string) $row->provider_alias,
                    'provider_work_id' => $row->provider_work_id ? (string) $row->provider_work_id : null,
                ])
                ->values()
                ->all())
            ->all();
    }

    private function decisionLabel(string $decision): string
    {
        return match ($decision) {
            ScreeningDecision::INCLUDE->value => 'Include',
            ScreeningDecision::NEEDS_REVIEW->value => 'Maybe',
            ScreeningDecision::EXCLUDE->value => 'Exclude',
            default => str($decision)->replace('_', ' ')->title()->toString(),
        };
    }
}
