# Developer Handoff

Last updated: 2026-05-30.

This document is the fastest route into Nexus Scholar Web for a developer taking
over the project. It summarizes the current product state, local setup, demo
data, validation commands, and the next implementation boundary.

## Repository State

- Default branch: `master`.
- Current application: hosted Laravel/Inertia SaaS-style app for Nexus Scholar.
- Package dependency: `nexus-scholar/core:^1.0` from Packagist.
- Latest merged workflow: workflow 8, full-text screening, merged in PR #15.
- Local demo data is deterministic and lives in `Database\Seeders\DemoAccessSeeder`.
- Browser scenario source of truth is `docs/demo-scenarios.md`.

## Product Boundary

`nexus-web` owns authentication, workspaces, projects, SaaS policy, UI routes,
demo data, and browser workflow experience.

`nexus-scholar/core` owns the reusable review engine: scholarly search,
deduplication, corpus snapshots, screening, full-text retrieval, citation graph
logic, exports, and Laravel package services.

Keep host-specific UI and SaaS tenancy in this repository. Keep reusable
research logic in `core`.

## Local Setup

From the repository root:

```powershell
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=DemoAccessSeeder --force
npm run build
```

If the SQLite file is missing:

```powershell
New-Item -ItemType File database/database.sqlite -Force
```

Start the full local stack:

```powershell
composer run dev
```

The default app URL is:

```text
http://localhost:8000
```

## Demo Accounts

All seeded demo accounts use the password `password`.

| Actor | Email | Expected access |
| --- | --- | --- |
| Operator | `operator@nexusscholar.test` | Platform user and workspace controls |
| Workspace owner | `owner@nexusscholar.test` | Owner of Evidence Synthesis Lab |
| Workspace admin | `admin@nexusscholar.test` | Workspace admin |
| Reviewer | `reviewer@nexusscholar.test` | Project reviewer, read-only outside assigned work |
| Viewer | `viewer@nexusscholar.test` | Project viewer |
| Disabled user | `disabled@nexusscholar.test` | Cannot use the app |

Reset demo state:

```powershell
php artisan migrate --force
php artisan db:seed --class=DemoAccessSeeder --force
```

Clear browser sessions without deleting demo data:

```powershell
php artisan tinker --execute='DB::table("sessions")->delete();'
```

If PsySH history permissions fail on Windows, use the PDO fallback:

```powershell
php -r '$pdo=new PDO("sqlite:database/database.sqlite"); $pdo->exec("delete from sessions");'
```

## Implemented Workflows

| Workflow | Status | Main docs |
| --- | --- | --- |
| 0. Local scaffold | Done | `docs/phase-0-local-dev.md` |
| 1. Auth, workspaces, access | Done | `docs/demo-scenarios.md` |
| 2. Project creation and protocol | Done | `docs/workflow-2-project-protocol-ui.md` |
| 3. Search plan and run | Done | `docs/workflow-3-search-plan-run.md` |
| 4. Draft corpus review | Done | `docs/workflow-4-draft-corpus-review.md` |
| 5. Deduplication and corpus lock | Done | `docs/workflow-5-dedup-corpus-lock.md` |
| 6. Title and abstract screening | Done | `docs/workflow-6-title-abstract-screening.md` |
| 7. Full-text retrieval and artifact audit | Done | `docs/workflow-7-full-text-retrieval.md` |
| 8. Full-text screening | Done | `docs/workflow-8-full-text-screening.md` |
| 9. Data extraction | Prepared | `docs/workflow-9-data-extraction.md` |
| 10. Quality appraisal / risk of bias | Prepared | `docs/workflow-10-quality-appraisal-risk-of-bias.md` |
| 11. Synthesis, PRISMA counts, exports | Prepared | `docs/workflow-11-synthesis-prisma-exports.md` |
| 12. Production hardening and launch controls | Prepared | `docs/workflow-12-production-hardening-launch-controls.md` |

## Workflow 8 Notes

Workflow 8 is integrated in the app flow, not only covered by tests.

The owner can open full-text screening after retrieval, reviewers can work
artifact-linked eligibility queues, excluded studies require structured
full-text exclusion reasons, conflicts stay stage-scoped, and completed
full-text screening produces final include/exclude/maybe counts plus follow-up
artifact counts for extraction readiness.

Hardening already in place:

- full-text screening uses `ScreeningStage::FULL_TEXT`;
- only successfully retrieved artifacts enter the first screening batch;
- failed, skipped, and manual-needed retrieval rows remain follow-up states;
- artifact access remains project-authorized;
- conflicts are separate from title-and-abstract conflicts;
- reviewer and viewer access remains role-scoped.

Key files:

- `app/Actions/Projects/StartProjectFullTextScreeningBatch.php`
- `app/Actions/Projects/RecordProjectFullTextScreeningDecision.php`
- `app/Actions/Projects/ResolveProjectFullTextScreeningConflict.php`
- `app/Queries/Projects/ProjectFullTextScreeningReadModel.php`
- `app/Queries/Projects/ProjectFullTextScreeningQueueReadModel.php`
- `resources/js/pages/projects/full-text-screening.tsx`
- `resources/js/pages/projects/full-text-screening-queue.tsx`
- `tests/Feature/ProjectFullTextWorkflowTest.php`

## Workflow 7 Notes

Workflow 7 is integrated in the app flow, not only covered by tests.

The owner can start legal open-access full-text retrieval from a completed
title-and-abstract screening handoff, the queue job records success, failure,
skipped, and manual-needed item states, and reviewers can inspect status and
artifact audit details without mutation controls.

Hardening already in place:

- candidates come from final Workflow 6 include plus maybe outcomes, not draft
  corpus membership;
- final exclude outcomes are not queued for automatic retrieval;
- retrieval dispatches through the background queue;
- artifact routes require project access;
- source audit is read through core full-text read APIs;
- manual-upload-only protocol blocks automatic retrieval.

Key files:

- `app/Actions/Projects/BuildProjectFullTextCandidates.php`
- `app/Actions/Projects/StartProjectFullTextBatch.php`
- `app/Jobs/RunProjectFullTextBatchJob.php`
- `app/Queries/Projects/ProjectFullTextReadModel.php`
- `resources/js/pages/projects/full-text.tsx`
- `tests/Feature/ProjectFullTextWorkflowTest.php`

## Workflow 6 Notes

Workflow 6 is integrated in the app flow, not only covered by tests.

The owner can start screening from a locked representative snapshot, reviewers
can work assigned title-and-abstract queues, adjudicators can resolve conflicts
with rationale, and completed screening produces final per-work outcomes for
full-text handoff readiness.

Hardening already in place:

- screening reads locked representative snapshot membership, not mutable draft
  corpus membership;
- include, maybe, and exclude decisions map to core screening verdicts;
- completed handoff counts final per-work outcomes instead of raw reviewer
  votes;
- conflict resolution stays auditable through rationale and event rows;
- reviewer and viewer access remains read-only outside permitted actions.

Key files:

- `app/Actions/Projects/StartProjectScreeningBatch.php`
- `app/Actions/Projects/RecordProjectScreeningDecision.php`
- `app/Actions/Projects/ResolveProjectScreeningConflict.php`
- `app/Queries/Projects/ProjectScreeningReadModel.php`
- `app/Queries/Projects/ProjectScreeningQueueReadModel.php`
- `resources/js/pages/projects/screening.tsx`
- `resources/js/pages/projects/screening-queue.tsx`
- `tests/Feature/ProjectScreeningWorkflowTest.php`

## Validation Commands

Run these before merging product work:

```powershell
composer test
npm run test:ui
npm run lint:check
npm run format:check
npm run types:check
npm run build:check
composer validate --strict
composer audit --format=plain --abandoned=ignore
git diff --check
```

CI currently runs:

- quality;
- tests on PHP 8.3;
- tests on PHP 8.4;
- tests on PHP 8.5.

The Workflow 8 merge passed all of them.

## Browser Verification

Use `docs/demo-scenarios.md` as the living browser script. Store local
screenshots under `output/playwright/`; the folder is ignored by Git.

Workflow 8 screenshot targets:

- `output/playwright/workflow-8-full-text-screening-readiness.png`
- `output/playwright/workflow-8-full-text-screening-setup.png`
- `output/playwright/workflow-8-full-text-queue.png`
- `output/playwright/workflow-8-full-text-decision.png`
- `output/playwright/workflow-8-full-text-conflict.png`
- `output/playwright/workflow-8-full-text-completed.png`
- `output/playwright/workflow-8-viewer-readonly.png`

Workflow 7 screenshot targets:

- `output/playwright/workflow-7-full-text-ready.png`
- `output/playwright/workflow-7-full-text-running.png`
- `output/playwright/workflow-7-full-text-completed.png`
- `output/playwright/workflow-7-full-text-artifact-detail.png`
- `output/playwright/workflow-7-reviewer-readonly.png`

When changing UI, also check:

- `docs/frontend-quality.md`
- `docs/ui-system-shadcn.md`
- `docs/design-tokens.md`
- `docs/brand-identity.md`

## Next Workflow Boundary

The next product workflow should be Workflow 9: data extraction.

Recommended first slice:

1. Start from `docs/workflow-9-data-extraction.md`.
2. Build candidates only from final Workflow 8 full-text `include` outcomes.
3. Keep full-text excludes, maybes, failed retrieval rows, skipped rows, and
   manual-needed rows out of extraction candidates in the first slice.
4. Add versioned extraction templates and require template lock before
   assignments start.
5. Add reviewer extraction assignments and field-level value recording.
6. Require evidence pointers for configured fields.
7. Add field-level conflict detection and adjudication with audit reason.
8. Add a dataset preview read model.
9. Extend `DemoAccessSeeder` with ready, active, conflict, completed, and
   read-only extraction states.
10. Add Pest, component, and browser coverage from `docs/demo-scenarios.md`.

Prepared follow-up boundaries:

- Workflow 10 quality appraisal:
  `docs/workflow-10-quality-appraisal-risk-of-bias.md`.
- Workflow 11 synthesis, PRISMA counts, and exports:
  `docs/workflow-11-synthesis-prisma-exports.md`.
- Workflow 12 production hardening and launch controls:
  `docs/workflow-12-production-hardening-launch-controls.md`.
