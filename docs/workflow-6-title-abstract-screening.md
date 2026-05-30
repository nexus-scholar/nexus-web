# Workflow 6: Title And Abstract Screening

Prepared on 2026-05-30.

Status: first UI slice implemented and verified. The lifecycle gate now records
final per-work outcomes for full-text handoff readiness while keeping full-text
retrieval itself out of Workflow 6.

## Goal

Turn a locked, deduplicated corpus into a reviewer screening workflow for title
and abstract decisions.

Workflow 6 is the first scientific decision loop in Nexus Scholar Web. It must
let owners assign locked records to reviewers, let reviewers record include,
exclude, or maybe decisions with rationale, and make disagreement visible
before the project moves to full-text work.

## Sources Checked

- `docs/developer-handoff.md`
- `docs/demo-scenarios.md`
- `docs/workflow-4-draft-corpus-review.md`
- `docs/workflow-5-dedup-corpus-lock.md`
- `docs/brand-identity.md`
- `docs/design-tokens.md`
- `docs/ui-system-shadcn.md`
- `docs/frontend-quality.md`
- `app/Policies/ProjectPolicy.php`
- `app/Actions/Projects/LockProjectCorpus.php`
- `app/Actions/Projects/RunProjectCorpusDeduplication.php`
- `app/Actions/Projects/ProjectCorpusMembershipHasher.php`
- `app/Queries/Projects/ProjectCorpusReadModel.php`
- `app/Queries/Projects/ProjectDeduplicationReadModel.php`
- `database/migrations/2026_04_28_000010_create_corpus_snapshots_table.php`
- `database/migrations/2026_04_28_000007_create_screening_runs_table.php`
- `database/migrations/2026_04_27_000012_create_screening_decisions_table.php`
- `database/migrations/2026_04_28_000008_add_llm_fields_to_screening_decisions_table.php`
- `database/migrations/2026_04_28_000009_create_screening_votes_table.php`
- `repos/core/docs/v1.0/modules/05-core-screening-and-adjudication.md`
- `repos/core/src/Screening/Application/UseCase/AdjudicateScreeningDecisionsHandler.php`
- `repos/core/src/Screening/Application/UseCase/HumanAdjudicationInput.php`
- `repos/core/src/Screening/Application/UseCase/ScreenCorpusHandler.php`
- `repos/core/src/Screening/Domain/ScreeningCriteria.php`
- `repos/core/src/Screening/Domain/ScreeningDecision.php`
- `repos/core/src/Screening/Domain/ScreeningStage.php`
- `repos/core/src/Screening/Domain/ScreeningRunMode.php`
- `repos/core/src/Laravel/Persistence/Repository/EloquentScreeningDecisionRepository.php`

## Product Boundary

Nexus Scholar Web owns:

- reviewer assignment setup,
- reviewer queues and progress surfaces,
- title and abstract decision UI,
- conflict detection and resolution workflow,
- role-scoped access and mutation policy,
- audit events around assignment, decision, conflict, and resolution,
- demo data and browser scenarios for human screening.

`nexus-scholar/core` owns:

- locked-corpus enforcement,
- screening run records,
- screening decision records,
- screening vote records,
- screening criteria hashing,
- human adjudication handlers,
- LLM screening handlers and conservative council aggregation.

Use core screening tables and handlers where they fit. Do not move reviewer
queue, SaaS policy, or product UI into the package.

## Current Findings

The app already has the core screening migrations copied into the web
migration set:

- `screening_runs`
- `screening_decisions`
- `screening_votes`

The package dependency is `nexus-scholar/core:^1.0`, not a local path
repository, so implementation must stay compatible with the published v1 API.

Workflow 5 now locks a representative-only corpus snapshot. Downstream
screening should read from:

- `corpus_snapshots`
- `corpus_snapshot_works`

and should treat snapshot metadata as the audit source for locked membership.
Do not rebuild screenable membership from mutable `query_works`.

Core supports the screening decision values:

- `include`
- `needs_review`
- `exclude`

In the web UI, label `needs_review` as `Maybe` or `Needs review`, but keep the
stored value aligned with core.

Core does not ship:

- a product-facing reviewer queue,
- assignment records,
- conflict records,
- a consensus state table,
- a persisted comparison report table.

Those are host-app responsibilities.

## Locked Corpus Input Contract

Workflow 6 can start only when:

- the project is locked through Workflow 5,
- the latest snapshot exists,
- the snapshot work count is greater than zero,
- the snapshot metadata has `representative_snapshot: true`,
- the current user can view the project,
- the workspace is not suspended,
- screening has not already been finalized for the same stage.

The authoritative work list is:

```sql
select corpus_snapshot_works.work_id
from corpus_snapshot_works
join corpus_snapshots on corpus_snapshots.id = corpus_snapshot_works.snapshot_id
where corpus_snapshots.project_id = :project_id
order by corpus_snapshot_works.included_at, corpus_snapshot_works.work_id
```

Every assignment and decision must point to a work in this latest snapshot.
If a work is not in the snapshot, reject the mutation even if it still exists
in `scholarly_works`.

## First Slice

Implement human title and abstract screening only:

1. Owner or workspace admin opens a screening setup page for a locked project.
2. Owner or workspace admin assigns snapshot records to active reviewers.
3. Reviewers open their queue.
4. Reviewers record include, exclude, or maybe decisions with a rationale.
5. The app updates assignment status and screening progress.
6. The app detects conflicts when required reviewers disagree.
7. Owner, workspace admin, or adjudicator resolves conflicts with an audit
   reason.
8. Viewers can inspect progress and decisions read-only.

The first slice should work with seeded demo data and should not call live LLMs.

## Explicit Non-Goals

Do not implement these in Workflow 6:

- full-text retrieval,
- full-text screening,
- export packages,
- AI-assisted screening UI,
- live LLM calls,
- model council comparison UI,
- billing or usage limits,
- final PRISMA reporting,
- snowballing,
- reviewer calibration statistics,
- mobile-first screening optimization,
- saved table views.

## Data Model Proposal

Keep core tables as the source of recorded decisions. Add a web-owned layer for
assignment and conflict workflow state.

Recommended tables:

- `project_screening_batches`
- `project_screening_assignments`
- `project_screening_conflicts`

### project_screening_batches

Purpose: represent one product screening setup for one project and stage.

Recommended columns:

- `id`
- `project_id`
- `screening_run_id` nullable, referencing `screening_runs.id`
- `stage` default `title_abstract`
- `status` one of `draft`, `active`, `conflicts`, `completed`, `cancelled`
- `required_reviewer_count`
- `criteria_hash`
- `snapshot_id`
- `assignment_policy` JSON
- `counts` JSON
- `created_by`
- `started_at`
- `completed_at`
- timestamps

`criteria_hash` should come from `Nexus\Screening\Domain\ScreeningCriteria`.
For v1, build criteria from the locked protocol snapshot:

- review title,
- research question,
- inclusion criteria,
- exclusion criteria,
- language policy,
- date policy,
- full-text policy,
- protocol version,
- stage.

### project_screening_assignments

Purpose: one reviewer-to-work assignment.

Recommended columns:

- `id`
- `project_id`
- `batch_id`
- `work_id`
- `assigned_to`
- `assigned_by`
- `stage`
- `status` one of `pending`, `in_progress`, `decided`, `conflict`, `resolved`
- `screening_decision_id` nullable, referencing `screening_decisions.id`
- `sort_order`
- `assigned_at`
- `decided_at`
- timestamps

Recommended indexes:

- `project_id`, `stage`, `status`
- `batch_id`, `assigned_to`, `status`
- `batch_id`, `work_id`
- `assigned_to`, `status`

Recommended uniqueness:

- `batch_id`, `work_id`, `assigned_to`

### project_screening_conflicts

Purpose: one disagreement for one work in one batch and stage.

Recommended columns:

- `id`
- `project_id`
- `batch_id`
- `work_id`
- `stage`
- `status` one of `open`, `resolved`
- `decision_ids` JSON
- `resolved_decision_id` nullable, referencing `screening_decisions.id`
- `resolved_by`
- `resolution_reason`
- `opened_at`
- `resolved_at`
- timestamps

Recommended uniqueness:

- `batch_id`, `work_id`, `stage` for open conflict detection.

Do not create a separate final-decision table in the first slice. Store the
resolution as a core human decision and link it through
`project_screening_conflicts.resolved_decision_id`.

## Decision Model

Reviewer-facing labels:

| UI label | Stored core value | Meaning |
| --- | --- | --- |
| Include | `include` | The title and abstract appear eligible. |
| Maybe | `needs_review` | The reviewer cannot decide from title and abstract alone. |
| Exclude | `exclude` | The title and abstract do not meet criteria. |

Every human reviewer decision requires:

- decision,
- short rationale,
- optional evidence notes,
- optional uncertainty notes,
- optional exclusion basis.

Persist assignment decisions through the core `ScreeningDecisionRepositoryPort`
with a `ScreeningVerdict` whose `screeningRunId` is the active batch run and
whose `source` is `human`.

Do not call `AdjudicateScreeningDecisionsHandler` once per queue decision. That
handler is useful reference code for locked-membership validation and human
verdict shape, but it completes the command's run. The web assignment service
needs to keep the active batch open until all required decisions and conflicts
are finished.

Do not use `latestForWork()` to derive team consensus. It returns the latest
decision for one work and stage, which is useful for simple reads but not enough
for multi-reviewer agreement. The web read model must query assignments and
their linked decision ids.

## Assignment Model

The protocol already stores `min_reviewer_count`. Use it as the default
required reviewer count for the batch.

First-slice assignment policy:

- assign only active project reviewers and adjudicators,
- owner and workspace admin can assign but are not automatically reviewers,
- reviewers receive a balanced round-robin queue,
- each work gets `required_reviewer_count` assignments,
- do not assign the same reviewer twice to the same work,
- do not assign viewers,
- do not assign disabled users,
- do not assign users outside the project unless they are workspace owners or
  admins explicitly participating as adjudicators.

Recommended setup controls:

- required reviewer count,
- reviewer checklist,
- optional per-reviewer workload preview,
- start batch action,
- clear warning when the locked corpus has no records.

## Conflict Rules

For each work, compare all completed reviewer decisions in the active batch.

Resolved without conflict:

- all completed decisions have the same stored core value,
- one decision exists and required reviewer count is one.

Open conflict:

- at least two completed reviewer decisions disagree,
- one reviewer chooses `include` and another chooses `exclude`,
- one reviewer chooses `include` and another chooses `needs_review`,
- one reviewer chooses `exclude` and another chooses `needs_review`.

Conflict resolution:

- owner, workspace admin, or adjudicator can resolve,
- resolution requires a decision and an audit reason,
- store the resolution as a core human adjudication decision,
- link the resolution to the open conflict,
- mark related assignments as `resolved`,
- record an app audit event.

The first slice should not implement automatic tie-breaking. Human resolution
is clearer and safer.

## Route And Policy

Recommended routes:

- `GET /projects/{project}/screening`
- `POST /projects/{project}/screening/batches`
- `GET /projects/{project}/screening/queue`
- `POST /projects/{project}/screening/assignments/{assignment}/decision`
- `GET /projects/{project}/screening/conflicts`
- `POST /projects/{project}/screening/conflicts/{conflict}/resolve`

Recommended route names:

- `projects.screening.index`
- `projects.screening.batches.store`
- `projects.screening.queue`
- `projects.screening.assignments.decision`
- `projects.screening.conflicts.index`
- `projects.screening.conflicts.resolve`

Recommended policy methods:

- `viewScreening(User $user, Project $project): bool`
- `manageScreening(User $user, Project $project): bool`
- `screenAssignedWork(User $user, Project $project): bool`
- `resolveScreeningConflict(User $user, Project $project): bool`

Role behavior:

| Actor | View screening | Setup batch | Screen assigned work | Resolve conflict |
| --- | --- | --- | --- | --- |
| Project owner | Yes | Yes | If assigned | Yes |
| Workspace owner/admin | Yes | Yes | If assigned | Yes |
| Adjudicator | Yes | No | If assigned | Yes |
| Reviewer | Yes | No | Yes, assigned only | No |
| Viewer | Yes | No | No | No |
| Unverified or disabled user | No | No | No | No |
| Suspended workspace member | No | No | No | No |

Mutation blockers:

- project is not locked,
- latest snapshot is missing,
- workspace is suspended,
- actor is not allowed,
- assignment does not belong to the actor,
- assignment work is not in the latest snapshot,
- assignment is already resolved,
- conflict is already resolved,
- decision rationale is blank,
- active batch criteria hash does not match the current protocol snapshot.

## Application Services

Keep controllers thin. Recommended classes:

- `App\Actions\Projects\StartProjectScreeningBatch`
- `App\Actions\Projects\AssignProjectScreeningWork`
- `App\Actions\Projects\RecordProjectScreeningDecision`
- `App\Actions\Projects\ResolveProjectScreeningConflict`
- `App\Queries\Projects\ProjectScreeningReadModel`
- `App\Queries\Projects\ProjectScreeningQueueReadModel`

### StartProjectScreeningBatch

Responsibilities:

- assert project is locked,
- load latest corpus snapshot,
- build normalized screening criteria,
- create a human `screening_runs` row and keep it running while the batch is
  active,
- create a `project_screening_batches` row,
- create assignment rows for selected reviewers,
- record `project.screening.batch_started`.

### RecordProjectScreeningDecision

Responsibilities:

- assert the assignment belongs to the actor,
- assert the work belongs to the latest snapshot,
- validate decision and rationale,
- persist a core human decision through `ScreeningDecisionRepositoryPort`,
- link assignment to the decision,
- update assignment status,
- detect or clear conflict state for that work,
- record `project.screening.decision_recorded`.

### ResolveProjectScreeningConflict

Responsibilities:

- assert owner, workspace admin, or adjudicator role,
- assert conflict is open,
- validate resolution decision and reason,
- persist a core human adjudication decision with source decision ids in
  metadata,
- link the conflict to the resolution decision,
- update conflict and assignment statuses,
- record `project.screening.conflict_resolved`.

## Read Models

`ProjectScreeningReadModel` should return:

- project status,
- locked snapshot summary,
- active batch summary,
- criteria hash,
- reviewer workload,
- counts by assignment status,
- counts by decision value,
- open conflict count,
- recent audit events,
- available actions.

`ProjectScreeningQueueReadModel` should return:

- current reviewer queue,
- selected assignment detail,
- title,
- abstract,
- authors,
- year,
- venue,
- identifiers,
- provider provenance from snapshot metadata,
- protocol criteria,
- previous decision for this reviewer if editing is allowed,
- team status without exposing other reviewer decisions until the assignment is
  submitted.

`ProjectScreeningConflictReadModel` should return:

- open conflicts,
- resolved conflicts,
- source decisions,
- source reviewer names,
- decision rationales,
- selected work detail,
- resolution decision if present.

## UI Shape

Use dense, operational screens aligned with the current Rhea-inspired Nexus
app shell.

Wireframes live in
`docs/wireframes/workflow-6-screening-wireframes.html`.

Recommended surfaces:

- screening overview page,
- setup panel for owner/admin,
- reviewer queue page,
- right-side work detail sheet,
- decision control panel,
- conflict list,
- conflict resolution sheet.

Recommended product components:

- `ScreeningStatusBadge`
- `DecisionBadge`
- `ReviewerWorkloadList`
- `ScreeningProgressStrip`
- `ScreeningQueueTable`
- `ScreeningDecisionPanel`
- `ScreeningWorkDetail`
- `ConflictBadge`
- `ConflictResolutionSheet`

Use existing shadcn primitives:

- `Button`
- `Badge`
- `Card`
- `Checkbox`
- `Dialog`
- `Input`
- `Label`
- `Select`
- `Sheet`
- `Textarea`
- `Tooltip`

Use status colors from `docs/design-tokens.md`:

- include: green,
- exclude: red or rose,
- maybe/conflict: violet,
- audit: amber,
- pending: gray.

Avoid a dashboard-heavy screen with large widgets. Screening is repetitive work;
the primary experience should be a fast queue, readable abstracts, clear
criteria, keyboard-friendly decision controls, and low-friction rationale entry.

## UX States

### Locked But Not Started

Show snapshot count, protocol criteria, required reviewer count, reviewer
selection, and `Start screening`.

### Active Screening

Show batch progress, reviewer workload, pending decisions, and open conflicts.
Reviewers see `Continue queue`.

### Reviewer Queue

Show one selected record with title, abstract, metadata, provenance, criteria,
decision controls, rationale field, and next/previous navigation. Keep table or
queue navigation visible enough that reviewers do not feel trapped in one
record.

### Conflict Review

Show disagreement pairs, source rationales, selected work details, and a
resolution sheet. Do not hide the audit reason requirement.

### Completed Screening

Show final per-work outcome counts and disable assignment mutation. The
full-text readiness card counts final `include` plus final `needs_review`
outcomes as ready for the next workflow, keeps final `exclude` outcomes
separate, and does not expose a full-text retrieval action in this slice.

## Demo Data

Extend `DemoAccessSeeder` during implementation.

Required seeded states:

- locked project with no screening batch,
- active batch with pending reviewer assignments,
- active batch with completed agreement,
- active batch with one open conflict,
- resolved conflict with audit reason,
- completed batch with final include, maybe, exclude, and adjudicated outcomes,
- reviewer queue for `reviewer@nexusscholar.test`,
- viewer read-only screening overview.

Reuse `Locked Cardiometabolic Evidence Snapshot` as the main locked-corpus
demo project unless a second project makes the UI clearer.

## Browser Scenarios

Add these to `docs/demo-scenarios.md` during implementation:

1. Owner opens screening setup from a locked corpus project.
2. Owner starts a title and abstract screening batch.
3. Reviewer opens the assigned queue and records an include decision.
4. Reviewer records a maybe decision with rationale.
5. Reviewer cannot screen a work that is not assigned to them.
6. Viewer can inspect progress but cannot record decisions.
7. Owner sees workload and progress update after reviewer decisions.
8. Conflicting reviewer decisions create an open conflict.
9. Adjudicator resolves a conflict with an audit reason.
10. Completed screening shows full-text readiness counts and disables setup or
    reviewer mutation.

Screenshot targets:

- `output/playwright/workflow-6-screening-setup.png`
- `output/playwright/workflow-6-reviewer-queue.png`
- `output/playwright/workflow-6-decision-panel.png`
- `output/playwright/workflow-6-conflict-list.png`
- `output/playwright/workflow-6-conflict-resolution.png`
- `output/playwright/workflow-6-viewer-readonly.png`

## Automated Tests

Pest feature tests:

- owner can view screening setup for locked project,
- unlocked project cannot start screening,
- missing snapshot blocks screening start,
- owner can start screening batch,
- assignments are created only for active reviewers,
- viewer cannot start batch or record decisions,
- reviewer can record decision for own assignment,
- reviewer cannot record decision for another reviewer assignment,
- decision requires rationale,
- decision persists to `screening_decisions`,
- assignment links to persisted decision,
- agreement marks work resolved without conflict,
- disagreement creates conflict,
- owner/admin/adjudicator can resolve conflict,
- reviewer cannot resolve conflict,
- conflict resolution writes audit event,
- suspended workspace blocks screening access.

Vitest component tests:

- `DecisionBadge`,
- `ScreeningStatusBadge`,
- `ReviewerWorkloadList`,
- `ScreeningDecisionPanel` validation states,
- `ScreeningQueueTable` selection and empty state,
- `ConflictResolutionSheet` reason requirement,
- read-only rendering for viewer.

Browser verification:

- desktop owner setup flow,
- desktop reviewer queue flow,
- conflict resolution flow,
- viewer read-only flow,
- narrow viewport smoke for reviewer queue only.

## Implementation Order

1. Add screening batch, assignment, and conflict migrations.
2. Add models and relationships.
3. Add policy methods.
4. Add screening criteria builder from protocol and snapshot.
5. Add `StartProjectScreeningBatch`.
6. Add screening overview route and Inertia page.
7. Add reviewer queue read model and page.
8. Add `RecordProjectScreeningDecision`.
9. Add conflict detection and conflict read model.
10. Add `ResolveProjectScreeningConflict`.
11. Extend `DemoAccessSeeder`.
12. Add Pest feature coverage.
13. Add React component coverage.
14. Update `docs/demo-scenarios.md`.
15. Verify with browser screenshots.
16. Run full validation gates.

## Risks

- Core's `latestForWork()` is not enough for multi-reviewer consensus. Query
  assignment-linked decisions instead.
- Screening must never operate on draft query membership after lock.
- `needs_review` must be presented clearly. If the UI calls it `Maybe`, the
  stored value still stays `needs_review`.
- Assignment reruns can duplicate work unless uniqueness and idempotent setup
  are tested.
- Conflict resolution is scientifically consequential. Require an audit reason.
- Reviewer queues can become slow if the read model loads full detail for every
  row. Paginate and load selected work detail separately.
- Do not let AI-related fields in core tables make the human first slice feel
  automated.

## Readiness Checklist

Before implementation starts:

- repo is clean,
- branch is fresh,
- locked snapshot contract is accepted,
- assignment table names are accepted,
- conflict rules are accepted,
- first slice remains human-only,
- demo scenario names match this document,
- browser verification will use seeded data,
- no implementation shells out to command-line workflow hosts,
- validation gates are known.

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
