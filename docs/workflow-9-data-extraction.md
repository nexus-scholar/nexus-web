# Workflow 9: Data Extraction

Prepared on 2026-05-30.

Status: prepared for implementation. This workflow starts after full-text
eligibility decisions are stable and turns included studies into structured,
auditable extraction records.

Wireframes: `docs/wireframes/workflow-9-data-extraction-wireframes.html`.

## Goal

Let research teams extract structured evidence from studies that passed
full-text screening.

Workflow 9 answers these questions:

- Which final included full-text studies are ready for extraction?
- Which extraction template is official for this project?
- Which reviewer extracted which field, from which source, and with which
  evidence pointer?
- Which extraction values conflict and need adjudication?
- Which extracted dataset is ready for quality appraisal, synthesis, and
  export?

Data extraction is a scientific workflow, not a spreadsheet side task. The app
must preserve provenance, reviewer ownership, field versions, and adjudication
history.

## Sources Checked

- `docs/workflow-6-title-abstract-screening.md`
- `docs/workflow-7-full-text-retrieval.md`
- `docs/workflow-8-full-text-screening.md`
- `docs/demo-scenarios.md`
- `docs/project-progress.md`
- `docs/core-cli-workflow-scan.md`
- `repos/core/docs/v1.0/modules/05-core-screening-and-adjudication.md`
- `repos/core/docs/v1.0/modules/07-core-full-text-and-dissemination.md`
- `repos/core/docs/v1.0/modules/08-core-laravel-integration-persistence-jobs-read-apis.md`
- `repos/core/docs/v1.0/tutorials/advanced-full-text-retrieval-and-export-audit.md`
- `repos/nexus-cli/docs/commands.md`

## Product Boundary

Nexus Scholar Web owns:

- extraction template setup and versioning;
- reviewer extraction assignments;
- field-level extraction values;
- evidence pointers to full-text artifacts, pages, sections, tables, or notes;
- duplicate independent extraction where the protocol requires it;
- conflict detection and adjudication;
- read models for extraction progress and extraction dataset readiness;
- demo data, browser scenarios, and UI tests.

`nexus-scholar/core` owns the upstream facts that extraction must trust:

- locked corpus membership;
- title-and-abstract and full-text screening decisions;
- legal full-text artifact audit;
- export history and storage primitives where reused later.

Do not shell out to `nexus-cli`. Treat CLI and core docs as behavior references
for audit posture, final membership, and exports.

## Input Contract

Workflow 9 starts when:

- Workflow 8 has a completed full-text screening batch;
- final full-text outcomes exist per work;
- at least one work has final `include`;
- the included works still belong to the latest locked representative snapshot;
- the actor can manage data extraction for the project;
- the workspace is active.

The authoritative extraction candidate set is:

- final Workflow 8 `include` records only.

Do not include:

- final full-text `exclude` records;
- final full-text `needs_review` records unless a later protocol explicitly
  permits provisional extraction;
- failed, skipped, or manual-needed retrieval follow-up rows;
- title-and-abstract includes that did not pass full-text screening.

## Protocol Policy

The first implementation should add a project extraction configuration instead
of overloading the existing protocol fields.

Recommended settings:

| Setting | First-slice behavior |
| --- | --- |
| extraction mode | `single`, `dual_independent`, or `single_with_verification` |
| conflict policy | conflict when two required values differ, or when one reviewer marks a field unavailable |
| required evidence pointer | default true for key outcome fields |
| missing value policy | allow `not_reported`, `not_applicable`, and `unclear` with required note |
| template lock policy | extraction can start only after the template is locked |

Changing the template after extraction starts should create a new template
version. Existing values should remain tied to the version reviewers used.

## Data Model

Recommended tables:

- `project_extraction_templates`
- `project_extraction_template_fields`
- `project_extraction_batches`
- `project_extraction_assignments`
- `project_extraction_values`
- `project_extraction_conflicts`

### project_extraction_templates

Purpose: the official extraction form version for a project.

Recommended columns:

- `id`
- `project_id`
- `version`
- `status` one of `draft`, `locked`, `retired`
- `name`
- `description`
- `mode`
- `created_by`
- `locked_by`
- `locked_at`
- `lock_reason`
- timestamps

Recommended uniqueness:

- `project_id`, `version`

### project_extraction_template_fields

Purpose: ordered fields that define the extraction dataset.

Recommended columns:

- `id`
- `template_id`
- `key`
- `label`
- `description`
- `field_type` one of `text`, `number`, `boolean`, `date`, `choice`,
  `multi_choice`, `outcome_measure`, `effect_estimate`, `note`
- `options` JSON
- `required`
- `requires_evidence_pointer`
- `group`
- `sort_order`
- timestamps

Recommended uniqueness:

- `template_id`, `key`

### project_extraction_batches

Purpose: one extraction run over the included full-text outcome set.

Recommended columns:

- `id`
- `project_id`
- `full_text_screening_batch_id`
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

### project_extraction_assignments

Purpose: one reviewer and one included work within the batch.

Recommended columns:

- `id`
- `project_id`
- `batch_id`
- `work_id`
- `reviewer_id`
- `status` one of `assigned`, `in_progress`, `completed`, `returned`
- `source_full_text_item_id`
- `source_screening_decision_id`
- `started_at`
- `completed_at`
- timestamps

Recommended uniqueness:

- `batch_id`, `work_id`, `reviewer_id`

### project_extraction_values

Purpose: field-level extracted values with provenance.

Recommended columns:

- `id`
- `project_id`
- `batch_id`
- `assignment_id`
- `work_id`
- `template_field_id`
- `value` JSON
- `value_status` one of `provided`, `not_reported`, `not_applicable`,
  `unclear`
- `evidence_pointer` JSON nullable
- `note`
- `created_by`
- timestamps

### project_extraction_conflicts

Purpose: conflict and adjudication state for field-level disagreement.

Recommended columns:

- `id`
- `project_id`
- `batch_id`
- `work_id`
- `template_field_id`
- `status` one of `open`, `resolved`
- `resolution_value` JSON nullable
- `resolution_status` nullable
- `resolution_note`
- `resolved_by`
- `resolved_at`
- timestamps

## Application Services

Recommended classes:

- `App\Actions\Projects\BuildProjectExtractionCandidates`
- `App\Actions\Projects\CreateProjectExtractionTemplate`
- `App\Actions\Projects\LockProjectExtractionTemplate`
- `App\Actions\Projects\StartProjectExtractionBatch`
- `App\Actions\Projects\RecordProjectExtractionValue`
- `App\Actions\Projects\CompleteProjectExtractionAssignment`
- `App\Actions\Projects\ResolveProjectExtractionConflict`
- `App\Queries\Projects\ProjectExtractionReadModel`
- `App\Queries\Projects\ProjectExtractionQueueReadModel`
- `App\Queries\Projects\ProjectExtractionDatasetReadModel`

### BuildProjectExtractionCandidates

Responsibilities:

- load the completed Workflow 8 handoff;
- include final full-text `include` works only;
- verify candidate works still belong to the locked representative snapshot;
- attach artifact and full-text decision provenance;
- expose excluded, maybe, and follow-up counts as context only;
- preserve deterministic ordering.

### LockProjectExtractionTemplate

Responsibilities:

- assert owner, workspace admin, or extraction manager access;
- validate field keys, required labels, and supported types;
- require at least one study descriptor field and one outcome field;
- store an audit reason;
- mark the template version locked.

### StartProjectExtractionBatch

Responsibilities:

- require a locked template;
- require completed full-text screening with included works;
- create one batch for the current included set;
- create assignments according to extraction mode;
- record `project.extraction.batch_started`.

### RecordProjectExtractionValue

Responsibilities:

- assert the actor owns the assignment or can manage extraction;
- assert the assignment belongs to the active extraction batch;
- validate the field type and value shape;
- require evidence pointer when the field requires it;
- allow structured missing-value states;
- write one field-level value;
- update assignment progress.

### ResolveProjectExtractionConflict

Responsibilities:

- assert owner, workspace admin, or adjudicator access;
- show source reviewer values before resolution;
- require an audit reason;
- persist the resolved value and status;
- record `project.extraction.conflict_resolved`.

## Route And Policy

Recommended routes:

- `GET /projects/{project}/extraction`
- `POST /projects/{project}/extraction/templates`
- `PATCH /projects/{project}/extraction/templates/{template}`
- `POST /projects/{project}/extraction/templates/{template}/lock`
- `POST /projects/{project}/extraction/batches`
- `GET /projects/{project}/extraction/queue`
- `POST /projects/{project}/extraction/assignments/{assignment}/values`
- `POST /projects/{project}/extraction/assignments/{assignment}/complete`
- `GET /projects/{project}/extraction/conflicts`
- `POST /projects/{project}/extraction/conflicts/{conflict}/resolve`
- `GET /projects/{project}/extraction/dataset`

Recommended route names:

- `projects.extraction.index`
- `projects.extraction.templates.store`
- `projects.extraction.templates.update`
- `projects.extraction.templates.lock`
- `projects.extraction.batches.store`
- `projects.extraction.queue`
- `projects.extraction.assignments.values.store`
- `projects.extraction.assignments.complete`
- `projects.extraction.conflicts.index`
- `projects.extraction.conflicts.resolve`
- `projects.extraction.dataset`

Recommended policy methods:

- `viewExtraction`
- `manageExtraction`
- `extractAssignedStudy`
- `resolveExtractionConflict`
- `viewExtractionDataset`

## UI Shape

Use an extraction workbench, not a generic form builder.

Primary surfaces:

- extraction readiness page;
- template builder with locked-version preview;
- assignment setup panel;
- reviewer extraction queue;
- right-side source artifact and full-text decision sheet;
- field conflict review sheet;
- dataset preview table.

Reviewer queue layout:

- compact assigned-study list on the left;
- center extraction form grouped by study descriptors, methods, outcomes, and
  notes;
- right-side source sheet hidden by default and opened on demand;
- field status rail for missing, conflicted, completed, and required fields;
- sticky save and complete controls.

Template builder layout:

- field groups as editable rows;
- type selector, options editor, required toggle, evidence pointer toggle;
- preview mode that shows the reviewer form before locking;
- lock action with audit reason.

## UX States

### Not Ready

No completed full-text screening batch exists. Link back to Workflow 8.

### Ready Without Template

Show included-study count and prompt owners/admins to create the extraction
template.

### Draft Template

Allow template editing and preview. Do not start extraction until the template
is locked.

### Active Extraction

Show progress by study, reviewer, and field group. Highlight open conflicts and
returned assignments.

### Reviewer Queue

Show one study at a time with full-text decision provenance, artifact actions,
and field-level save state.

### Conflict Review

Show reviewer values side by side and require an adjudicated final value or a
structured missing-value state.

### Completed Dataset

Show extraction coverage, field completeness, unresolved conflicts, and a
dataset preview. The next action becomes quality appraisal setup.

## Demo Data

Extend `DemoAccessSeeder` during implementation.

Required seeded states:

- completed full-text eligibility handoff with no extraction template;
- draft extraction template;
- locked extraction template;
- active extraction batch with reviewer assignments;
- one partially extracted study;
- one completed extraction assignment;
- one open field conflict;
- one resolved field conflict;
- completed extraction dataset ready for appraisal;
- viewer read-only extraction overview.

Use deterministic fake values and fake evidence pointers. Do not parse live
PDFs in the seeder.

## Browser Scenarios

Add these to `docs/demo-scenarios.md` during implementation:

1. Owner opens extraction before full-text screening is complete and sees a
   blocker.
2. Owner opens completed full-text eligibility handoff and sees included-study
   extraction readiness.
3. Owner creates and previews a draft extraction template.
4. Owner locks the extraction template with an audit reason.
5. Owner starts an extraction batch.
6. Reviewer opens the extraction queue and records field values with evidence
   pointers.
7. Reviewer completes an extraction assignment.
8. Dual extraction creates a field conflict when reviewer values differ.
9. Adjudicator resolves a field conflict with an audit reason.
10. Owner opens the completed extraction dataset preview.
11. Viewer sees read-only extraction progress and dataset preview.

Screenshot targets:

- `output/playwright/workflow-9-extraction-readiness.png`
- `output/playwright/workflow-9-template-builder.png`
- `output/playwright/workflow-9-template-lock.png`
- `output/playwright/workflow-9-extraction-queue.png`
- `output/playwright/workflow-9-source-sheet.png`
- `output/playwright/workflow-9-conflict-resolution.png`
- `output/playwright/workflow-9-dataset-preview.png`
- `output/playwright/workflow-9-viewer-readonly.png`

## Automated Tests

Pest feature tests:

- extraction is blocked until full-text screening is complete;
- only final full-text includes become extraction candidates;
- maybe, exclude, and follow-up artifact rows are not candidates;
- owner/admin can create and lock a template;
- reviewers/viewers cannot lock templates;
- extraction cannot start with a draft template;
- assignments follow the configured extraction mode;
- reviewer can write values for assigned studies;
- reviewer cannot write another reviewer's assignment;
- required fields and evidence pointers are enforced;
- conflicting field values create open conflicts;
- adjudicator can resolve conflicts with an audit reason;
- completed dataset read model exposes extraction coverage.

Vitest component tests:

- extraction readiness summary;
- extraction template builder;
- extraction field row validation;
- extraction queue study list;
- extraction value editor;
- source artifact sheet;
- extraction conflict sheet;
- dataset preview table.

Browser verification:

- owner template setup;
- reviewer extraction queue;
- conflict resolution flow;
- viewer read-only state.

## First Implementation Slice

1. Add extraction tables and models.
2. Add candidate builder from completed full-text includes.
3. Add extraction template create, update, preview, and lock actions.
4. Add extraction batch setup and assignment generation.
5. Add reviewer queue and field value recording.
6. Add field-level conflict detection.
7. Add conflict resolution with audit reason.
8. Add dataset preview read model.
9. Extend demo seed data.
10. Add Pest and component coverage.
11. Update `docs/demo-scenarios.md`.
12. Verify with browser screenshots.

## Explicit Non-Goals

Do not implement these in the first slice:

- AI extraction;
- PDF annotation;
- statistical meta-analysis;
- GRADE;
- risk-of-bias appraisal;
- PRISMA report generation;
- final export packages;
- public sharing;
- importing arbitrary external extraction spreadsheets.

## Risks

- Extraction must not include studies that did not pass full-text screening.
- Template edits after extraction starts can corrupt datasets if values are not
  tied to a version.
- Field-level autosave can become noisy. Keep audit events meaningful and avoid
  recording every keystroke.
- Evidence pointers need a structured shape before exports depend on them.
- Dual extraction conflict rules must be deterministic and transparent.
- Dataset preview should paginate and lazy-load values to avoid large page
  payloads.

## Readiness Checklist

Before implementation starts:

- Workflow 8 is merged;
- included-study candidate rules are accepted;
- extraction mode options are accepted;
- template versioning policy is accepted;
- field types are agreed for the first slice;
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
