<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\RecordProjectScreeningDecision;
use App\Enums\ProjectScreeningAssignmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectScreeningAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Nexus\Screening\Domain\ScreeningDecision;

class ProjectScreeningAssignmentDecisionController extends Controller
{
    public function store(
        Request $request,
        Project $project,
        ProjectScreeningAssignment $assignment,
        RecordProjectScreeningDecision $recordDecision,
    ): RedirectResponse {
        abort_unless($assignment->project_id === $project->id, 404);

        $data = $request->validate([
            'decision' => ['required', Rule::in([
                ScreeningDecision::INCLUDE->value,
                ScreeningDecision::NEEDS_REVIEW->value,
                ScreeningDecision::EXCLUDE->value,
            ])],
            'reason' => ['required', 'string', 'min:3', 'max:5000'],
            'evidence' => ['nullable', 'string', 'max:5000'],
            'uncertainty' => ['nullable', 'string', 'max:5000'],
            'exclusion_basis' => ['nullable', 'string', 'max:5000'],
        ]);

        $recordDecision->handle(
            $assignment,
            $request->user(),
            $data['decision'],
            $data['reason'],
            $this->lines($data['evidence'] ?? null),
            $this->lines($data['uncertainty'] ?? null),
            $this->lines($data['exclusion_basis'] ?? null),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Screening decision recorded.')]);

        return to_route('projects.screening.queue', [
            'project' => $project,
            'assignment' => $this->nextAssignmentId($assignment) ?? $assignment->id,
        ]);
    }

    /**
     * @return list<string>
     */
    private function lines(?string $value): array
    {
        if (! $value) {
            return [];
        }

        return collect(preg_split('/\R/', $value) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    private function nextAssignmentId(ProjectScreeningAssignment $assignment): ?string
    {
        return ProjectScreeningAssignment::query()
            ->where('batch_id', $assignment->batch_id)
            ->where('assigned_to', $assignment->assigned_to)
            ->where('status', ProjectScreeningAssignmentStatus::Pending->value)
            ->orderBy('sort_order')
            ->value('id');
    }
}
