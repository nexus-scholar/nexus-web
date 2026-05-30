<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\StartProjectScreeningBatch;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class ProjectScreeningBatchController extends Controller
{
    public function store(
        Request $request,
        Project $project,
        StartProjectScreeningBatch $startBatch,
    ): RedirectResponse {
        $this->authorize('manageScreening', $project);

        $data = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:180'],
            'required_reviewer_count' => ['required', 'integer', 'min:1', 'max:6'],
            'reviewer_ids' => ['required', 'array', 'min:1'],
            'reviewer_ids.*' => ['integer', 'exists:users,id'],
        ])->validate();

        $startBatch->handle(
            $project,
            $request->user(),
            $data['reviewer_ids'],
            (int) $data['required_reviewer_count'],
            $data['name'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Screening batch started.')]);

        return to_route('projects.screening.index', $project);
    }
}
