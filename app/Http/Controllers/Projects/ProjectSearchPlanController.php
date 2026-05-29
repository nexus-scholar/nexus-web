<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\EnsureProjectSearchPlan;
use App\Actions\Projects\UpdateProjectSearchPlan;
use App\Enums\ProtocolStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectProtocol;
use App\Models\ProjectSearchPlan;
use App\Models\ProjectSearchPlanQuery;
use App\Support\SearchProviderCatalog;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProjectSearchPlanController extends Controller
{
    public function edit(
        Request $request,
        Project $project,
        EnsureProjectSearchPlan $ensureSearchPlan,
    ): Response|RedirectResponse {
        $project->load(['workspace', 'protocol', 'searchPlan.queries']);

        $this->authorize('viewSearchPlan', $project);

        if (! $this->canOpenSearchPlan($project->protocol)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Complete the protocol before opening the search plan.'),
            ]);

            return to_route('projects.show', $project);
        }

        $plan = $project->searchPlan;

        if (! $plan && $request->user()->can('updateSearchPlan', $project)) {
            $plan = $ensureSearchPlan->handle($project, $request->user());
        }

        abort_unless($plan instanceof ProjectSearchPlan, 404);

        return Inertia::render('projects/search-plan', [
            'project' => $this->projectPayload($project),
            'searchPlan' => $this->searchPlanPayload($plan),
            'can' => [
                'update_search_plan' => $request->user()->can('updateSearchPlan', $project),
                'run_search' => $request->user()->can('runSearch', $project),
            ],
        ]);
    }

    public function update(
        Request $request,
        Project $project,
        EnsureProjectSearchPlan $ensureSearchPlan,
        UpdateProjectSearchPlan $updateSearchPlan,
    ): RedirectResponse {
        $project->load(['workspace', 'protocol', 'searchPlan.queries']);

        $this->authorize('updateSearchPlan', $project);

        if (! $this->canOpenSearchPlan($project->protocol)) {
            throw ValidationException::withMessages([
                'search_plan' => __('Complete the protocol before editing the search plan.'),
            ]);
        }

        $plan = $project->searchPlan ?? $ensureSearchPlan->handle($project, $request->user());
        $data = $this->validatedData($request);

        $updateSearchPlan->handle($project, $plan, $request->user(), $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Search plan saved.')]);

        return back();
    }

    private function canOpenSearchPlan(?ProjectProtocol $protocol): bool
    {
        if (! $protocol || ! $protocol->isReadyForSearch()) {
            return false;
        }

        return in_array($protocol->status, [ProtocolStatus::Complete, ProtocolStatus::Amended], true);
    }

    private function validatedData(Request $request): array
    {
        $maxYear = now()->year + 1;

        $data = $request->validate([
            'default_providers' => ['nullable', 'string', 'max:1000', $this->providerValidationRule()],
            'default_year_from' => ['nullable', 'integer', 'min:1800', "max:{$maxYear}"],
            'default_year_to' => ['nullable', 'integer', 'min:1800', "max:{$maxYear}"],
            'default_result_limit' => ['required', 'integer', 'min:1', 'max:500'],
            'include_raw_data' => ['boolean'],
            'queries' => ['required', 'array', 'min:1', 'max:20'],
            'queries.*.query_key' => ['required', 'string', 'max:80', 'distinct', 'regex:/^[a-z0-9][a-z0-9_-]{0,79}$/'],
            'queries.*.label' => ['required', 'string', 'min:2', 'max:180'],
            'queries.*.query' => ['required', 'string', 'min:3', 'max:10000'],
            'queries.*.providers' => ['nullable', 'string', 'max:1000', $this->providerValidationRule()],
            'queries.*.year_from' => ['nullable', 'integer', 'min:1800', "max:{$maxYear}"],
            'queries.*.year_to' => ['nullable', 'integer', 'min:1800', "max:{$maxYear}"],
            'queries.*.result_limit' => ['required', 'integer', 'min:1', 'max:500'],
            'queries.*.include_raw_data' => ['boolean'],
        ]);

        $this->validateYearOrder($data);

        $defaultProviders = SearchProviderCatalog::fromText($data['default_providers'] ?? '');

        return [
            'default_providers' => $defaultProviders,
            'default_year_from' => $this->nullableInteger($data['default_year_from'] ?? null),
            'default_year_to' => $this->nullableInteger($data['default_year_to'] ?? null),
            'default_result_limit' => (int) $data['default_result_limit'],
            'include_raw_data' => $this->booleanValue($data['include_raw_data'] ?? false),
            'queries' => collect($data['queries'])
                ->map(fn (array $query): array => [
                    'query_key' => strtolower($query['query_key']),
                    'label' => $query['label'],
                    'query' => $query['query'],
                    'providers' => SearchProviderCatalog::fromText($query['providers'] ?? '') ?: $defaultProviders,
                    'year_from' => $this->nullableInteger($query['year_from'] ?? null),
                    'year_to' => $this->nullableInteger($query['year_to'] ?? null),
                    'result_limit' => (int) $query['result_limit'],
                    'include_raw_data' => $this->booleanValue($query['include_raw_data'] ?? false),
                ])
                ->values()
                ->all(),
        ];
    }

    private function providerValidationRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $invalid = SearchProviderCatalog::unsupportedFromText((string) $value);

            if ($invalid !== []) {
                $fail(__('Unsupported providers: :providers.', [
                    'providers' => implode(', ', $invalid),
                ]));
            }
        };
    }

    private function validateYearOrder(array $data): void
    {
        $errors = [];
        $defaultFrom = $this->nullableInteger($data['default_year_from'] ?? null);
        $defaultTo = $this->nullableInteger($data['default_year_to'] ?? null);

        if ($defaultFrom !== null && $defaultTo !== null && $defaultTo < $defaultFrom) {
            $errors['default_year_to'] = __('The default end year must be greater than or equal to the default start year.');
        }

        foreach ($data['queries'] ?? [] as $index => $query) {
            $from = $this->nullableInteger($query['year_from'] ?? null);
            $to = $this->nullableInteger($query['year_to'] ?? null);

            if ($from !== null && $to !== null && $to < $from) {
                $errors["queries.{$index}.year_to"] = __('The query end year must be greater than or equal to the query start year.');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function nullableInteger(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function booleanValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function projectPayload(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'review_type' => $project->review_type?->value,
            'review_type_label' => $project->review_type?->label(),
            'status' => $project->status->value,
            'status_label' => $project->status->label(),
            'locked_at' => $project->locked_at?->toISOString(),
            'workspace' => [
                'id' => $project->workspace->id,
                'name' => $project->workspace->name,
            ],
            'urls' => [
                'overview' => route('projects.show', $project, absolute: false),
                'protocol' => route('projects.protocol.edit', $project, absolute: false),
                'search_plan' => route('projects.search-plan.edit', $project, absolute: false),
                'search_runs' => route('projects.search-runs.store', $project, absolute: false),
                'activity' => route('projects.activity.index', $project, absolute: false),
            ],
            'protocol' => [
                'id' => $project->protocol?->id,
                'status' => $project->protocol?->status->value,
                'status_label' => $project->protocol?->status->label(),
                'version' => $project->protocol?->version,
                'completed_at' => $project->protocol?->completed_at?->toISOString(),
            ],
        ];
    }

    private function searchPlanPayload(ProjectSearchPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'status' => $plan->status->value,
            'status_label' => $plan->status->label(),
            'version' => $plan->version,
            'default_providers' => implode(', ', $plan->default_providers ?? []),
            'default_year_from' => $this->stringValue($plan->default_year_from),
            'default_year_to' => $this->stringValue($plan->default_year_to),
            'default_result_limit' => $plan->default_result_limit,
            'include_raw_data' => $plan->include_raw_data,
            'queries' => $plan->queries
                ->map(fn (ProjectSearchPlanQuery $query): array => [
                    'id' => $query->id,
                    'query_key' => $query->query_key,
                    'label' => $query->label,
                    'query' => $query->query,
                    'providers' => implode(', ', $query->providers ?? []),
                    'year_from' => $this->stringValue($query->year_from),
                    'year_to' => $this->stringValue($query->year_to),
                    'result_limit' => $query->result_limit,
                    'include_raw_data' => $query->include_raw_data,
                ])
                ->values()
                ->all(),
        ];
    }

    private function stringValue(?int $value): string
    {
        return $value === null ? '' : (string) $value;
    }
}
