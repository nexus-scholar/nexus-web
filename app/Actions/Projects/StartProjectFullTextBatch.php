<?php

namespace App\Actions\Projects;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ProjectFullTextBatchStatus;
use App\Enums\ProjectFullTextItemStatus;
use App\Jobs\RunProjectFullTextBatchJob;
use App\Models\Project;
use App\Models\ProjectFullTextBatch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StartProjectFullTextBatch
{
    public function __construct(
        private readonly BuildProjectFullTextCandidates $candidateBuilder,
        private readonly RefreshProjectFullTextBatchCounts $refreshCounts,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(Project $project, User $actor): ProjectFullTextBatch
    {
        $project->loadMissing(['workspace', 'protocol']);

        if (! $actor->can('manageFullText', $project)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $project): ProjectFullTextBatch {
            $candidateSet = $this->candidateBuilder->handle($project);

            if (! $candidateSet['ready']) {
                throw ValidationException::withMessages([
                    'full_text' => $candidateSet['blockers'][0] ?? __('Full-text retrieval is not ready for this project.'),
                ]);
            }

            $this->assertNoOpenBatch($project);

            $batchId = (string) Str::uuid();
            $destination = sprintf('full-text/projects/%s/batches/%s', $project->id, $batchId);
            $batch = ProjectFullTextBatch::create([
                'id' => $batchId,
                'project_id' => $project->id,
                'screening_batch_id' => $candidateSet['screening_batch']->id,
                'snapshot_id' => (string) $candidateSet['snapshot']->id,
                'status' => ProjectFullTextBatchStatus::Queued,
                'candidate_count' => count($candidateSet['candidates']),
                'destination_folder' => $destination,
                'source_policy' => $this->sourcePolicy(),
                'requested_by' => $actor->id,
            ]);

            $now = now();
            $rows = collect($candidateSet['candidates'])
                ->map(fn (array $candidate): array => [
                    'id' => (string) Str::uuid(),
                    'project_id' => $project->id,
                    'batch_id' => $batch->id,
                    'work_id' => $candidate['work_id'],
                    'screening_decision' => $candidate['screening_decision'],
                    'status' => ProjectFullTextItemStatus::Queued->value,
                    'metadata' => json_encode([
                        'screening_decision_id' => $candidate['screening_decision_id'] ?? null,
                        'screening_reason' => $candidate['screening_reason'] ?? null,
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            DB::table('project_full_text_items')->insert($rows);
            $batch = $this->refreshCounts->handle($batch);

            $this->audit->handle(
                'project.full_text.batch_started',
                $batch,
                $actor,
                $project->workspace,
                metadata: [
                    'batch_id' => $batch->id,
                    'screening_batch_id' => $batch->screening_batch_id,
                    'snapshot_id' => $batch->snapshot_id,
                    'candidate_count' => $batch->candidate_count,
                ],
                project: $project,
            );

            RunProjectFullTextBatchJob::dispatch($batch->id);

            return $batch;
        });
    }

    private function assertNoOpenBatch(Project $project): void
    {
        $exists = ProjectFullTextBatch::query()
            ->where('project_id', $project->id)
            ->whereIn('status', [
                ProjectFullTextBatchStatus::Queued->value,
                ProjectFullTextBatchStatus::Running->value,
            ])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'full_text' => __('This project already has a queued or running full-text batch.'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function sourcePolicy(): array
    {
        return [
            'legal_open_access_only' => true,
            'sources' => collect(config('nexus.full_text.sources', []))
                ->map(fn (mixed $source): bool => is_array($source)
                    ? (bool) ($source['enabled'] ?? true)
                    : true)
                ->all(),
        ];
    }
}
