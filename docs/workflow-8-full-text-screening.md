# Workflow 8: Full-Text Screening

Prepared on 2026-05-30.

Status: preparation only. Workflow 7 is merged and provides full-text retrieval
batches, artifact status, and source audit. Workflow 8 turns retrieved or
manually followed-up records into a human full-text eligibility decision loop.

Wireframes: `docs/wireframes/workflow-8-full-text-screening-wireframes.html`.

## Goal

Let research teams make final eligibility decisions after reading the full text
of records that survived title-and-abstract screening.

Workflow 8 answers these questions:

- Which title-and-abstract includes or maybes have usable full text?
- Which records need full-text reviewer assignment?
- Which full-text records are finally included, excluded, unresolved, or
  blocked by missing artifacts?
- Which exclusion reasons and audit notes explain the final decision set?

Full-text retrieval is not the same as full-text screening. Retrieval only
finds artifacts. Screening is a human scientific decision.

## Sources Checked

- `docs/workflow-6-title-abstract-screening.md`
- `docs/workflow-7-full-text-retrieval.md`
- `docs/demo-scenarios.md`
- `docs/project-progress.md`
- `repos/core/docs/v1.0/modules/05-core-screening-and-adjudication.md`
- `repos/core/src/Screening/Domain/ScreeningStage.php`
- `repos/nexus-cli/docs/commands/nexus-screen-adjudicate/README.md`
- `repos/nexus-cli/docs/commands/nexus-screen-compare/README.md`
- `app/Actions/Projects/BuildProjectFullTextCandidates.php`
- `app/Queries/Projects/ProjectFullTextReadModel.php`
- `database/migrations/2026_05_30_000002_create_project_screening_workflow_tables.php`
- `database/migrations/2026_05_30_000003_create_project_full_text_workflow_tables.php`

## Product Boundary

Nexus Scholar Web owns:

- full-text reviewer assignment and queue UX;
- artifact-linked decision screens;
- role-scoped full-text screening controls;
- missing-artifact follow-up states;
- full-text exclusion reason capture;
- conflict detection and adjudication;
- audit events around assignment, decision, and resolution;
- demo data and browser scenarios.

`nexus-scholar/core` owns:

- screening stage values, including `full_text`;
- screening decision records;
- screening run records;
- human adjudication handlers;
- decision comparison behavior;
- locked project membership enforcement.

Do not shell out to `nexus-cli`. Treat CLI docs as a behavior reference only.

## Input Contract

Workflow 8 starts when:

- Workflow 6 has a completed title-and-abstract screening batch;
- Workflow 7 has either a terminal full-text retrieval batch or the protocol
  explicitly uses a manual-follow-up policy;
- the candidate set is still tied to the latest locked representative snapshot;
- the actor can manage screening for the project;
- the workspace is active.

Candidate records are:

- final `include` records from title-and-abstract screening;
- final `needs_review` records from title-and-abstract screening;
- never final `exclude` records by default.

Record-level screenability depends on artifact state:

| Full-text state | First-slice behavior |
| --- | --- |
| `success` | Screenable. Assignment should link to the successful artifact item. |
| `manual_needed` | Not screenable until a manual artifact or explicit missing-full-text decision path exists. |
| `failed` | Not screenable by default. Keep as follow-up unless the protocol later allows audited missing-full-text exclusion. |
| `skipped` | Not screenable by default. Keep as follow-up unless the missing reason is resolved. |
| no batch | Block setup and point back to Workflow 7. |
| running batch | Block setup and show retrieval progress. |

The first implementation slice should screen only records with successful
retrieval artifacts. Missing-artifact decisions can be planned but should not be
silently converted into scientific exclusions.

## Protocol Policy

Use `project_protocols.full_text_policy`:

| Policy | Workflow behavior |
| --- | --- |
| `optional` | Allow full-text screening for retrieved artifacts and keep missing records in follow-up. |
| `required_for_inclusion` | Block final inclusion for records without inspected full text. |
| `manual_uploads_only` | Do not depend on automatic retrieval. Show manual artifact requirements before screening can start. |

## Data Model

Reuse the existing project screening workflow tables with `stage = full_text`
where possible:

- `project_screening_batches`
- `project_screening_assignments`
- `project_screening_conflicts`
- core `screening_runs`
- core `screening_decisions`

Add a small stage-specific link to preserve artifact provenance:

- `project_screening_batches.source_full_text_batch_id` nullable;
- `project_screening_assignments.source_full_text_item_id` nullable.

These fields keep the decision connected to the artifact state that reviewers
were asked to inspect without duplicating `project_full_text_items` or
`pdf_fetches`.

Do not create a separate final-inclusion table in the first slice. The final
full-text outcome should be derived from completed `full_text` screening
assignments, resolved conflicts, and their linked core decisions.

## Decision Model

Reviewer-facing decisions:

| UI label | Stored core value | Meaning |
| --- | --- | --- |
| Include | `include` | The full text satisfies eligibility criteria. |
| Maybe | `needs_review` | The reviewer cannot decide and needs adjudication or team discussion. |
| Exclude | `exclude` | The full text fails one or more eligibility criteria. |

Every decision requires:

- decision;
- rationale;
- exclusion reason when decision is `exclude`;
- confirmation that the artifact was inspected;
- optional evidence notes;
- optional page, section, or quote pointer when useful.

Recommended first-slice exclusion reasons:

- wrong population;
- wrong intervention or exposure;
- wrong comparator;
- wrong outcome;
- wrong study design;
- wrong publication type;
- duplicate report;
- language or date out of scope;
- full text unavailable after follow-up;
- other, with required explanation.

## Application Services

Recommended classes:

- `App\Actions\Projects\BuildProjectFullTextScreeningCandidates`
- `App\Actions\Projects\StartProjectFullTextScreeningBatch`
- `App\Actions\Projects\RecordProjectFullTextScreeningDecision`
- `App\Actions\Projects\ResolveProjectFullTextScreeningConflict`
- `App\Queries\Projects\ProjectFullTextScreeningReadModel`
- `App\Queries\Projects\ProjectFullTextScreeningQueueReadModel`

Prefer reusing existing title-and-abstract screening services only after they
are made stage-aware. Do not add conditionals that make the current Workflow 6
behavior harder to reason about.

### BuildProjectFullTextScreeningCandidates

Responsibilities:

- load the completed Workflow 6 handoff;
- load the latest terminal Workflow 7 batch;
- include final title-and-abstract `include` and `needs_review` records only;
- require successful artifacts for first-slice screenable assignments;
- return follow-up counts for failed, skipped, and manual-needed records;
- preserve deterministic ordering.

### StartProjectFullTextScreeningBatch

Responsibilities:

- assert project access and workspace state;
- assert the latest full-text retrieval batch is terminal;
- create a core `screening_runs` row with `stage = full_text`;
- create a project screening batch with `stage = full_text`;
- assign only screenable records with successful artifacts;
- record `project.full_text_screening.batch_started`.

### RecordProjectFullTextScreeningDecision

Responsibilities:

- assert the assignment belongs to the actor;
- assert the assignment stage is `full_text`;
- assert the linked artifact item still belongs to the project;
- validate rationale, inspected-artifact confirmation, and exclusion reason;
- persist a core human decision with `ScreeningStage::FULL_TEXT`;
- link assignment to the decision;
- detect or clear conflicts;
- record `project.full_text_screening.decision_recorded`.

### ResolveProjectFullTextScreeningConflict

Responsibilities:

- assert owner, workspace admin, or adjudicator role;
- assert the conflict stage is `full_text`;
- validate resolution decision and audit reason;
- persist a core human adjudication decision with `ScreeningStage::FULL_TEXT`;
- update conflict and assignment statuses;
- record `project.full_text_screening.conflict_resolved`.

## Route And Policy

Recommended routes:

- `GET /projects/{project}/full-text-screening`
- `POST /projects/{project}/full-text-screening/batches`
- `GET /projects/{project}/full-text-screening/queue`
- `POST /projects/{project}/full-text-screening/assignments/{assignment}/decision`
- `GET /projects/{project}/full-text-screening/conflicts`
- `POST /projects/{project}/full-text-screening/conflicts/{conflict}/resolve`

Recommended route names:

- `projects.full-text-screening.index`
- `projects.full-text-screening.batches.store`
- `projects.full-text-screening.queue`
- `projects.full-text-screening.assignments.decision`
- `projects.full-text-screening.conflicts.index`
- `projects.full-text-screening.conflicts.resolve`

Policy can reuse screening role boundaries, but method names should make the
stage clear:

- `viewFullTextScreening`
- `manageFullTextScreening`
- `screenAssignedFullText`
- `resolveFullTextScreeningConflict`

## UI Shape

Use a reviewer-work screen, not a dashboard.

Primary surfaces:

- full-text screening readiness page;
- setup panel for reviewer assignment;
- reviewer queue with artifact context;
- right-side artifact detail sheet;
- decision panel with exclusion reason controls;
- conflict list and resolution sheet;
- completed handoff summary for extraction readiness.

Reviewer queue layout:

- left rail or compact table for assigned records;
- center area for title, abstract, metadata, and criteria;
- artifact action strip for download/open plus source audit summary;
- decision panel fixed enough that repetitive screening is fast;
- conflict or team-status panel hidden until needed.

Do not embed a heavy PDF viewer in the first slice unless performance is proven.
The first slice can use authorized artifact download/open controls and metadata
inspection.

## UX States

### Not Ready

No completed full-text retrieval batch exists. Link back to Workflow 7.

### Retrieval Running

Show progress and disable full-text screening setup.

### Ready With Retrieved Artifacts

Show screenable count, follow-up count, artifact policy, reviewer selection, and
start action.

### Active Full-Text Screening

Show progress, reviewer workload, pending assignments, follow-up records, and
open conflicts.

### Reviewer Queue

Show artifact-linked record detail, inclusion/exclusion criteria, decision
controls, rationale, exclusion reason when needed, and next/previous navigation.

### Conflict Review

Show reviewer decisions, rationales, artifact status, and a resolution sheet
with required audit reason.

### Completed

Show final include, exclude, maybe, conflict, and follow-up counts. The next
action becomes extraction setup.

## Demo Data

Extend `DemoAccessSeeder` during implementation.

Required seeded states:

- completed full-text retrieval with no full-text screening batch;
- active full-text screening batch with pending assignments;
- reviewer queue with successful artifact links;
- one exclusion decision with reason;
- one open conflict;
- one resolved conflict with audit reason;
- completed full-text screening handoff with include, exclude, maybe, and
  follow-up counts;
- viewer read-only full-text screening overview.

Use fake artifact paths already created for Workflow 7. Do not make live network
calls from seeders or browser scenarios.

## Browser Scenarios

Add these to `docs/demo-scenarios.md` during implementation:

1. Owner opens full-text screening before retrieval is complete and sees a
   blocker.
2. Owner opens completed retrieval and sees screenable artifact count plus
   follow-up count.
3. Owner starts a full-text screening batch.
4. Reviewer opens the full-text queue and sees artifact context.
5. Reviewer records an include decision with rationale.
6. Reviewer records an exclude decision with exclusion reason.
7. Viewer opens full-text screening read-only.
8. Conflicting decisions create an open conflict.
9. Adjudicator resolves a full-text screening conflict with an audit reason.
10. Completed full-text screening shows extraction readiness.

Screenshot targets:

- `output/playwright/workflow-8-full-text-screening-readiness.png`
- `output/playwright/workflow-8-full-text-screening-setup.png`
- `output/playwright/workflow-8-full-text-queue.png`
- `output/playwright/workflow-8-full-text-decision.png`
- `output/playwright/workflow-8-full-text-conflict.png`
- `output/playwright/workflow-8-full-text-completed.png`
- `output/playwright/workflow-8-viewer-readonly.png`

## Automated Tests

Pest feature tests:

- setup is blocked until full-text retrieval is terminal;
- only successful artifact items become screenable in the first slice;
- failed, skipped, and manual-needed items stay in follow-up;
- owner/admin can start full-text screening;
- reviewer/viewer cannot start full-text screening;
- reviewer can decide assigned full-text work;
- reviewer cannot decide another reviewer assignment;
- exclude decisions require an exclusion reason;
- decisions use `ScreeningStage::FULL_TEXT`;
- conflicts are stage-scoped and do not mix with title-and-abstract conflicts;
- adjudicator can resolve full-text conflict with audit reason;
- completed full-text screening exposes extraction readiness counts.

Vitest component tests:

- full-text screening readiness summary;
- full-text screening queue table;
- artifact-linked work detail;
- full-text decision panel validation;
- exclusion reason selector;
- full-text conflict resolution sheet;
- read-only viewer state.

Browser verification:

- desktop owner setup flow;
- desktop reviewer queue flow;
- conflict resolution flow;
- viewer read-only flow;
- narrow viewport smoke for reviewer queue only.

## First Implementation Slice

1. Make screening batch/read-model code stage-aware without regressing Workflow
   6.
2. Add nullable full-text source links to project screening batch and
   assignment tables.
3. Add candidate builder for successful full-text artifact items.
4. Add policy methods and routes.
5. Add full-text screening overview page.
6. Add reviewer queue page with artifact context.
7. Add decision recording with artifact-inspected confirmation.
8. Add conflict detection and resolution for `full_text`.
9. Extend demo seed data.
10. Add Pest and component coverage.
11. Update `docs/demo-scenarios.md`.
12. Verify with browser screenshots.

## Explicit Non-Goals

Do not implement these in the first slice:

- manual PDF upload;
- OCR;
- PDF annotation;
- in-browser PDF rendering if it harms performance;
- extraction forms;
- risk-of-bias appraisal;
- AI full-text screening;
- PRISMA report generation;
- export packages;
- public sharing.

## Risks

- Reusing title-and-abstract screening code without stage boundaries can create
  subtle bugs. Keep stage filters explicit.
- Full-text unavailable is not the same as scientific exclusion. Treat it as a
  follow-up or protocol-governed decision.
- Artifact access must remain project-authorized.
- Decision screens can become slow if they load all artifact audit records for
  every row. Load detail for the selected record.
- Exclusion reasons must be structured enough for PRISMA/export later.

## Readiness Checklist

Before implementation starts:

- repo is clean;
- branch is fresh;
- Workflow 7 is merged;
- candidate rules are accepted;
- first slice screens successful artifacts only;
- missing-artifact states remain follow-up;
- route names and stage-aware policy methods are accepted;
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
