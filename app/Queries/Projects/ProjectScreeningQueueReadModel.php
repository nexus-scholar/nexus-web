<?php

namespace App\Queries\Projects;

use App\Enums\ProjectScreeningAssignmentStatus;
use App\Enums\ProjectScreeningBatchStatus;
use App\Models\Project;
use App\Models\ProjectScreeningAssignment;
use App\Models\ProjectScreeningBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Nexus\Screening\Domain\ScreeningDecision;
use Nexus\Screening\Domain\ScreeningStage;

final class ProjectScreeningQueueReadModel
{
    /**
     * @return array<string, mixed>
     */
    public function forReviewer(Project $project, User $actor, ?string $selectedAssignmentId = null): array
    {
        $project->loadMissing(['protocol', 'workspace']);
        $batch = $this->activeBatch($project);

        if (! $batch) {
            return [
                'batch' => null,
                'assignments' => [],
                'selectedAssignment' => null,
                'protocol' => $this->protocolPayload($project),
            ];
        }

        $assignments = $this->assignments($batch, $actor);
        $selected = $this->selectedAssignment($batch, $actor, $selectedAssignmentId);

        return [
            'batch' => [
                'id' => $batch->id,
                'status' => $batch->status->value,
                'status_label' => $this->batchStatusLabel($batch->status),
                'stage_label' => 'Title and abstract',
                'required_reviewer_count' => $batch->required_reviewer_count,
                'counts' => $batch->counts ?? [],
            ],
            'assignments' => $assignments,
            'selectedAssignment' => $selected ? $this->assignmentDetail($selected) : null,
            'protocol' => $this->protocolPayload($project),
        ];
    }

    private function activeBatch(Project $project): ?ProjectScreeningBatch
    {
        return ProjectScreeningBatch::query()
            ->where('project_id', $project->id)
            ->where('stage', ScreeningStage::TITLE_ABSTRACT->value)
            ->whereIn('status', [
                ProjectScreeningBatchStatus::Active->value,
                ProjectScreeningBatchStatus::Conflicts->value,
                ProjectScreeningBatchStatus::Completed->value,
            ])
            ->latest('started_at')
            ->latest()
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function assignments(ProjectScreeningBatch $batch, User $actor): array
    {
        return DB::table('project_screening_assignments as assignments')
            ->join('scholarly_works as works', 'works.id', '=', 'assignments.work_id')
            ->leftJoin('screening_decisions as decisions', 'decisions.id', '=', 'assignments.screening_decision_id')
            ->where('assignments.batch_id', $batch->id)
            ->where('assignments.assigned_to', $actor->id)
            ->orderBy('assignments.sort_order')
            ->get([
                'assignments.id',
                'assignments.status',
                'assignments.work_id',
                'assignments.decided_at',
                'works.title',
                'works.year',
                'works.venue_name',
                'decisions.decision',
            ])
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'status' => (string) $row->status,
                'status_label' => $this->assignmentStatusLabel((string) $row->status),
                'work_id' => (string) $row->work_id,
                'title' => (string) $row->title,
                'year' => $row->year === null ? null : (int) $row->year,
                'venue_name' => $row->venue_name ? (string) $row->venue_name : null,
                'decision' => $row->decision ? (string) $row->decision : null,
                'decision_label' => $row->decision ? $this->decisionLabel((string) $row->decision) : null,
                'decided_at' => $this->dateString($row->decided_at),
                'href' => route('projects.screening.queue', [$batch->project_id, 'assignment' => (string) $row->id], absolute: false),
            ])
            ->all();
    }

    private function selectedAssignment(
        ProjectScreeningBatch $batch,
        User $actor,
        ?string $selectedAssignmentId,
    ): ?ProjectScreeningAssignment {
        $query = ProjectScreeningAssignment::query()
            ->where('batch_id', $batch->id)
            ->where('assigned_to', $actor->id);

        if ($selectedAssignmentId) {
            $selected = (clone $query)
                ->whereKey($selectedAssignmentId)
                ->first();

            if ($selected instanceof ProjectScreeningAssignment) {
                return $selected;
            }
        }

        return (clone $query)
            ->whereIn('status', [
                ProjectScreeningAssignmentStatus::Pending->value,
                ProjectScreeningAssignmentStatus::InProgress->value,
                ProjectScreeningAssignmentStatus::Decided->value,
            ])
            ->orderBy('sort_order')
            ->first()
            ?? $query->orderBy('sort_order')->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentDetail(ProjectScreeningAssignment $assignment): array
    {
        $assignment->loadMissing(['batch.project']);
        $batchAcceptsDecisions = in_array($assignment->batch->status, [
            ProjectScreeningBatchStatus::Active,
            ProjectScreeningBatchStatus::Conflicts,
        ], true);
        $work = DB::table('scholarly_works')
            ->where('id', $assignment->work_id)
            ->first();
        $decision = $assignment->screening_decision_id
            ? DB::table('screening_decisions')->where('id', $assignment->screening_decision_id)->first()
            : null;

        return [
            'id' => $assignment->id,
            'status' => $assignment->status->value,
            'status_label' => $this->assignmentStatusLabel($assignment->status->value),
            'decision_url' => route('projects.screening.assignments.decision', [$assignment->project_id, $assignment->id], absolute: false),
            'can_record_decision' => $batchAcceptsDecisions && in_array($assignment->status, [
                ProjectScreeningAssignmentStatus::Pending,
                ProjectScreeningAssignmentStatus::InProgress,
                ProjectScreeningAssignmentStatus::Decided,
            ], true),
            'decision' => $decision ? [
                'id' => (string) $decision->id,
                'decision' => (string) $decision->decision,
                'decision_label' => $this->decisionLabel((string) $decision->decision),
                'reason' => $decision->reason ? (string) $decision->reason : null,
                'evidence' => $this->decodeList($decision->evidence),
                'uncertainty' => $this->decodeList($decision->uncertainty),
                'exclusion_basis' => $this->decodeList($decision->exclusion_basis),
                'decided_at' => $this->dateString($decision->decided_at),
            ] : null,
            'work' => [
                'id' => (string) $assignment->work_id,
                'title' => $work?->title ? (string) $work->title : 'Unknown work',
                'abstract' => $work?->abstract ? (string) $work->abstract : null,
                'year' => $work?->year === null ? null : (int) $work->year,
                'venue_name' => $work?->venue_name ? (string) $work->venue_name : null,
                'venue_type' => $work?->venue_type ? (string) $work->venue_type : null,
                'language' => $work?->language ? (string) $work->language : null,
                'cited_by_count' => $work?->cited_by_count === null ? 0 : (int) $work->cited_by_count,
                'identifiers' => $this->identifiers((string) $assignment->work_id),
                'providers' => $this->providers((string) $assignment->work_id),
                'provenance' => $this->snapshotProvenance($assignment->batch, (string) $assignment->work_id),
            ],
        ];
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
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function identifiers(string $workId): array
    {
        return DB::table('work_external_ids')
            ->where('work_id', $workId)
            ->orderByDesc('is_primary')
            ->orderBy('namespace')
            ->get(['namespace', 'value', 'is_primary'])
            ->map(fn (object $row): array => [
                'namespace' => (string) $row->namespace,
                'value' => (string) $row->value,
                'is_primary' => (bool) $row->is_primary,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function providers(string $workId): array
    {
        return DB::table('work_providers')
            ->where('work_id', $workId)
            ->orderBy('provider_alias')
            ->get(['provider_alias', 'provider_work_id'])
            ->map(fn (object $row): array => [
                'provider_alias' => (string) $row->provider_alias,
                'provider_work_id' => $row->provider_work_id ? (string) $row->provider_work_id : null,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function snapshotProvenance(ProjectScreeningBatch $batch, string $workId): array
    {
        $row = DB::table('corpus_snapshot_works')
            ->where('snapshot_id', $batch->snapshot_id)
            ->where('work_id', $workId)
            ->first(['provenance', 'provider_aliases', 'included_at']);

        if (! $row) {
            return [];
        }

        $provenance = collect($this->decodeList($row->provenance))
            ->map(fn (mixed $item): array => is_array($item) ? [
                'query_label' => $item['query_label'] ?? 'Snapshot query',
                'provider_alias' => $item['provider_alias'] ?? null,
                'provider_work_id' => $item['provider_work_id'] ?? null,
                'rank' => isset($item['rank']) ? (int) $item['rank'] : null,
                'seen_at' => $item['seen_at'] ?? $this->dateString($row->included_at),
            ] : [])
            ->filter(fn (array $item): bool => $item !== [])
            ->values()
            ->all();

        if ($provenance !== []) {
            return $provenance;
        }

        return collect($this->decodeList($row->provider_aliases))
            ->map(fn (string $provider): array => [
                'query_label' => 'Snapshot query',
                'provider_alias' => $provider,
                'provider_work_id' => null,
                'rank' => null,
                'seen_at' => $this->dateString($row->included_at),
            ])
            ->values()
            ->all();
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

    private function assignmentStatusLabel(string $status): string
    {
        return match ($status) {
            ProjectScreeningAssignmentStatus::Pending->value => 'Pending',
            ProjectScreeningAssignmentStatus::InProgress->value => 'In progress',
            ProjectScreeningAssignmentStatus::Decided->value => 'Submitted',
            ProjectScreeningAssignmentStatus::Conflict->value => 'Conflict',
            ProjectScreeningAssignmentStatus::Resolved->value => 'Resolved',
            default => str($status)->replace('_', ' ')->title()->toString(),
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
