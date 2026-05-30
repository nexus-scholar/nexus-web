# Demo Scenarios

This document is the living browser verification script for Nexus Scholar.
Update it whenever a workflow adds a new actor, role, screen, or state.

## Baseline Setup

Use the default local ports:

```powershell
php artisan serve
npm run dev
```

Use the demo seed data:

```powershell
php artisan migrate --force
php artisan db:seed --class=DemoAccessSeeder --force
```

For local browser verification through Python Playwright, keep the tooling in
the ignored project venv:

```powershell
python -m venv .venv
.\.venv\Scripts\python.exe -m pip install -r requirements-dev.txt
.\.venv\Scripts\python.exe -m playwright --version
```

For a clean scenario run, clear existing browser sessions without deleting demo
data:

```powershell
php artisan tinker --execute='DB::table("sessions")->delete();'
```

The seeder is idempotent. Running it repeatedly should preserve the same demo
actors and reset the intended demo states.

## Demo Accounts

All seeded accounts use the password `password`.

| Actor           | Email                        | Expected role or state                                 |
| --------------- | ---------------------------- | ------------------------------------------------------ |
| Operator        | `operator@nexusscholar.test` | Platform operator with user and workspace controls     |
| Workspace owner | `owner@nexusscholar.test`    | Owner of personal workspace and Evidence Synthesis Lab |
| Workspace admin | `admin@nexusscholar.test`    | Admin in Evidence Synthesis Lab                        |
| Reviewer        | `reviewer@nexusscholar.test` | Member in Evidence Synthesis Lab                       |
| Viewer          | `viewer@nexusscholar.test`   | Member in Evidence Synthesis Lab                       |
| Disabled user   | `disabled@nexusscholar.test` | Disabled account, cannot use the app                   |

Seeded workspace states:

- `Evidence Synthesis Lab`: shared workspace owned by the workspace owner.
- `Suspended Review Group`: suspended workspace for operator review.
- `pending-reviewer@nexusscholar.test`: pending invitation in Evidence Synthesis Lab.

Seeded project states:

- `AI Screening in Primary Care Reviews`: draft project in Evidence Synthesis
  Lab with a draft protocol.
- `Cardiometabolic Review Search Strategy`: draft-corpus project in Evidence
  Synthesis Lab with a completed protocol, draft search plan, completed demo
  search run, and deterministic corpus records.
- `Locked Cardiometabolic Evidence Snapshot`: locked corpus project in Evidence
  Synthesis Lab with snapshot-backed corpus membership.
- `Cardiometabolic Title Abstract Screening`: active screening project in
  Evidence Synthesis Lab with a locked representative snapshot, reviewer
  assignments, one open conflict, and one resolved conflict.
- `Cardiometabolic Screening Handoff`: completed screening project in Evidence
  Synthesis Lab with final include, maybe, exclude, and adjudicated outcomes.
- `Cardiometabolic Full-Text Retrieval`: completed screening project with a
  running full-text retrieval batch.
- `Cardiometabolic Full-Text Audit`: completed screening project with
  retrieved, failed, skipped, and manual-needed full-text audit rows.
- `Cardiometabolic Full-Text Screening`: completed retrieval project with
  active full-text reviewer assignments and an open full-text conflict.
- `Cardiometabolic Full-Text Eligibility Handoff`: completed full-text
  screening project with include, exclude, maybe, resolved conflict, and
  follow-up artifact counts.
- Owner has the project `owner` role.
- Reviewer has the project `reviewer` role.
- Viewer has the project `viewer` role.
- Workspace admin can administer the project through workspace policy without a
  project membership row.

## Workflow 1: Auth, Workspaces, And Access

### Public Entry

1. Clear sessions.
2. Open `http://127.0.0.1:8000/`.
3. Verify the page is Nexus Scholar branded and contains no Laravel starter
   content.
4. Capture `output/playwright/workflow-1-welcome.png`.

Expected signals:

- Title is `Welcome - Nexus Scholar`.
- Primary copy says `Nexus Scholar`.
- Calls to action include `Create account` and `Log in`.

### Owner Workspace Dashboard

1. Log in as `owner@nexusscholar.test`.
2. Open `/dashboard`.
3. Verify the active workspace is `Evidence Synthesis Lab` or switch to it from
   the workspace switcher.
4. Capture `output/playwright/workflow-1-dashboard-fixed.png`.

Expected signals:

- Sidebar brand says `Nexus Scholar`.
- Workspace switcher shows the active workspace and role.
- Dashboard shows personal and shared workspace access.
- `Workspace settings` and `Members` links appear for the active workspace.

### Workspace Member Management

1. As the owner, open the Evidence Synthesis Lab members screen from the
   sidebar.
2. Verify owner, admin, reviewer, and viewer demo accounts are listed.
3. Verify pending invitations are listed.
4. Invite a new address such as `browser-reviewer@nexusscholar.test`.
5. Capture `output/playwright/workflow-1-members-fixed.png` and
   `output/playwright/workflow-1-invite.png` when invite behavior changes.

Expected signals:

- Owners can invite members.
- Owners can see role controls for removable or editable members.
- The workspace switcher remains visible on the members page.
- Pending invitations stay scoped to the current workspace.

### Operator User Controls

1. Clear sessions.
2. Log in as `operator@nexusscholar.test`.
3. Open `/operator/users`.
4. Capture `output/playwright/workflow-1-operator-users.png`.

Expected signals:

- Sidebar includes `Operator users` and `Operator workspaces`.
- `Disabled Researcher` is marked disabled.
- Each operator action requires an audit reason.
- Operators can see disable or enable controls as applicable.

### Operator Workspace Controls

1. As the operator, open `/operator/workspaces`.
2. Capture `output/playwright/workflow-1-operator-workspaces.png`.

Expected signals:

- `Suspended Review Group` is visible as a suspended workspace.
- Workspace suspend or restore actions require an audit reason.
- Operator navigation remains visible.

### Disabled Account

1. Clear sessions.
2. Try to log in as `disabled@nexusscholar.test`.

Expected signals:

- The disabled account cannot proceed into the authenticated app.
- The app does not expose workspace pages after login is blocked.

## Workflow 2: Project Creation And Protocol

### Owner Project Dashboard

1. Clear sessions.
2. Log in as `owner@nexusscholar.test`.
3. Open `/dashboard`.
4. Verify the active workspace is `Evidence Synthesis Lab`.
5. Capture `output/playwright/workflow-2-owner-dashboard-projects.png`.

Expected signals:

- Dashboard includes a `Projects` panel.
- `AI Screening in Primary Care Reviews` is visible.
- Project and protocol status badges are visible.
- `New project`, `Protocol`, and `Open` actions are visible.

### Create Project

1. As the owner, open `/projects/create`.
2. Enter a title, review type, research question, and background.
3. Submit the form.
4. Capture `output/playwright/workflow-2-create-project.png`.

Expected signals:

- The page uses the guided project setup layout.
- The project is created in the active workspace.
- The app redirects to the project overview.
- The new project appears on the dashboard.

### Protocol Editor

1. As the owner, open the demo project's `Protocol` action.
2. Fill missing protocol fields, including exclusion criteria.
3. Set target providers through the provider tag selector. Verify selected
   providers render as removable tags and available providers render as toggle
   choices.
4. Set language policy, reviewer count, AI policy, and full-text policy.
5. Scroll the search-readiness card near the bottom of the viewport and open
   the AI policy and full-text dropdowns.
6. Save the draft.
7. Capture `output/playwright/workflow-2-protocol-editor.png`,
   `output/playwright/workflow-2-protocol-provider-tags.png`,
   `output/playwright/workflow-2-protocol-provider-tags-select-fixed.png`, and
   `output/playwright/workflow-2-protocol-full-text-select-fixed.png`.

Expected signals:

- Protocol fields use the Nexus/shadcn form styling.
- Target providers are selected from known provider tags, not comma-separated
  free-form text.
- AI policy and full-text dropdowns remain readable near the bottom of the
  viewport; they should flip upward instead of collapsing into a thin scroll
  strip.
- The project status remains visible in the header.
- Save is available to the project owner.
- Missing field errors appear when the owner attempts to complete an
  incomplete protocol.

### Role Boundary

1. Clear sessions.
2. Log in as `reviewer@nexusscholar.test`.
3. Open the demo project overview.
4. Open the demo project protocol page.
5. Capture `output/playwright/workflow-2-reviewer-protocol-readonly.png`.
6. Repeat as `viewer@nexusscholar.test`.

Expected signals:

- Reviewer and viewer can see the project because they have explicit project
  memberships.
- Reviewer and viewer do not see enabled protocol save controls.
- Workspace admin can access the project without an explicit project membership
  row.

## Workflow 3: Search Plan And Search Run

The implemented slice covers host-owned search-plan drafting, role-scoped
read-only review, queued background dispatch, and a stable search-run overview.

### Owner Search Plan Draft

1. Clear sessions.
2. Log in as `owner@nexusscholar.test`.
3. Open `Cardiometabolic Review Search Strategy`.
4. Open the `Search plan` action.
5. Add or edit query rows, providers, year range, result limit, and raw-payload
   policy.
6. Capture `output/playwright/workflow-3-search-plan-draft.png`.

Expected signals:

- Search-plan editing is available only after protocol completion.
- Provider selection uses known provider tags.
- Protocol defaults are visible without hiding per-query overrides.
- Validation errors identify the specific query row and field.
- `Run all queries` queues a background run and redirects to the run overview.

### Owner Search Run Dispatch

1. As the owner, run all draft search-plan items.
2. Open the search-run overview page after dispatch.
3. Capture `output/playwright/workflow-3-search-run-overview.png`.
4. If a queue worker processes the run during local verification, refresh and
   capture `output/playwright/workflow-3-search-run-overview-completed.png`.

Expected signals:

- The run moves to a background state immediately.
- Provider-level progress, raw counts, unique-work counts, and failures are
  visible when available.
- Partial provider failure does not hide successful provider results.
- If no queue worker is running, the overview remains in `Queued` state and the
  lifecycle panel states that it is waiting for queue records.
- If a queue worker is running, completed runs show provider timings, raw and
  unique counts, item completion, and lifecycle records.

### Search Plan Role Boundary

1. Clear sessions.
2. Log in as `reviewer@nexusscholar.test`.
3. Open the `Cardiometabolic Review Search Strategy` search-plan page.
4. Capture `output/playwright/workflow-3-reviewer-search-plan-readonly.png`.
5. Repeat as `viewer@nexusscholar.test`.

Expected signals:

- Reviewer and viewer can inspect the plan and search-run status.
- Reviewer and viewer cannot edit the plan or dispatch a run.
- Locked projects block search dispatch for every actor.

## Workflow 4: Draft Corpus Review

The implemented first slice covers a read-only corpus review surface after
search runs persist project works. It does not implement screening, full-text
retrieval, export, manual merge, or corpus lock actions.

### Owner Corpus Review

1. Clear sessions.
2. Log in as `owner@nexusscholar.test`.
3. Open `Cardiometabolic Review Search Strategy`.
4. Open the `Corpus` action from the project overview.
5. Capture `output/playwright/workflow-4-corpus-overview.png`.

Expected signals:

- Project status is `Draft corpus` when a completed search run has contributed
  records.
- Metrics show unique works, raw query links, provider coverage, metadata
  issues, and duplicate cluster count when clusters exist.
- Corpus records are listed with year, provider badges, metadata quality, and
  query-link counts.
- The table has a compact toolbar for row selection, read-only bulk actions,
  column visibility, sortable headers, horizontal overflow, and rows-per-page
  pagination.
- The page is read-only in this slice; no include, exclude, merge, split,
  screen, export, or lock action is visible.

### Record Detail And Provenance

1. As the owner, open the corpus review page.
2. Open a record with multiple providers or query links from the table `View`
   action.
3. Capture `output/playwright/workflow-4-corpus-detail.png`.

Expected signals:

- Detail opens in a right-side sliding panel rather than occupying permanent
  page space.
- Detail shows title, abstract, year, venue, identifiers, provider sightings,
  query provenance, rank, and seen timestamp.
- Missing abstract, missing identifier, and retracted flags are visible as
  metadata facts.
- Provider aliases use core-normalized values such as `semantic_scholar`.

### Corpus Filters

1. As the owner, filter by provider, search query, year range, identifier
   namespace, and metadata issue.
2. Capture `output/playwright/workflow-4-corpus-filters.png`.

Expected signals:

- The default table surface stays compact; advanced filters remain hidden until
  opened.
- Active filters appear as compact chips near the search toolbar.
- Filters are reflected in the URL query string.
- Pagination keeps filter parameters.
- Reset returns to the full project corpus without leaving the project context.

### Corpus Role Boundary

1. Clear sessions.
2. Log in as `reviewer@nexusscholar.test`.
3. Open the `Cardiometabolic Review Search Strategy` corpus page.
4. Capture `output/playwright/workflow-4-reviewer-corpus-readonly.png`.
5. Repeat as `viewer@nexusscholar.test`.

Expected signals:

- Reviewer and viewer can inspect corpus records and provenance.
- Reviewer and viewer do not see mutation controls.
- Users outside the project and without workspace administration cannot view
  the corpus.

### Empty Corpus State

1. As the owner, open a project with no persisted query-work membership.
2. Capture `output/playwright/workflow-4-corpus-empty.png`.

Expected signals:

- The page explains that search must run before corpus review has records.
- The primary next action points to the search plan when allowed.
- No fake records or placeholder metrics are shown.

### Locked Corpus State

1. As the owner, open `Locked Cardiometabolic Evidence Snapshot`.
2. Open the `Corpus` action from the project overview.
3. Capture `output/playwright/workflow-4-corpus-locked.png`.

Expected signals:

- Membership is presented as snapshot-backed, not draft query-work membership.
- Snapshot metadata includes locked time, actor, reason, and work count.
- Search and snowballing mutation actions are not available while locked.

## Workflow 5: Deduplication And Corpus Lock

Preparation lives in `docs/workflow-5-dedup-corpus-lock.md`. Wireframes live in
`docs/wireframes/workflow-5-dedup-lock-wireframes.html`.

The implementation must prove that screening will not receive duplicate members
by accident. When duplicate clusters exist, the lock path should create a
representative-aware snapshot and preserve duplicate-member provenance in the
snapshot metadata.

Implemented first-slice browser scenarios:

1. Owner opens deduplication readiness from the corpus page.
2. Owner runs deduplication and sees persisted cluster counts.
3. Owner opens a duplicate cluster and compares representative vs members.
4. Reviewer opens deduplication and sees read-only evidence.
5. Viewer opens deduplication and sees no mutation controls.
6. Owner attempts to lock without a reason and sees validation.
7. Owner locks with a reason and sees snapshot metadata.
8. Locked project blocks rerun deduplication and search mutation.
9. Stale deduplication blocks lock until rerun.

Automated coverage:

- `tests/Feature/ProjectDeduplicationWorkflowTest.php`
- `resources/js/components/dedup-readiness-rail.test.tsx`
- `resources/js/components/dedup-cluster-table.test.tsx`

Screenshot targets:

- `output/playwright/workflow-5-dedup-readiness.png`
- `output/playwright/workflow-5-dedup-cluster-detail.png`
- `output/playwright/workflow-5-lock-confirmation.png`
- `output/playwright/workflow-5-locked-snapshot.png`
- `output/playwright/workflow-5-reviewer-readonly.png`

## Workflow 6: Title And Abstract Screening

Preparation lives in `docs/workflow-6-title-abstract-screening.md`.
Wireframes live in `docs/wireframes/workflow-6-screening-wireframes.html`.

Implemented first-slice browser scenarios:

1. Owner opens `Cardiometabolic Title Abstract Screening` and verifies the
   screening overview shows locked snapshot readiness, active batch progress,
   reviewer workload, open conflicts, resolved conflicts, and audit events.
2. Owner opens `Locked Cardiometabolic Evidence Snapshot`, opens screening, and
   sees the setup panel for starting a screening batch from the representative
   locked corpus snapshot.
3. Reviewer opens the active screening queue and verifies assigned records,
   protocol criteria, work metadata, snapshot provenance, and the decision
   panel.
4. Reviewer submits a decision with a rationale and sees the queue advance to
   the next pending assignment.
5. Viewer opens the active screening overview and sees read-only progress
   without setup, queue, or conflict-resolution mutation controls.
6. Workspace admin or adjudicator opens the conflict panel and resolves an open
   conflict with an audit reason.
7. Owner opens `Cardiometabolic Screening Handoff` and verifies the full-text
   readiness panel shows final outcome counts and a handoff-ready state.
8. Completed or resolved assignments cannot be submitted again from the UI.

Expected signals:

- Screening uses the locked representative snapshot, not mutable draft corpus
  membership.
- `include`, `maybe`, and `exclude` labels map to core decision values:
  `include`, `needs_review`, and `exclude`.
- Final outcome counts are per work, not raw reviewer-vote counts.
- The full-text readiness card counts `include` plus `needs_review` as ready
  for the next workflow and keeps excluded records separate.
- Conflict resolution stays in a right-side sheet with source reviewer
  rationales visible.
- All mutations require role-appropriate access and a reviewer/adjudicator
  rationale.
- Viewer access is read-only.

Automated coverage:

- `tests/Feature/ProjectScreeningWorkflowTest.php`
- `resources/js/components/decision-badge.test.tsx`
- `resources/js/components/screening-status-badge.test.tsx`
- `resources/js/components/reviewer-workload-list.test.tsx`
- `resources/js/components/screening-queue-table.test.tsx`

Screenshot targets:

- `output/playwright/workflow-6-screening-overview.png`
- `output/playwright/workflow-6-screening-setup.png`
- `output/playwright/workflow-6-reviewer-queue.png`
- `output/playwright/workflow-6-reviewer-decision-submitted.png`
- `output/playwright/workflow-6-conflict-resolution.png`
- `output/playwright/workflow-6-conflict-resolved.png`
- `output/playwright/workflow-6-handoff-ready.png`
- `output/playwright/workflow-6-viewer-readonly.png`

## Workflow 7: Full-Text Retrieval And Artifact Audit

Preparation lives in `docs/workflow-7-full-text-retrieval.md`.
Wireframes live in `docs/wireframes/workflow-7-full-text-wireframes.html`.

Implemented first-slice browser scenarios:

1. Owner opens `Cardiometabolic Screening Handoff` and sees full-text readiness
   with include plus maybe records counted as candidates.
2. Owner opens the full-text page before retrieval and sees candidate preview,
   source policy, protocol policy, and readiness checks.
3. Owner starts retrieval and sees a queued or running background batch state.
4. Owner opens `Cardiometabolic Full-Text Retrieval` and sees the seeded running
   background batch state.
5. Owner opens `Cardiometabolic Full-Text Audit` and sees completed retrieval
   with success, failed, skipped, and manual-needed counts.
6. Owner opens a row detail sheet and sees the final screening decision,
   artifact metadata, and source attempts from the full-text audit trail.
7. Reviewer opens the same full-text page and sees read-only status, artifact
   access where permitted, and no mutation controls.
8. Viewer can inspect full-text status without retrieval or retry controls.
9. A manual-upload-only protocol disables automatic retrieval and explains the
   policy state without dispatching a job.

Expected signals:

- Candidates come from the completed screening handoff, not draft corpus
  membership.
- Final `exclude` outcomes are not queued for automatic retrieval.
- Retrieval always dispatches to the background queue.
- The UI shows legal open-access source policy explicitly.
- Failures and skipped items remain visible audit facts.
- Artifact access routes require project access and never expose public raw
  storage paths.
- Source audit is read through core read APIs instead of duplicated from
  `pdf_fetches`.

Automated coverage:

- `tests/Feature/ProjectFullTextWorkflowTest.php`
- `resources/js/components/full-text-status-badge.test.tsx`
- `resources/js/components/full-text-progress-strip.test.tsx`
- `resources/js/components/full-text-candidate-table.test.tsx`
- `resources/js/components/full-text-artifact-sheet.test.tsx`

Screenshot targets:

- `output/playwright/workflow-7-full-text-ready.png`
- `output/playwright/workflow-7-full-text-running.png`
- `output/playwright/workflow-7-full-text-completed.png`
- `output/playwright/workflow-7-full-text-artifact-detail.png`
- `output/playwright/workflow-7-reviewer-readonly.png`

## Workflow 8: Full-Text Screening

Preparation lives in `docs/workflow-8-full-text-screening.md`.
Wireframes live in
`docs/wireframes/workflow-8-full-text-screening-wireframes.html`.

Implemented first-slice browser scenarios:

1. Owner opens `Cardiometabolic Full-Text Retrieval`, then opens full-text
   screening and sees a blocker while retrieval is still running.
2. Owner opens `Cardiometabolic Full-Text Audit`, then opens full-text
   screening and sees successful artifact count plus follow-up count.
3. Owner opens `Cardiometabolic Full-Text Screening` and sees active full-text
   screening progress, reviewer workload, and an open conflict.
4. Reviewer opens the full-text queue and sees artifact context, title and
   abstract handoff, protocol criteria, and full-text decision controls.
5. Reviewer records an include decision with rationale after confirming the
   artifact was inspected.
6. Reviewer records an exclude decision with a structured exclusion reason.
7. Viewer opens full-text screening and sees read-only readiness/progress
   without setup, queue, or conflict-resolution mutation controls.
8. Conflicting full-text reviewer decisions create a stage-scoped open
   conflict.
9. Adjudicator resolves a full-text screening conflict with an audit reason.
10. Owner opens `Cardiometabolic Full-Text Eligibility Handoff` and sees
    completed full-text outcomes plus follow-up artifact counts.

Expected signals:

- Full-text screening uses `ScreeningStage::FULL_TEXT`.
- Only successfully retrieved artifacts enter the first screening batch.
- Failed, skipped, and manual-needed retrieval items remain visible follow-up
  states.
- Final title-and-abstract excludes are not revived.
- Exclude decisions require structured exclusion reason plus rationale.
- Artifact access remains project-authorized.
- Conflicts are stage-scoped and do not mix with title-and-abstract conflicts.

Automated coverage:

- `tests/Feature/ProjectFullTextWorkflowTest.php`
- `resources/js/components/full-text-screening-decision-panel.test.tsx`

Screenshot targets:

- `output/playwright/workflow-8-full-text-screening-readiness.png`
- `output/playwright/workflow-8-full-text-screening-setup.png`
- `output/playwright/workflow-8-full-text-queue.png`
- `output/playwright/workflow-8-full-text-decision.png`
- `output/playwright/workflow-8-full-text-conflict.png`
- `output/playwright/workflow-8-full-text-completed.png`
- `output/playwright/workflow-8-viewer-readonly.png`

## Workflow 9: Data Extraction

Preparation lives in `docs/workflow-9-data-extraction.md`.
Wireframes live in
`docs/wireframes/workflow-9-data-extraction-wireframes.html`.

Prepared scenario placeholders:

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

Expected signals:

- Extraction candidates come only from final full-text `include` outcomes.
- Full-text excludes, maybes, and follow-up artifact rows are not extracted in
  the first slice.
- Extraction starts only after a locked template exists.
- Template fields are versioned and values remain tied to the version reviewers
  used.
- Evidence pointers are required for configured fields.
- Conflicts are field-level and resolved with an audit reason.

Screenshot targets:

- `output/playwright/workflow-9-extraction-readiness.png`
- `output/playwright/workflow-9-template-builder.png`
- `output/playwright/workflow-9-template-lock.png`
- `output/playwright/workflow-9-extraction-queue.png`
- `output/playwright/workflow-9-source-sheet.png`
- `output/playwright/workflow-9-conflict-resolution.png`
- `output/playwright/workflow-9-dataset-preview.png`
- `output/playwright/workflow-9-viewer-readonly.png`

## Workflow 10: Quality Appraisal And Risk Of Bias

Preparation lives in
`docs/workflow-10-quality-appraisal-risk-of-bias.md`.
Wireframes live in
`docs/wireframes/workflow-10-quality-appraisal-wireframes.html`.

Prepared scenario placeholders:

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

Expected signals:

- Appraisal candidates come only from final full-text `include` outcomes.
- Appraisal is separate from eligibility and does not silently change inclusion
  decisions.
- Tool templates are locked before assignments start.
- Tool presets do not claim formal compliance unless exact rules are encoded.
- High-risk and unclear ratings require rationale and configured evidence
  pointers.
- Domain and overall rating conflicts are resolved with an audit reason.

Screenshot targets:

- `output/playwright/workflow-10-appraisal-readiness.png`
- `output/playwright/workflow-10-tool-setup.png`
- `output/playwright/workflow-10-appraisal-queue.png`
- `output/playwright/workflow-10-source-context-sheet.png`
- `output/playwright/workflow-10-conflict-resolution.png`
- `output/playwright/workflow-10-appraisal-matrix.png`
- `output/playwright/workflow-10-viewer-readonly.png`

## Workflow 11: Synthesis, PRISMA Counts, And Exports

Preparation lives in `docs/workflow-11-synthesis-prisma-exports.md`.
Wireframes live in
`docs/wireframes/workflow-11-synthesis-exports-wireframes.html`.

Prepared scenario placeholders:

1. Owner opens synthesis before extraction is complete and sees partial
   readiness.
2. Owner opens completed synthesis and sees PRISMA-style counts.
3. Owner previews the evidence table with extraction and appraisal columns.
4. Owner opens the export builder and selects CSV, BibTeX, RIS, JSON audit
   package, and full-text ZIP.
5. Owner starts an export package and sees queued/running status.
6. Owner opens a completed package detail sheet and sees manifest, item list,
   checksums, and download controls.
7. Viewer opens exports and sees read-only package history.
8. Failed package state shows error and retry guidance to owners/admins.

Expected signals:

- PRISMA-style counts distinguish records, representative works, reports, and
  studies.
- Export readiness is explicit per package section.
- Bibliography exports use core export handlers where applicable.
- JSON audit packages are deterministic and list source workflow ids.
- Full-text ZIP downloads use authorized routes and never expose raw storage
  paths.
- Export package assembly runs in the background.

Screenshot targets:

- `output/playwright/workflow-11-synthesis-overview.png`
- `output/playwright/workflow-11-prisma-counts.png`
- `output/playwright/workflow-11-evidence-table.png`
- `output/playwright/workflow-11-export-builder.png`
- `output/playwright/workflow-11-package-history.png`
- `output/playwright/workflow-11-package-detail.png`
- `output/playwright/workflow-11-viewer-readonly.png`

## Workflow 12: Production Hardening And Launch Controls

Preparation lives in
`docs/workflow-12-production-hardening-launch-controls.md`.
Wireframes live in
`docs/wireframes/workflow-12-production-controls-wireframes.html`.

Prepared scenario placeholders:

1. Owner opens workspace usage and sees current limits and usage.
2. Owner sees a near-limit warning.
3. Owner attempts a blocked heavy operation and sees a clear limit message.
4. Operator opens health overview and sees queue, storage, and failed job
   summaries.
5. Operator opens job monitor, filters failed jobs, and opens a job detail
   sheet.
6. Operator retries an allowed failed job with an audit reason.
7. Operator adjusts a workspace limit with an audit reason.
8. Owner opens project activity and sees workflow audit events.
9. Viewer cannot access operator health or workspace limits.

Expected signals:

- Soft limits warn at 80% and block heavy operations at 100%.
- Existing data remains readable when a heavy operation is blocked.
- Search, full-text retrieval, and export package assembly stay background-only.
- Operator pages show job and storage health without leaking secrets.
- Limit overrides and job retries require audit reasons.
- Project audit timelines include workflow and operator events.

Screenshot targets:

- `output/playwright/workflow-12-workspace-usage.png`
- `output/playwright/workflow-12-near-limit-warning.png`
- `output/playwright/workflow-12-limit-blocker.png`
- `output/playwright/workflow-12-operator-health.png`
- `output/playwright/workflow-12-job-monitor.png`
- `output/playwright/workflow-12-job-detail.png`
- `output/playwright/workflow-12-storage-dashboard.png`
- `output/playwright/workflow-12-project-activity.png`

## UI Hardening: Brand Tokens

Run this after global token or shared component changes.

### Public Brand Smoke

1. Open `http://127.0.0.1:8000/`.
2. Verify the welcome screen uses the Nexus Scholar brand mark, neutral
   surfaces, and semantic icon colors.
3. Capture `output/playwright/nexus-tokens-welcome.png`.

Expected signals:

- The page title is `Welcome - Nexus Scholar`.
- The primary action is visually dominant without using a one-hue theme.
- The preview panel uses `WorkspaceStatusBadge` for the `Active` state.

### Authenticated Shell Token Smoke

1. Log in as `operator@nexusscholar.test`.
2. Open `/dashboard`.
3. Verify the sidebar, workspace switcher, dashboard cards, and workspace type
   badge use the Nexus token system.
4. Capture `output/playwright/nexus-tokens-dashboard.png`.
5. Toggle or force dark mode and capture
   `output/playwright/nexus-tokens-dashboard-dark.png`.

Expected signals:

- `WorkspaceStatusBadge` renders the current workspace type.
- Light mode reads as clean research software, not starter-kit defaults.
- Dark mode keeps card boundaries, text, inputs, and primary actions readable.

## UI Hardening: Mark And Auth Shell

Run this after logo, app shell, or auth layout changes.

### Public Mark Smoke

1. Open `http://127.0.0.1:8000/`.
2. Verify the welcome header uses the Nexus evidence-card mark, not a generic
   starter icon.
3. Capture `output/playwright/nexus-mark-welcome.png`.

Expected signals:

- The mark remains legible at small header size.
- The brand lockup reads `Nexus Scholar`.
- The page keeps the tokenized brand and status colors.

### Auth Shell Smoke

1. Open `/login`.
2. Verify the login page uses the two-column desktop shell: form on the left,
   the generated Nexus research workspace image on the right, and a
   single-column fallback below desktop width.
3. Capture `output/playwright/nexus-auth-login-split.png`.
4. Resize to a mobile viewport and capture
   `output/playwright/nexus-auth-login-mobile.png`.
5. Open `/register`.
6. Verify registration uses the same split shell on desktop.
7. Capture `output/playwright/nexus-auth-register-split.png`.

Expected signals:

- Auth pages no longer look like unbranded starter-kit screens.
- The login page follows the shadcn `login-02` structure without importing a
  Next.js block into the Laravel/Inertia app.
- The logo, heading, description, forms, links, and visual panel fit cleanly at
  desktop width.
- The auth copy addresses the research workspace, not a generic account portal.
- The visual panel image is decorative for assistive technology and does not
  introduce readable pseudo-text or a heavy PNG payload.

## UI Hardening: Sidebar Shell

Run this after sidebar, workspace switcher, account footer, or navigation
changes.

### Owner Sidebar Smoke

1. Clear sessions.
2. Log in as `owner@nexusscholar.test`.
3. Open `/dashboard`.
4. Verify the sidebar contains static workspace rows, not a workspace dropdown.
5. Switch from `Evidence Synthesis Lab` to `Dr. Lina Haddad's Workspace`.
6. Collapse the sidebar.
7. Capture `output/playwright/nexus-sidebar-refactor-dashboard.png` and
   `output/playwright/nexus-sidebar-refactor-collapsed.png`.

Expected signals:

- The sidebar brand reads `Nexus Scholar`.
- There are no `Repository`, `Documentation`, GitHub, or Laravel starter links.
- Workspace rows show the workspace name and role.
- The active workspace row is highlighted and disabled.
- `Settings` and `Log out` are direct footer actions, not a user dropdown.
- Collapsed mode keeps the logo, workspace icons, navigation icons, account
  avatar, settings icon, and logout icon visible without text overlap.

### Operator Sidebar Smoke

1. Clear sessions.
2. Log in as `operator@nexusscholar.test`.
3. Open `/operator/users`.
4. Verify operator navigation is visible.
5. Capture `output/playwright/nexus-sidebar-operator-users.png`.

Expected signals:

- `Operator users` and `Operator workspaces` appear only for the operator.
- Workspace navigation and account footer still match the owner sidebar shape.
- Operator pages contain no starter-kit footer links.

## Browser Verification Rules

- Prefer the Browser MCP for local visual checks.
- Use screenshots for the first pass of every new workflow and for every bug
  fixed through visual inspection.
- Keep screenshots in `output/playwright/`; this path is ignored by Git.
- When a visual check reveals a UI bug, update this document with the scenario
  that would have caught it.
- Finish each workflow with the relevant automated gates:

```powershell
composer test
npm run lint:check
npm run format:check
npm run types:check
npm run build
composer validate --strict
composer audit --format=plain --abandoned=ignore
git diff --check
```

## Growth Rule

Each future workflow must add:

- seeded demo data,
- at least one actor-based scenario,
- expected browser-visible signals,
- screenshot artifact names,
- automated tests that protect the same behavior.
