<?php

namespace App\Actions\Projects;

use App\Enums\ProjectFullTextBatchStatus;
use App\Enums\ProjectFullTextItemStatus;
use App\Models\Project;
use App\Models\ProjectFullTextBatch;
use App\Models\ProjectFullTextItem;
use App\Models\ProjectScreeningBatch;
use Illuminate\Support\Collection;

class BuildProjectFullTextScreeningCandidates
{
    public function __construct(
        private readonly BuildProjectFullTextCandidates $retrievalCandidates,
    ) {}

    /**
     * @return array{
     *     ready: bool,
     *     blockers: list<string>,
     *     title_abstract_batch: ProjectScreeningBatch|null,
     *     full_text_batch: ProjectFullTextBatch|null,
     *     snapshot: object|null,
     *     counts: array<string, int>,
     *     follow_up: array<string, int>,
     *     candidates: list<array<string, mixed>>
     * }
     */
    public function handle(Project $project): array
    {
        $candidateSet = $this->retrievalCandidates->handle($project);
        $fullTextBatch = $this->latestBatch($project);
        $blockers = $candidateSet['blockers'];

        if (! $fullTextBatch instanceof ProjectFullTextBatch) {
            $blockers[] = 'Complete full-text retrieval before full-text screening.';
        } elseif ($fullTextBatch->status->isOpen()) {
            $blockers[] = 'Wait for the full-text retrieval batch to finish before assigning reviewers.';
        } elseif ($fullTextBatch->status === ProjectFullTextBatchStatus::Cancelled) {
            $blockers[] = 'The latest full-text retrieval batch was cancelled.';
        }

        if ($fullTextBatch instanceof ProjectFullTextBatch && $candidateSet['screening_batch'] instanceof ProjectScreeningBatch) {
            if ((string) $fullTextBatch->screening_batch_id !== (string) $candidateSet['screening_batch']->id) {
                $blockers[] = 'The latest full-text retrieval batch does not match the completed title and abstract handoff.';
            }
        }

        if ($fullTextBatch instanceof ProjectFullTextBatch && $candidateSet['snapshot']) {
            if ((string) $fullTextBatch->snapshot_id !== (string) $candidateSet['snapshot']->id) {
                $blockers[] = 'The latest full-text retrieval batch is not tied to the latest locked snapshot.';
            }
        }

        $items = $fullTextBatch instanceof ProjectFullTextBatch ? $this->itemsForBatch($fullTextBatch) : collect();
        $itemsByWork = $items->keyBy('work_id');
        $screenable = collect($candidateSet['candidates'])
            ->map(function (array $candidate) use ($itemsByWork): ?array {
                $item = $itemsByWork->get($candidate['work_id']);

                if (! $item instanceof ProjectFullTextItem || $item->status !== ProjectFullTextItemStatus::Success) {
                    return null;
                }

                if (! $item->artifact_path) {
                    return null;
                }

                return [
                    ...$candidate,
                    'full_text_item' => $this->itemPayload($item),
                ];
            })
            ->filter()
            ->values()
            ->all();

        $followUp = $this->followUpCounts($candidateSet['candidates'], $items);

        if ($fullTextBatch instanceof ProjectFullTextBatch
            && ! $fullTextBatch->status->isOpen()
            && $blockers === []
            && $screenable === []) {
            $blockers[] = 'No successful full-text artifacts are ready for reviewer screening.';
        }

        return [
            'ready' => $blockers === [],
            'blockers' => array_values(array_unique($blockers)),
            'title_abstract_batch' => $candidateSet['screening_batch'],
            'full_text_batch' => $fullTextBatch,
            'snapshot' => $candidateSet['snapshot'],
            'counts' => [
                'title_abstract_candidates' => $candidateSet['counts']['candidate_count'],
                'screenable' => count($screenable),
                'include' => collect($screenable)->where('screening_decision', 'include')->count(),
                'needs_review' => collect($screenable)->where('screening_decision', 'needs_review')->count(),
                'excluded_at_title_abstract' => $candidateSet['counts']['excluded'],
            ],
            'follow_up' => $followUp,
            'candidates' => $screenable,
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
     * @return Collection<int, ProjectFullTextItem>
     */
    private function itemsForBatch(ProjectFullTextBatch $batch): Collection
    {
        return ProjectFullTextItem::query()
            ->where('batch_id', $batch->id)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @param  Collection<int, ProjectFullTextItem>  $items
     * @return array<string, int>
     */
    private function followUpCounts(array $candidates, Collection $items): array
    {
        $counts = [
            ProjectFullTextItemStatus::Failed->value => 0,
            ProjectFullTextItemStatus::Skipped->value => 0,
            ProjectFullTextItemStatus::ManualNeeded->value => 0,
            'missing_item' => 0,
            'total' => 0,
        ];
        $itemsByWork = $items->keyBy('work_id');

        foreach ($candidates as $candidate) {
            $item = $itemsByWork->get($candidate['work_id']);

            if (! $item instanceof ProjectFullTextItem) {
                $counts['missing_item']++;

                continue;
            }

            if (array_key_exists($item->status->value, $counts)) {
                $counts[$item->status->value]++;
            }
        }

        $counts['total'] = $counts[ProjectFullTextItemStatus::Failed->value]
            + $counts[ProjectFullTextItemStatus::Skipped->value]
            + $counts[ProjectFullTextItemStatus::ManualNeeded->value]
            + $counts['missing_item'];

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(ProjectFullTextItem $item): array
    {
        return [
            'id' => $item->id,
            'status' => $item->status->value,
            'status_label' => $item->status->label(),
            'source_alias' => $item->source_alias,
            'artifact_type' => $item->artifact_type,
            'artifact_path' => $item->artifact_path,
            'download_url' => $item->artifact_path
                ? route('projects.full-text.artifacts.show', [$item->project_id, $item->id], absolute: false)
                : null,
            'completed_at' => $item->completed_at?->toISOString(),
            'metadata' => $item->metadata ?? [],
        ];
    }
}
