# Developer Handoff

Last updated: 2026-05-30.

This document is the fastest route into Nexus Scholar Web for a developer taking
over the project. It summarizes the current product state, local setup, demo
data, validation commands, and the next implementation boundary.

## Repository State

- Default branch: `master`.
- Current application: hosted Laravel/Inertia SaaS-style app for Nexus Scholar.
- Package dependency: `nexus-scholar/core:^1.0` from Packagist.
- Latest merged workflow: workflow 6, title and abstract screening, merged in
  PR #11.
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
| 7. Full-text retrieval and artifact audit | Prepared | `docs/workflow-7-full-text-retrieval.md` |

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

The workflow 6 merge passed all of them.

## Browser Verification

Use `docs/demo-scenarios.md` as the living browser script. Store local
screenshots under `output/playwright/`; the folder is ignored by Git.

Workflow 6 screenshot targets:

- `output/playwright/workflow-6-screening-overview.png`
- `output/playwright/workflow-6-screening-setup.png`
- `output/playwright/workflow-6-reviewer-queue.png`
- `output/playwright/workflow-6-reviewer-decision-submitted.png`
- `output/playwright/workflow-6-conflict-resolution.png`
- `output/playwright/workflow-6-conflict-resolved.png`
- `output/playwright/workflow-6-handoff-ready.png`
- `output/playwright/workflow-6-viewer-readonly.png`

When changing UI, also check:

- `docs/frontend-quality.md`
- `docs/ui-system-shadcn.md`
- `docs/design-tokens.md`
- `docs/brand-identity.md`

## Next Workflow Boundary

The next product workflow should be full-text retrieval and artifact audit.

Recommended first slice:

1. Start from `docs/workflow-7-full-text-retrieval.md`.
2. Add web-owned full-text batch and item migrations.
3. Build candidates from completed Workflow 6 outcomes: include plus maybe,
   never exclude by default.
4. Add full-text policy methods and project routes.
5. Implement an overview page with readiness, progress, candidate table, and a
   right-side artifact audit sheet.
6. Dispatch retrieval in a queue job and call core `RetrieveFullTextHandler`
   with the project id.
7. Read source audit through `FullTextFetchReaderPort`.
8. Extend `DemoAccessSeeder` with no-batch, running, completed, failed, and
   skipped full-text states using fake artifacts.
9. Add Pest, component, and Python Playwright coverage from
   `docs/demo-scenarios.md`.

Do not start full-text screening, exports, citation graphs, AI assistance, or
billing before retrieval status and artifact audit are stable.
