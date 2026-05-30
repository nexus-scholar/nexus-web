<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\ResolveProjectScreeningConflict;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectScreeningConflict;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Nexus\Screening\Domain\ScreeningDecision;

class ProjectScreeningConflictResolutionController extends Controller
{
    public function store(
        Request $request,
        Project $project,
        ProjectScreeningConflict $conflict,
        ResolveProjectScreeningConflict $resolveConflict,
    ): RedirectResponse {
        abort_unless($conflict->project_id === $project->id, 404);

        $data = $request->validate([
            'decision' => ['required', Rule::in([
                ScreeningDecision::INCLUDE->value,
                ScreeningDecision::NEEDS_REVIEW->value,
                ScreeningDecision::EXCLUDE->value,
            ])],
            'reason' => ['required', 'string', 'min:8', 'max:5000'],
            'evidence' => ['nullable', 'string', 'max:5000'],
            'uncertainty' => ['nullable', 'string', 'max:5000'],
            'exclusion_basis' => ['nullable', 'string', 'max:5000'],
        ]);

        $resolved = $resolveConflict->handle(
            $conflict,
            $request->user(),
            $data['decision'],
            $data['reason'],
            $this->lines($data['evidence'] ?? null),
            $this->lines($data['uncertainty'] ?? null),
            $this->lines($data['exclusion_basis'] ?? null),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Screening conflict resolved.')]);

        return to_route('projects.screening.index', [
            'project' => $project,
            'conflict' => $resolved->id,
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
}
