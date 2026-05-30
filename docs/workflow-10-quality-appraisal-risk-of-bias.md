# Workflow 10: Quality Appraisal And Risk Of Bias

Prepared on 2026-05-30.

Status: prepared for implementation. This workflow appraises included studies
after data extraction is underway or complete, while keeping appraisal decisions
auditable and separate from eligibility decisions.

Wireframes: `docs/wireframes/workflow-10-quality-appraisal-wireframes.html`.

## Goal

Let research teams assess study quality and risk of bias for the included
studies that passed full-text screening.

Workflow 10 answers these questions:

- Which included studies need appraisal?
- Which appraisal tool is official for this project?
- Which domains, signalling questions, ratings, and reasons were recorded?
- Where do independent reviewers disagree?
- Which final appraisal state is ready for synthesis and export?

Quality appraisal is not another exclusion screen. It should describe trust in
the evidence and preserve the reasoning that led to each judgement.

## Sources Checked

- `docs/workflow-8-full-text-screening.md`
- `docs/workflow-9-data-extraction.md`
- `docs/demo-scenarios.md`
- `docs/project-progress.md`
- `repos/core/docs/v1.0/modules/05-core-screening-and-adjudication.md`
- `repos/core/docs/v1.0/modules/07-core-full-text-and-dissemination.md`
- `repos/core/docs/v1.0/modules/08-core-laravel-integration-persistence-jobs-read-apis.md`

## Product Boundary

Nexus Scholar Web owns:

- appraisal tool configuration;
- domain and question templates;
- reviewer appraisal assignments;
- domain-level and overall ratings;
- reasons, notes, and evidence pointers;
- conflict detection and adjudication;
- appraisal matrix and synthesis handoff views;
- demo data, browser scenarios, and UI tests.

`nexus-scholar/core` currently does not own quality appraisal or risk-of-bias
logic. Keep this workflow host-owned until a reusable package boundary becomes
clear.

## Input Contract

Workflow 10 starts when:

- Workflow 8 has a completed full-text screening batch;
- at least one work has final full-text `include`;
- Workflow 9 is at least configured, and preferably has a completed dataset for
  first implementation;
- the actor can manage quality appraisal for the project;
- the workspace is active.

The authoritative appraisal candidate set is:

- final Workflow 8 `include` records.

Data extraction should provide context, but a missing extraction field should
not silently block appraisal unless the project policy requires it.

## Protocol Policy

Recommended settings:

| Setting | First-slice behavior |
| --- | --- |
| appraisal tool | choose one locked template per project |
| assignment mode | `single`, `dual_independent`, or `single_with_verification` |
| overall rating | computed from domains or manually adjudicated |
| evidence pointer requirement | required for high-risk and unclear ratings |
| conflict policy | conflict on different domain rating or different overall rating |

The first slice should ship a small set of configurable templates instead of
pretending one tool fits every review.

Recommended built-in tool presets:

- RoB 2 style randomized-trial domains;
- ROBINS-I style non-randomized domains;
- QUADAS-2 style diagnostic accuracy domains;
- JBI/CASP-style custom checklist;
- custom domain template.

Do not claim formal compliance with a named tool unless the exact required
domains, signalling questions, and rating rules are implemented.

## Data Model

Recommended tables:

- `project_appraisal_templates`
- `project_appraisal_domains`
- `project_appraisal_questions`
- `project_appraisal_batches`
- `project_appraisal_assignments`
- `project_appraisal_responses`
- `project_appraisal_ratings`
- `project_appraisal_conflicts`

### project_appraisal_templates

Purpose: project-specific appraisal tool version.

Recommended columns:

- `id`
- `project_id`
- `version`
- `tool_type`
- `name`
- `status` one of `draft`, `locked`, `retired`
- `assignment_mode`
- `rating_policy` JSON
- `created_by`
- `locked_by`
- `locked_at`
- `lock_reason`
- timestamps

### project_appraisal_domains

Purpose: ordered domains within a template.

Recommended columns:

- `id`
- `template_id`
- `key`
- `label`
- `description`
- `sort_order`
- `requires_evidence_pointer`
- timestamps

### project_appraisal_questions

Purpose: optional signalling questions or checklist items.

Recommended columns:

- `id`
- `domain_id`
- `key`
- `prompt`
- `response_type`
- `options` JSON
- `required`
- `sort_order`
- timestamps

### project_appraisal_batches

Purpose: one appraisal run over included studies.

Recommended columns:

- `id`
- `project_id`
- `full_text_screening_batch_id`
- `extraction_batch_id` nullable
- `template_id`
- `snapshot_id`
- `status` one of `draft`, `active`, `completed`, `locked`
- `candidate_count`
- `assigned_count`
- `completed_count`
- `conflict_count`
- `finalized_count`
- `started_by`
- `started_at`
- `completed_at`
- timestamps

### project_appraisal_assignments

Purpose: one reviewer and one included work.

Recommended columns:

- `id`
- `project_id`
- `batch_id`
- `work_id`
- `reviewer_id`
- `status` one of `assigned`, `in_progress`, `completed`, `returned`
- `started_at`
- `completed_at`
- timestamps

### project_appraisal_responses

Purpose: question-level answers.

Recommended columns:

- `id`
- `project_id`
- `batch_id`
- `assignment_id`
- `work_id`
- `question_id`
- `response` JSON
- `note`
- `evidence_pointer` JSON nullable
- timestamps

### project_appraisal_ratings

Purpose: domain-level and overall judgements.

Recommended columns:

- `id`
- `project_id`
- `batch_id`
- `assignment_id` nullable
- `work_id`
- `domain_id` nullable
- `rating_scope` one of `domain`, `overall`
- `rating` one of `low`, `some_concerns`, `high`, `unclear`,
  `not_applicable`
- `rationale`
- `evidence_pointer` JSON nullable
- `is_final`
- `created_by`
- timestamps

### project_appraisal_conflicts

Purpose: disagreement between reviewers or between reviewer and computed
rating.

Recommended columns:

- `id`
- `project_id`
- `batch_id`
- `work_id`
- `domain_id` nullable
- `rating_scope`
- `status` one of `open`, `resolved`
- `resolution_rating`
- `resolution_rationale`
- `resolved_by`
- `resolved_at`
- timestamps

## Application Services

Recommended classes:

- `App\Actions\Projects\BuildProjectAppraisalCandidates`
- `App\Actions\Projects\CreateProjectAppraisalTemplate`
- `App\Actions\Projects\LockProjectAppraisalTemplate`
- `App\Actions\Projects\StartProjectAppraisalBatch`
- `App\Actions\Projects\RecordProjectAppraisalResponse`
- `App\Actions\Projects\CompleteProjectAppraisalAssignment`
- `App\Actions\Projects\ResolveProjectAppraisalConflict`
- `App\Queries\Projects\ProjectAppraisalReadModel`
- `App\Queries\Projects\ProjectAppraisalQueueReadModel`
- `App\Queries\Projects\ProjectAppraisalMatrixReadModel`

### BuildProjectAppraisalCandidates

Responsibilities:

- load final full-text included studies;
- attach extraction status when available;
- expose missing extraction fields as warnings, not silent blockers;
- preserve snapshot provenance;
- preserve deterministic ordering.

### LockProjectAppraisalTemplate

Responsibilities:

- validate domains and required rating options;
- validate signalling questions;
- require an audit reason;
- freeze the template version before assignments start.

### StartProjectAppraisalBatch

Responsibilities:

- require a locked appraisal template;
- create assignments according to assignment mode;
- link to full-text screening and optionally extraction batch;
- record `project.appraisal.batch_started`.

### RecordProjectAppraisalResponse

Responsibilities:

- assert assigned reviewer access;
- validate question response shape;
- validate rating values;
- require rationale for non-low or unclear ratings when configured;
- require evidence pointer for high-risk or unclear ratings when configured;
- update assignment progress.

### ResolveProjectAppraisalConflict

Responsibilities:

- assert owner, workspace admin, or adjudicator access;
- show reviewer ratings, reasons, and evidence pointers;
- require resolution rating and audit reason;
- persist final rating;
- record `project.appraisal.conflict_resolved`.

## Route And Policy

Recommended routes:

- `GET /projects/{project}/appraisal`
- `POST /projects/{project}/appraisal/templates`
- `PATCH /projects/{project}/appraisal/templates/{template}`
- `POST /projects/{project}/appraisal/templates/{template}/lock`
- `POST /projects/{project}/appraisal/batches`
- `GET /projects/{project}/appraisal/queue`
- `POST /projects/{project}/appraisal/assignments/{assignment}/responses`
- `POST /projects/{project}/appraisal/assignments/{assignment}/complete`
- `GET /projects/{project}/appraisal/conflicts`
- `POST /projects/{project}/appraisal/conflicts/{conflict}/resolve`
- `GET /projects/{project}/appraisal/matrix`

Recommended policy methods:

- `viewAppraisal`
- `manageAppraisal`
- `appraiseAssignedStudy`
- `resolveAppraisalConflict`
- `viewAppraisalMatrix`

## UI Shape

Use an appraisal matrix plus reviewer workbench.

Primary surfaces:

- appraisal readiness page;
- tool/template setup;
- reviewer assignment setup;
- reviewer appraisal queue;
- domain matrix;
- conflict resolution sheet;
- synthesis handoff summary.

Reviewer queue layout:

- study list with extraction status and full-text outcome;
- domain tabs or segmented controls;
- signalling questions above domain rating;
- rating control with clear status colors;
- rationale and evidence pointer fields;
- hidden right-side source sheet for full text and extraction context.

Matrix layout:

- studies as rows;
- domains as columns;
- badges for low, some concerns, high, unclear, and not applicable;
- filters for rating, domain, reviewer, and conflict state;
- column visibility and horizontal scrolling;
- row detail sheet for reasons and evidence pointers.

## UX States

### Not Ready

No final full-text include set exists. Link back to Workflow 8.

### Ready Without Appraisal Tool

Show included-study count and prompt owners/admins to choose or build a tool.

### Draft Tool

Allow editing and preview. Do not start reviewer assignments.

### Active Appraisal

Show reviewer workload, domain progress, unresolved conflicts, and extraction
context warnings.

### Reviewer Queue

Show one study and one domain group at a time. Save question responses and
ratings without forcing a full-page reload.

### Conflict Review

Show reviewer ratings side by side and resolve through a right-side sheet.

### Completed Matrix

Show final ratings by domain and overall study judgement. The next action
becomes synthesis and export setup.

## Demo Data

Extend `DemoAccessSeeder` during implementation.

Required seeded states:

- completed full-text eligibility handoff without appraisal;
- locked appraisal template;
- active appraisal batch with reviewer assignments;
- completed appraisal assignment;
- open domain-rating conflict;
- resolved appraisal conflict;
- completed appraisal matrix ready for synthesis;
- viewer read-only appraisal matrix.

Use deterministic fake reasons and evidence pointers. Do not imply official
RoB 2, ROBINS-I, or QUADAS-2 compliance unless the exact rules are encoded.

## Browser Scenarios

Add these to `docs/demo-scenarios.md` during implementation:

1. Owner opens appraisal before full-text screening completion and sees a
   blocker.
2. Owner chooses an appraisal tool for a completed included-study set.
3. Owner previews and locks the appraisal tool with an audit reason.
4. Owner starts an appraisal batch.
5. Reviewer opens the appraisal queue and records domain responses and ratings.
6. Reviewer completes an appraisal assignment.
7. Different reviewer ratings create an open conflict.
8. Adjudicator resolves an appraisal conflict with an audit reason.
9. Owner opens the completed appraisal matrix.
10. Viewer sees read-only appraisal progress and final matrix.

Screenshot targets:

- `output/playwright/workflow-10-appraisal-readiness.png`
- `output/playwright/workflow-10-tool-setup.png`
- `output/playwright/workflow-10-appraisal-queue.png`
- `output/playwright/workflow-10-source-context-sheet.png`
- `output/playwright/workflow-10-conflict-resolution.png`
- `output/playwright/workflow-10-appraisal-matrix.png`
- `output/playwright/workflow-10-viewer-readonly.png`

## Automated Tests

Pest feature tests:

- appraisal is blocked until final full-text includes exist;
- only final full-text includes are appraisal candidates;
- owner/admin can configure and lock a tool;
- reviewers/viewers cannot lock a tool;
- appraisal cannot start with a draft tool;
- assignments follow assignment mode;
- reviewer can record assigned responses and ratings;
- reviewer cannot edit another reviewer's assignment;
- high-risk and unclear ratings require rationale and evidence pointer when
  configured;
- rating disagreement creates an open conflict;
- adjudicator can resolve conflict with audit reason;
- completed matrix read model exposes final ratings.

Vitest component tests:

- appraisal readiness summary;
- appraisal tool editor;
- domain rating control;
- appraisal queue domain tabs;
- source context sheet;
- appraisal conflict sheet;
- appraisal matrix table.

Browser verification:

- owner tool setup;
- reviewer appraisal queue;
- conflict resolution flow;
- matrix read-only view.

## First Implementation Slice

1. Add appraisal tables and models.
2. Add candidate builder from final full-text includes.
3. Add appraisal template setup, preview, and lock.
4. Add batch setup and assignment generation.
5. Add reviewer appraisal queue.
6. Add response and rating recording.
7. Add conflict detection and resolution.
8. Add matrix read model.
9. Extend demo seed data.
10. Add Pest and component coverage.
11. Update `docs/demo-scenarios.md`.
12. Verify with browser screenshots.

## Explicit Non-Goals

Do not implement these in the first slice:

- automatic risk-of-bias judgement;
- formal GRADE certainty assessment;
- meta-analysis;
- publication-ready report generation;
- external appraisal-tool import;
- public sharing;
- billing or quota logic.

## Risks

- Tool names carry scientific expectations. Avoid claiming compliance until
  exact domains and rules are implemented.
- Quality appraisal should not retroactively change eligibility decisions
  without a separate protocol amendment.
- Rating values must be normalized enough for export and synthesis.
- Domain matrices can become wide. Use horizontal scrolling and column
  visibility controls.
- Evidence pointers should link back to full-text artifacts and extraction
  context without loading all details for every row.

## Readiness Checklist

Before implementation starts:

- Workflow 9 scope is accepted;
- first appraisal tool presets are accepted;
- assignment mode and conflict policy are accepted;
- rating vocabulary is accepted;
- browser scenarios and screenshot names are accepted.

Validation gates before commit:

```powershell
composer test
npm run format:check
npm run lint:check
npm run types:check
npm run test:ui
npm run build:check
composer validate --strict
composer audit --format=plain --abandoned=ignore
git diff --check
```
