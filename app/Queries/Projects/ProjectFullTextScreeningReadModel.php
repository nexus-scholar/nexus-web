<?php

namespace App\Queries\Projects;

use App\Actions\Projects\BuildProjectFullTextScreeningCandidates;
use App\Enums\ProjectRole;
use App\Enums\ProjectScreeningAssignmentStatus;
use App\Enums\ProjectScreeningBatchStatus;
use App\Enums\ProjectScreeningConflictStatus;
use App\Models\Project;
use App\Models\ProjectScreeningAssignment;
use App\Models\ProjectScreeningBatch;
use App\Models\ProjectScreeningConflict;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Nexus\Screening\Domain\ScreeningDecision;
use Nexus\Screening\Domain\ScreeningStage;

final class ProjectFullTextScreeningReadModel
{
    public function __construct(
        private readonly BuildProjectFullTextScreeningCandidates $candidateBuilder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forProject(Project $project, User $actor, ?string $selectedConflictId = null): array
    {
        $project->loadMissing(['protocol', 'workspace']);
        $candidateSet = $this->candidateBuilder->handle($project);
        $batch = $this->activeBatch($project);
        $conflicts = $batch ? $this->conflicts($batch) : collect();
        $selectedConflict = $this->selectedConflict($conflicts, $selectedConflictId);

        return [
            'readiness' => [
                'ready' => $candidateSet['ready'],
                'blockers' => $candidateSet['blockers'],
                'counts' => $candidateSet['counts'],
                'follow_up' => $candidateSet['follow_up'],
                'title_abstract_batch' => $candidateSet['title_abstract_batch'] ? [
                    'id' => $candidateSet['title_abstract_batch']->id,
                    'completed_at' => $this->dateString($candidateSet['title_abstract_batch']->completed_at),
                    'status' => $candidateSet['title_abstract_batch']->status->value,
                    'status_label' => $this->batchStatusLabel($candidateSet['title_abstract_batch']->status),
                ] : null,
                'full_text_batch' => $candidateSet['full_text_batch'] ? [
                    'id' => $candidateSet['full_text_batch']->id,
                    'status' => $candidateSet['full_text_batch']->status->value,
                    'status_label' => $candidateSet['full_text_batch']->status->label(),
                    'candidate_count' => $candidateSet['full_text_batch']->candidate_count,
                    'success_count' => $candidateSet['full_text_batch']->success_count,
                    'failed_count' => $candidateSet['full_text_batch']->failed_count,
                    'skipped_count' => $candidateSet['full_text_batch']->skipped_count,
                    'manual_needed_count' => $candidateSet['full_text_batch']->manual_needed_count,
                    'completed_at' => $this->dateString($candidateSet['full_text_batch']->completed_at),
                ] : null,
                'snapshot' => $candidateSet['snapshot'] ? [
                    'id' => (string) $candidateSet['snapshot']->id,
                    'locked_at' => $this->dateString($candidateSet['snapshot']->locked_at),
                    'work_count' => (int) $candidateSet['snapshot']->work_count,
                ] : null,
            ],
            'protocol' => $this->protocolPayload($project),
            'batch' => $batch ? $this->batchPayload($batch) : null,
            'setup' => [
                'default_required_reviewer_count' => max(1, (int) ($project->protocol?->min_reviewer_count ?? 2)),
                'available_reviewers' => $this->availableReviewers($project),
            ],
            'workload' => $batch ? $this->workload($batch) : [],
            'conflicts' => $conflicts->values()->all(),
            'selectedConflict' => $selectedConflict,
            'recentAuditEvents' => $this->recentAuditEvents($project),
            'nextReviewerAssignmentUrl' => $batch && $this->actorHasAssignments($batch, $actor)
                ? route('projects.full-text-screening.queue', $project, absolute: false)
                : null,
            'actor' => [
                'id' => $actor->id,
                'project_role' => $actor->projectRole($project)?->value,
            ],
        ];
    }

    private function activeBatch(Project $project): ?ProjectScreeningBatch
    {
        return ProjectScreeningBatch::query()
            ->where('project_id', $project->id)
            ->where('stage', ScreeningStage::FULL_TEXT->value)
            ->where('status', '!=', ProjectScreeningBatchStatus::Cancelled->value)
            ->latest('started_at')
            ->latest()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function protocolPayload(Project $project): array
    {
        $protocol = $project->protocol;

        return [
            'title' => $protocol?->title ?? $project->name,
            'research_question' => $protocol?->research_question,
            'inclusion_criteria' => $protocol?->inclusion_criteria,
            'exclusion_criteria' => $protocol?->exclusion_criteria,
            'language_policy' => $protocol?->language_policy,
            'min_reviewer_count' => $protocol?->min_reviewer_count ?? 2,
            'ai_screening_policy' => $protocol?->ai_screening_policy,
            'full_text_policy' => $protocol?->full_text_policy,
            'full_text_policy_label' => $this->fullTextPolicyLabel($protocol?->full_text_policy ?? 'optional'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function batchPayload(ProjectScreeningBatch $batch): array
    {
        $counts = $this->normalizedCounts($batch->counts ?? []);
        $total = $counts['assignments']['total'];
        $resolved = $counts['assignments']['resolved'];

        return [
            'id' => $batch->id,
            'status' => $batch->status->value,
            'status_label' => $this->batchStatusLabel($batch->status),
            'stage' => $batch->stage,
            'stage_label' => 'Full text',
            'required_reviewer_count' => $batch->required_reviewer_count,
            'criteria_hash' => $batch->criteria_hash,
            'snapshot_id' => $batch->snapshot_id,
            'source_full_text_batch_id' => $batch->source_full_text_batch_id,
            'started_at' => $this->dateString($batch->started_at),
            'completed_at' => $this->dateString($batch->completed_at),
            'counts' => $counts,
            'progress_percent' => $total > 0 ? (int) round(($resolved / $total) * 100) : 0,
        ];
    }

    private function actorHasAssignments(ProjectScreeningBatch $batch, User $actor): bool
    {
        return ProjectScreeningAssignment::query()
            ->where('batch_id', $batch->id)
            ->where('assigned_to', $actor->id)
            ->exists();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function availableReviewers(Project $project): array
    {
        return $project->activeMemberships()
            ->with('user')
            ->whereIn('role', [ProjectRole::Reviewer->value, ProjectRole::Adjudicator->value])
            ->orderBy('role')
            ->get()
            ->filter(fn ($membership): bool => $membership->user instanceof User
                && ! $membership->user->isDisabled()
                && $membership->user->workspaceRole($project->workspace) !== null)
            ->map(fn ($membership): array => [
                'id' => $membership->user->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role->value,
                'role_label' => $membership->role->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function workload(ProjectScreeningBatch $batch): array
    {
        $rows = DB::table('project_screening_assignments as assignments')
            ->join('users', 'users.id', '=', 'assignments.assigned_to')
            ->where('assignments.batch_id', $batch->id)
            ->select(['assignments.assigned_to', 'assignments.status', 'users.name', 'users.email'])
            ->selectRaw('count(*) as aggregate')
            ->groupBy('assignments.assigned_to', 'assignments.status', 'users.name', 'users.email')
            ->orderBy('users.name')
            ->get()
            ->groupBy('assigned_to');

        return $rows
            ->map(function (Collection $statuses): array {
                $first = $statuses->first();
                $counts = collect(ProjectScreeningAssignmentStatus::cases())
                    ->mapWithKeys(fn (ProjectScreeningAssignmentStatus $status): array => [$status->value => 0])
                    ->all();

                foreach ($statuses as $row) {
                    $counts[(string) $row->status] = (int) $row->aggregate;
                }

                return [
                    'user_id' => (int) $first->assigned_to,
                    'name' => (string) $first->name,
                    'email' => (string) $first->email,
                    'counts' => $counts + ['total' => array_sum($counts)],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function conflicts(ProjectScreeningBatch $batch): Collection
    {
        $conflicts = ProjectScreeningConflict::query()
            ->where('batch_id', $batch->id)
            ->orderByRaw('case when status = ? then 0 else 1 end', [ProjectScreeningConflictStatus::Open->value])
            ->latest('opened_at')
            ->limit(20)
            ->get();

        if ($conflicts->isEmpty()) {
            return collect();
        }

        $workRows = DB::table('scholarly_works')
            ->whereIn('id', $conflicts->pluck('work_id')->all())
            ->get(['id', 'title', 'abstract', 'year', 'venue_name'])
            ->keyBy('id');

        $decisionIds = $conflicts
            ->flatMap(fn (ProjectScreeningConflict $conflict): array => array_values($conflict->decision_ids ?? []))
            ->merge($conflicts->pluck('resolved_decision_id')->filter())
            ->unique()
            ->values()
            ->all();
        $decisions = $this->decisionsById($decisionIds);

        return $conflicts->map(function (ProjectScreeningConflict $conflict) use ($decisions, $workRows): array {
            $work = $workRows[$conflict->work_id] ?? null;

            return [
                'id' => $conflict->id,
                'status' => $conflict->status->value,
                'status_label' => $this->conflictStatusLabel($conflict->status),
                'work_id' => $conflict->work_id,
                'work' => [
                    'title' => $work?->title ? (string) $work->title : 'Unknown work',
                    'abstract' => $work?->abstract ? (string) $work->abstract : null,
                    'year' => $work?->year === null ? null : (int) $work->year,
                    'venue_name' => $work?->venue_name ? (string) $work->venue_name : null,
                ],
                'source_decisions' => collect($conflict->decision_ids ?? [])
                    ->map(fn (string $id): ?array => $decisions[$id] ?? null)
                    ->filter()
                    ->values()
                    ->all(),
                'resolved_decision' => $conflict->resolved_decision_id
                    ? ($decisions[$conflict->resolved_decision_id] ?? null)
                    : null,
                'resolution_reason' => $conflict->resolution_reason,
                'opened_at' => $this->dateString($conflict->opened_at),
                'resolved_at' => $this->dateString($conflict->resolved_at),
                'resolve_url' => route('projects.full-text-screening.conflicts.resolve', [$conflict->project_id, $conflict->id], absolute: false),
            ];
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $conflicts
     * @return array<string, mixed>|null
     */
    private function selectedConflict(Collection $conflicts, ?string $selectedConflictId): ?array
    {
        if ($conflicts->isEmpty() || ! $selectedConflictId) {
            return null;
        }

        return $conflicts->firstWhere('id', $selectedConflictId);
    }

    /**
     * @param  list<string>  $decisionIds
     * @return array<string, array<string, mixed>>
     */
    private function decisionsById(array $decisionIds): array
    {
        if ($decisionIds === []) {
            return [];
        }

        $rows = DB::table('screening_decisions')
            ->whereIn('id', $decisionIds)
            ->get(['id', 'decision', 'decision_source', 'reason', 'decided_by', 'decided_at', 'evidence', 'uncertainty', 'exclusion_basis']);
        $users = User::query()
            ->whereIn('id', $rows->pluck('decided_by')->filter()->map(fn (mixed $id): int => (int) $id)->all())
            ->get()
            ->keyBy('id');

        return $rows
            ->mapWithKeys(fn (object $row): array => [
                (string) $row->id => [
                    'id' => (string) $row->id,
                    'decision' => (string) $row->decision,
                    'decision_label' => $this->decisionLabel((string) $row->decision),
                    'decision_source' => $row->decision_source ? (string) $row->decision_source : null,
                    'reason' => $row->reason ? (string) $row->reason : null,
                    'decided_at' => $this->dateString($row->decided_at),
                    'decided_by' => [
                        'id' => $row->decided_by ? (int) $row->decided_by : null,
                        'name' => $row->decided_by && isset($users[(int) $row->decided_by])
                            ? $users[(int) $row->decided_by]->name
                            : 'Unknown reviewer',
                    ],
                    'evidence' => $this->decodeList($row->evidence),
                    'uncertainty' => $this->decodeList($row->uncertainty),
                    'exclusion_basis' => $this->decodeList($row->exclusion_basis),
                ],
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentAuditEvents(Project $project): array
    {
        return DB::table('audit_events')
            ->where('project_id', $project->id)
            ->where('event_type', 'like', 'project.full_text_screening.%')
            ->latest('occurred_at')
            ->limit(5)
            ->get(['id', 'event_type', 'reason', 'occurred_at'])
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'event_type' => (string) $row->event_type,
                'label' => str((string) $row->event_type)->after('project.full_text_screening.')->replace('_', ' ')->title()->toString(),
                'reason' => $row->reason ? (string) $row->reason : null,
                'occurred_at' => $this->dateString($row->occurred_at),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $counts
     * @return array<string, mixed>
     */
    private function normalizedCounts(array $counts): array
    {
        return [
            'assignments' => [
                'total' => (int) data_get($counts, 'assignments.total', 0),
                'pending' => (int) data_get($counts, 'assignments.pending', 0),
                'in_progress' => (int) data_get($counts, 'assignments.in_progress', 0),
                'decided' => (int) data_get($counts, 'assignments.decided', 0),
                'conflict' => (int) data_get($counts, 'assignments.conflict', 0),
                'resolved' => (int) data_get($counts, 'assignments.resolved', 0),
            ],
            'decisions' => [
                ScreeningDecision::INCLUDE->value => (int) data_get($counts, 'decisions.include', 0),
                ScreeningDecision::NEEDS_REVIEW->value => (int) data_get($counts, 'decisions.needs_review', 0),
                ScreeningDecision::EXCLUDE->value => (int) data_get($counts, 'decisions.exclude', 0),
            ],
            'conflicts' => [
                'open' => (int) data_get($counts, 'conflicts.open', 0),
                'resolved' => (int) data_get($counts, 'conflicts.resolved', 0),
            ],
            'outcomes' => [
                'total_works' => (int) data_get($counts, 'outcomes.total_works', 0),
                'resolved_works' => (int) data_get($counts, 'outcomes.resolved_works', 0),
                'unresolved_works' => (int) data_get($counts, 'outcomes.unresolved_works', 0),
                ScreeningDecision::INCLUDE->value => (int) data_get($counts, 'outcomes.include', 0),
                ScreeningDecision::NEEDS_REVIEW->value => (int) data_get($counts, 'outcomes.needs_review', 0),
                ScreeningDecision::EXCLUDE->value => (int) data_get($counts, 'outcomes.exclude', 0),
                'ready_for_full_text' => (int) data_get($counts, 'outcomes.ready_for_full_text', 0),
                'excluded' => (int) data_get($counts, 'outcomes.excluded', 0),
            ],
        ];
    }

    private function batchStatusLabel(ProjectScreeningBatchStatus $status): string
    {
        return match ($status) {
            ProjectScreeningBatchStatus::Draft => 'Draft',
            ProjectScreeningBatchStatus::Active => 'Active',
            ProjectScreeningBatchStatus::Conflicts => 'Conflicts',
            ProjectScreeningBatchStatus::Completed => 'Completed',
            ProjectScreeningBatchStatus::Cancelled => 'Cancelled',
        };
    }

    private function conflictStatusLabel(ProjectScreeningConflictStatus $status): string
    {
        return match ($status) {
            ProjectScreeningConflictStatus::Open => 'Open',
            ProjectScreeningConflictStatus::Resolved => 'Resolved',
        };
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

    private function fullTextPolicyLabel(string $policy): string
    {
        return match ($policy) {
            'required_for_inclusion' => 'Required for inclusion',
            'manual_uploads_only' => 'Manual uploads only',
            default => 'Optional',
        };
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

    private function dateString(mixed $value): ?string
    {
        return $value ? (string) $value : null;
    }
}
