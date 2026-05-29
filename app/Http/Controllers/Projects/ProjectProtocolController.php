<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\CompleteProjectProtocol;
use App\Actions\Projects\UpdateProjectProtocol;
use App\Enums\ReviewType;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectProtocolController extends Controller
{
    private const PROVIDER_ALIASES = [
        'openalex',
        'crossref',
        'semantic_scholar',
        'arxiv',
        'pubmed',
        'doaj',
        'ieee',
    ];

    public function edit(Request $request, Project $project): Response
    {
        $project->load(['workspace', 'protocol']);

        $this->authorize('view', $project);

        $protocol = $project->protocol()->firstOrFail();

        return Inertia::render('projects/protocol', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'review_type' => $project->review_type?->value,
                'review_type_label' => $project->review_type?->label(),
                'status' => $project->status->value,
                'locked_at' => $project->locked_at?->toISOString(),
                'workspace' => [
                    'id' => $project->workspace->id,
                    'name' => $project->workspace->name,
                ],
                'urls' => [
                    'overview' => route('projects.show', $project, absolute: false),
                    'protocol' => route('projects.protocol.edit', $project, absolute: false),
                    'activity' => route('projects.activity.index', $project, absolute: false),
                ],
            ],
            'protocol' => [
                'id' => $protocol->id,
                'status' => $protocol->status->value,
                'version' => $protocol->version,
                'title' => $protocol->title,
                'research_question' => $protocol->research_question ?? '',
                'background' => $protocol->background ?? '',
                'inclusion_criteria' => $protocol->inclusion_criteria ?? '',
                'exclusion_criteria' => $protocol->exclusion_criteria ?? '',
                'target_providers' => implode(', ', $protocol->target_providers ?? []),
                'date_range_start' => $protocol->date_range_start?->toDateString() ?? '',
                'date_range_end' => $protocol->date_range_end?->toDateString() ?? '',
                'no_date_limit' => $protocol->no_date_limit,
                'language_policy' => $protocol->language_policy ?? '',
                'min_reviewer_count' => $protocol->min_reviewer_count,
                'ai_screening_policy' => $protocol->ai_screening_policy,
                'full_text_policy' => $protocol->full_text_policy,
            ],
            'reviewTypes' => $this->reviewTypeOptions(),
            'can' => [
                'update_protocol' => $request->user()->can('updateProtocol', $project),
                'complete_protocol' => $request->user()->can('completeProtocol', $project),
            ],
        ]);
    }

    public function update(
        Request $request,
        Project $project,
        UpdateProjectProtocol $updateProtocol,
        CompleteProjectProtocol $completeProtocol,
    ): RedirectResponse {
        $project->load(['workspace', 'protocol']);

        $this->authorize('updateProtocol', $project);

        $data = $this->validatedData($request);

        $updateProtocol->handle($project, $request->user(), [
            ...$data,
            'target_providers' => $this->providersFromText($data['target_providers'] ?? ''),
        ], $data['audit_reason'] ?? null);

        if (($data['intent'] ?? 'save') === 'complete') {
            $this->authorize('completeProtocol', $project);
            $completeProtocol->handle($project->refresh(), $request->user());
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Protocol completed.')]);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Protocol saved.')]);
        }

        return back();
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'intent' => ['nullable', Rule::in(['save', 'complete'])],
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'review_type' => ['required', Rule::enum(ReviewType::class)],
            'research_question' => ['nullable', 'string', 'max:5000'],
            'background' => ['nullable', 'string', 'max:10000'],
            'inclusion_criteria' => ['nullable', 'string', 'max:10000'],
            'exclusion_criteria' => ['nullable', 'string', 'max:10000'],
            'target_providers' => [
                'nullable',
                'string',
                'max:1000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $invalid = collect(explode(',', (string) $value))
                        ->map(fn (string $provider): string => $this->normalizeProviderAlias($provider))
                        ->filter()
                        ->reject(fn (string $provider): bool => in_array($provider, self::PROVIDER_ALIASES, true))
                        ->unique()
                        ->values();

                    if ($invalid->isNotEmpty()) {
                        $fail(__('Unsupported providers: :providers.', [
                            'providers' => $invalid->implode(', '),
                        ]));
                    }
                },
            ],
            'date_range_start' => ['nullable', 'date'],
            'date_range_end' => ['nullable', 'date', 'after_or_equal:date_range_start'],
            'no_date_limit' => ['boolean'],
            'language_policy' => ['nullable', 'string', 'max:1000'],
            'min_reviewer_count' => ['required', 'integer', 'min:1', 'max:10'],
            'ai_screening_policy' => ['required', Rule::in(['human_only', 'ai_assisted', 'ai_excluded'])],
            'full_text_policy' => ['required', Rule::in(['optional', 'required_for_inclusion', 'manual_uploads_only'])],
            'audit_reason' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function providersFromText(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $provider): string => $this->normalizeProviderAlias($provider))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeProviderAlias(string $provider): string
    {
        return str_replace('-', '_', strtolower(trim($provider)));
    }

    private function reviewTypeOptions(): array
    {
        return collect(ReviewType::cases())
            ->map(fn (ReviewType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ])
            ->all();
    }
}
