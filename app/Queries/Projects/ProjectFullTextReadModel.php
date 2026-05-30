<?php

namespace App\Queries\Projects;

use App\Actions\Projects\BuildProjectFullTextCandidates;
use App\Models\Project;
use App\Models\ProjectFullTextBatch;
use App\Models\ProjectFullTextItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Nexus\Dissemination\Domain\FullTextFetchRecord;
use Nexus\Dissemination\Domain\Port\FullTextFetchReaderPort;

final class ProjectFullTextReadModel
{
    public function __construct(
        private readonly BuildProjectFullTextCandidates $candidateBuilder,
        private readonly FullTextFetchReaderPort $fetchReader,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forProject(Project $project, User $actor, ?string $selectedItemId = null): array
    {
        $project->loadMissing(['protocol', 'workspace']);
        $candidateSet = $this->candidateBuilder->handle($project);
        $batch = $this->latestBatch($project);
        $items = $batch instanceof ProjectFullTextBatch
            ? $this->batchItems($batch)
            : $this->candidateItems($candidateSet['candidates']);
        $selectedItem = $this->selectedItem($items, $selectedItemId);

        if ($selectedItem) {
            $selectedItem['source_attempts'] = $this->sourceAttempts($selectedItem['work']['id']);
        }

        return [
            'readiness' => [
                'ready' => $candidateSet['ready'],
                'blockers' => $candidateSet['blockers'],
                'counts' => $candidateSet['counts'],
                'screening_batch' => $candidateSet['screening_batch'] ? [
                    'id' => $candidateSet['screening_batch']->id,
                    'completed_at' => $this->dateString($candidateSet['screening_batch']->completed_at),
                    'status' => $candidateSet['screening_batch']->status->value,
                    'status_label' => $this->statusLabel($candidateSet['screening_batch']->status->value),
                ] : null,
                'snapshot' => $candidateSet['snapshot'] ? [
                    'id' => (string) $candidateSet['snapshot']->id,
                    'locked_at' => $this->dateString($candidateSet['snapshot']->locked_at),
                    'work_count' => (int) $candidateSet['snapshot']->work_count,
                ] : null,
            ],
            'protocol' => [
                'full_text_policy' => $project->protocol?->full_text_policy ?? 'optional',
                'full_text_policy_label' => $this->fullTextPolicyLabel($project->protocol?->full_text_policy ?? 'optional'),
            ],
            'sourcePolicy' => $this->sourcePolicy(),
            'batch' => $batch ? $this->batchPayload($batch) : null,
            'items' => $items,
            'selectedItem' => $selectedItem,
            'recentAuditEvents' => $this->recentAuditEvents($project),
            'actor' => [
                'id' => $actor->id,
                'project_role' => $actor->projectRole($project)?->value,
            ],
        ];
    }

    private function latestBatch(Project $project): ?ProjectFullTextBatch
    {
        return ProjectFullTextBatch::query()
            ->where('project_id', $project->id)
            ->latest('created_at')
            ->latest()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function batchPayload(ProjectFullTextBatch $batch): array
    {
        $terminal = $batch->success_count + $batch->failed_count + $batch->skipped_count + $batch->manual_needed_count;

        return [
            'id' => $batch->id,
            'status' => $batch->status->value,
            'status_label' => $batch->status->label(),
            'screening_batch_id' => $batch->screening_batch_id,
            'snapshot_id' => $batch->snapshot_id,
            'candidate_count' => $batch->candidate_count,
            'success_count' => $batch->success_count,
            'failed_count' => $batch->failed_count,
            'skipped_count' => $batch->skipped_count,
            'manual_needed_count' => $batch->manual_needed_count,
            'progress_percent' => $batch->candidate_count > 0 ? (int) round(($terminal / $batch->candidate_count) * 100) : 0,
            'destination_folder' => $batch->destination_folder,
            'started_at' => $this->dateString($batch->started_at),
            'completed_at' => $this->dateString($batch->completed_at),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function batchItems(ProjectFullTextBatch $batch): array
    {
        $items = ProjectFullTextItem::query()
            ->where('batch_id', $batch->id)
            ->orderByRaw('case status when ? then 0 when ? then 1 when ? then 2 when ? then 3 when ? then 4 else 5 end', [
                'failed',
                'skipped',
                'manual_needed',
                'queued',
                'running',
            ])
            ->orderBy('created_at')
            ->get();

        if ($items->isEmpty()) {
            return [];
        }

        $workIds = $items->pluck('work_id')->map(fn (mixed $id): string => (string) $id)->all();
        $works = DB::table('scholarly_works')
            ->whereIn('id', $workIds)
            ->get(['id', 'title', 'abstract', 'year', 'venue_name', 'venue_type', 'language', 'cited_by_count', 'is_retracted'])
            ->keyBy('id');
        $identifiers = $this->identifiersByWork($workIds);
        $providers = $this->providersByWork($workIds);

        return $items
            ->map(fn (ProjectFullTextItem $item): array => $this->itemPayload(
                $item,
                $works[$item->work_id] ?? null,
                $identifiers[$item->work_id] ?? [],
                $providers[$item->work_id] ?? [],
            ))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    private function candidateItems(array $candidates): array
    {
        return collect($candidates)
            ->map(fn (array $candidate): array => [
                'id' => 'candidate:'.$candidate['work_id'],
                'work_id' => $candidate['work_id'],
                'screening_decision' => $candidate['screening_decision'],
                'screening_decision_label' => $candidate['screening_decision_label'],
                'screening_reason' => $candidate['screening_reason'],
                'status' => 'not_started',
                'status_label' => 'Not started',
                'source_alias' => null,
                'artifact_type' => null,
                'artifact_path' => null,
                'http_status' => null,
                'error_message' => null,
                'metadata' => [],
                'download_url' => null,
                'started_at' => null,
                'completed_at' => null,
                'work' => $candidate['work'],
                'source_attempts' => [],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function selectedItem(array $items, ?string $selectedItemId): ?array
    {
        if (! $selectedItemId) {
            return null;
        }

        return collect($items)->firstWhere('id', $selectedItemId);
    }

    /**
     * @param  list<array<string, mixed>>  $identifiers
     * @param  list<array<string, mixed>>  $providers
     * @return array<string, mixed>
     */
    private function itemPayload(ProjectFullTextItem $item, ?object $work, array $identifiers, array $providers): array
    {
        return [
            'id' => $item->id,
            'work_id' => $item->work_id,
            'screening_decision' => $item->screening_decision,
            'screening_decision_label' => $this->decisionLabel($item->screening_decision),
            'screening_reason' => $item->metadata['screening_reason'] ?? null,
            'status' => $item->status->value,
            'status_label' => $item->status->label(),
            'source_alias' => $item->source_alias,
            'artifact_type' => $item->artifact_type,
            'artifact_path' => $item->artifact_path,
            'http_status' => $item->http_status,
            'error_message' => $item->error_message,
            'metadata' => $item->metadata ?? [],
            'download_url' => $item->artifact_path && $item->status->value === 'success'
                ? route('projects.full-text.artifacts.show', [$item->project_id, $item->id], absolute: false)
                : null,
            'started_at' => $this->dateString($item->started_at),
            'completed_at' => $this->dateString($item->completed_at),
            'work' => [
                'id' => $item->work_id,
                'title' => $work?->title ? (string) $work->title : 'Unknown work',
                'abstract' => $work?->abstract ? (string) $work->abstract : null,
                'year' => $work?->year === null ? null : (int) $work->year,
                'venue_name' => $work?->venue_name ? (string) $work->venue_name : null,
                'venue_type' => $work?->venue_type ? (string) $work->venue_type : null,
                'language' => $work?->language ? (string) $work->language : null,
                'cited_by_count' => $work?->cited_by_count === null ? 0 : (int) $work->cited_by_count,
                'is_retracted' => (bool) ($work?->is_retracted ?? false),
                'identifiers' => $identifiers,
                'providers' => $providers,
            ],
            'source_attempts' => [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sourceAttempts(string $workId): array
    {
        return collect($this->fetchReader->forWork($workId, 10))
            ->map(fn (FullTextFetchRecord $record): array => [
                'id' => $record->id,
                'source_alias' => $record->sourceAlias,
                'source_url' => $record->sourceUrl,
                'status' => $record->status->value,
                'status_label' => str($record->status->value)->replace('_', ' ')->title()->toString(),
                'http_status' => $record->httpStatus,
                'file_path' => $record->filePath,
                'duration_ms' => $record->durationMs,
                'error_message' => $record->errorMessage,
                'attempted_at' => $record->attemptedAt->format(DATE_ATOM),
                'metadata' => $record->metadata,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentAuditEvents(Project $project): array
    {
        return DB::table('audit_events')
            ->where('project_id', $project->id)
            ->where('event_type', 'like', 'project.full_text.%')
            ->latest('occurred_at')
            ->limit(5)
            ->get(['id', 'event_type', 'reason', 'occurred_at'])
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'event_type' => (string) $row->event_type,
                'label' => str((string) $row->event_type)->after('project.full_text.')->replace('_', ' ')->title()->toString(),
                'reason' => $row->reason ? (string) $row->reason : null,
                'occurred_at' => $this->dateString($row->occurred_at),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sourcePolicy(): array
    {
        return collect(config('nexus.full_text.sources', []))
            ->reject(fn (mixed $_value, string $alias): bool => $alias === 'shadow_libraries')
            ->map(fn (mixed $source, string $alias): array => [
                'alias' => $alias,
                'label' => str($alias)->replace('_', ' ')->title()->toString(),
                'enabled' => is_array($source) ? (bool) ($source['enabled'] ?? true) : true,
            ])
            ->values()
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
            'include' => 'Include',
            'needs_review' => 'Maybe',
            'exclude' => 'Exclude',
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

    private function statusLabel(string $status): string
    {
        return str($status)->replace('_', ' ')->title()->toString();
    }

    private function dateString(mixed $value): ?string
    {
        return $value ? (string) $value : null;
    }
}
