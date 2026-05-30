# Developer Handoff

Last updated: 2026-05-30.

This document is the fastest route into Nexus Scholar Web for a developer taking
over the project. It summarizes the current product state, local setup, demo
data, validation commands, and the next implementation boundary.

## Repository State

- Default branch: `master`.
- Current application: hosted Laravel/Inertia SaaS-style app for Nexus Scholar.
- Package dependency: `nexus-scholar/core:^1.0` from Packagist.
- Latest merged workflow: workflow 5, deduplication and corpus lock, merged in
  PR #5.
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

## Workflow 5 Notes

Workflow 5 is integrated in the app flow, not only covered by tests.

The owner can open deduplication from corpus review, run deduplication, inspect
duplicate clusters, and lock the corpus with an audit reason. Locking creates a
representative-only corpus snapshot for downstream screening.

Hardening already in place:

- dedup freshness fingerprints query membership, work metadata, identifiers,
  and authors;
- lock refuses stale or incomplete dedup evidence;
- locked projects block dedup reruns and search mutation;
- reviewers can inspect evidence but cannot run deduplication or lock.

Key files:

- `app/Actions/Projects/RunProjectCorpusDeduplication.php`
- `app/Actions/Projects/LockProjectCorpus.php`
- `app/Actions/Projects/ProjectCorpusMembershipHasher.php`
- `app/Queries/Projects/ProjectDeduplicationReadModel.php`
- `resources/js/pages/projects/deduplication.tsx`
- `tests/Feature/ProjectDeduplicationWorkflowTest.php`

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

The workflow 5 merge passed all of them.

## Browser Verification

Use `docs/demo-scenarios.md` as the living browser script. Store local
screenshots under `output/playwright/`; the folder is ignored by Git.

Workflow 5 screenshot targets:

- `output/playwright/workflow-5-dedup-readiness.png`
- `output/playwright/workflow-5-dedup-stale.png`
- `output/playwright/workflow-5-dedup-cluster-detail.png`
- `output/playwright/workflow-5-lock-confirmation.png`
- `output/playwright/workflow-5-locked-snapshot.png`
- `output/playwright/workflow-5-reviewer-readonly.png`

When changing UI, also check:

- `docs/frontend-quality.md`
- `docs/ui-system-shadcn.md`
- `docs/design-tokens.md`
- `docs/brand-identity.md`

## Next Workflow Boundary

The next product workflow should be title and abstract screening.

Recommended first slice:

1. Create `docs/workflow-6-title-abstract-screening.md`.
2. Define the locked-corpus input contract from workflow 5.
3. Design reviewer assignment, include/exclude/maybe decisions, conflict
   states, and audit events before implementing UI.
4. Extend `DemoAccessSeeder` with screening-ready records and reviewer states.
5. Add Pest tests for policy, assignment, decision persistence, and conflict
   creation.
6. Add React tests for decision controls and conflict badges.
7. Verify owner, reviewer, viewer, and workspace admin browser scenarios.

Do not start full-text retrieval, exports, AI assistance, or billing before the
screening loop is stable.
