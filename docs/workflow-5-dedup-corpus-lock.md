# Workflow 5: Deduplication And Corpus Lock

Prepared on 2026-05-30.

Status: implemented and merged. This document records the first implementation
slice and the scientific constraints that must be preserved before screening
begins.

## Goal

Turn the draft corpus into a deduplicated, auditable, locked corpus that can
be used by screening without changing the scientific boundary of the review.

Workflow 5 is the gate between exploratory corpus growth and final review
work. It should let owners inspect duplicate evidence, understand the selected
representatives, and lock a snapshot with an audit reason. It must not allow a
team to start screening a duplicate-filled corpus by accident.

## Sources Checked

- `docs/workflow-4-draft-corpus-review.md`
- `docs/core-cli-workflow-scan.md`
- `docs/demo-scenarios.md`
- `docs/brand-identity.md`
- `docs/design-tokens.md`
- `docs/ui-system-shadcn.md`
- `repos/core/docs/v1.0/modules/04-core-deduplication-and-corpus-lock.md`
- `repos/core/docs/v1.0/modules/05-core-screening-and-adjudication.md`
- `repos/core/src/Deduplication/Application/DeduplicateCorpusHandler.php`
- `repos/core/src/Deduplication/Application/LockCorpusHandler.php`
- `repos/core/src/Deduplication/Application/UnlockCorpusHandler.php`
- `repos/core/src/Laravel/Persistence/EloquentCorpusSnapshotRepository.php`
- `repos/core/src/Laravel/Persistence/EloquentProjectLock.php`
- `repos/core/src/Laravel/Persistence/EloquentProjectWorkMembership.php`
- `repos/core/src/Laravel/Persistence/Repository/EloquentWorkRepository.php`
- `repos/core/src/Laravel/Persistence/Repository/EloquentDedupClusterRepository.php`
- `repos/core/src/Screening/Application/UseCase/ScreenCorpusHandler.php`
- `repos/core/src/Laravel/Persistence/EloquentScreeningWorkSource.php`
- `repos/nexus-cli/app/Console/Commands/NexusCorpusLock.php`
- `repos/nexus-cli/docs/commands/nexus-corpus-lock/README.md`
- `repos/nexus-cli/tests/Feature/Commands/NexusCorpusLockExportTest.php`
- `repos/nexus-web/app/Queries/Projects/ProjectCorpusReadModel.php`
- `repos/nexus-web/database/migrations/2026_04_27_000010_create_dedup_clusters_table.php`
- `repos/nexus-web/database/migrations/2026_04_27_000011_create_cluster_members_table.php`
- `repos/nexus-web/database/migrations/2026_04_28_000005_add_project_lock_lifecycle.php`
- `repos/nexus-web/database/migrations/2026_04_28_000010_create_corpus_snapshots_table.php`

## Product Boundary

Nexus Scholar Web owns:

- the project-facing deduplication review workflow,
- the lock confirmation UX and audit reason requirement,
- role-scoped mutation controls,
- representative snapshot UX,
- browser scenarios and seeded demo states,
- explanatory copy around duplicate evidence and lock consequences.

`nexus-scholar/core` owns:

- duplicate detection policies,
- cluster assembly,
- representative election,
- project lock state,
- immutable snapshot ports,
- locked-membership enforcement for screening, adjudication, export, graph,
  full-text, and corpus mutation blockers.

Do not shell out to `nexus-cli`. Use core handlers, ports, and package tables
inside Laravel. Treat `nexus-cli` as a workflow reference for lock sequencing,
output expectations, and audit language.

## Scientific Boundary

Screening must operate on the intended final corpus, not on a draft list that
still contains duplicate members.

The important implementation finding is this:

- `DeduplicateCorpusHandler` returns clusters and representatives but does not
  persist clusters by itself.
- `LockCorpusHandler` records project lock state through the lock lifecycle
  port.
- The default core `EloquentCorpusSnapshotRepository` snapshots current
  `query_works` membership. It does not automatically replace duplicate
  members with elected representatives.
- `ScreenCorpusHandler` gets works from `ProjectCorpusWorksPort`; for locked
  projects, that port reads the latest immutable snapshot.

Therefore the web app must not call the default lock path blindly after finding
duplicates. If the snapshot still includes every duplicate member, Workflow 6
will screen duplicates.

## Recommended Lock Strategy

Use a representative-aware web lock path.

Before lock, build the snapshot membership as:

- one elected representative per duplicate cluster,
- every non-clustered corpus work,
- merged provenance from all duplicate members attached to the representative
  snapshot row,
- snapshot metadata that records the deduplication run summary, policy stats,
  cluster count, representative count, and duplicate-member count.

This keeps core's locked membership contract intact because screening reads
from `corpus_snapshot_works`, but it makes the host snapshot scientifically
correct for a deduplicated review.

Implementation options:

1. Preferred: bind a web-owned `CorpusSnapshotRepositoryPort` implementation
   that creates representative-aware snapshots while preserving
   `latestForProject()` behavior.
2. Acceptable first implementation if the binding order is risky: create a
   web-owned lock application service that records project lock state,
   project-lock audit rows, representative-aware snapshot rows, and cluster
   locked flags inside one database transaction.
3. Do not implement: a lock button that snapshots raw `query_works` membership
   when duplicate clusters exist.

## First Slice

Implement a single Workflow 5 page or corpus-page mode that covers:

1. Deduplication readiness summary.
2. `Run deduplication` action for mutable draft projects.
3. Persisted duplicate cluster list.
4. Cluster detail comparison in a right-side sheet.
5. Representative work visibility.
6. Policy evidence and confidence visibility where available.
7. Lock readiness status.
8. `Lock corpus` confirmation with required audit reason.
9. Snapshot-backed locked state after success.
10. Unlock omitted from product UI for now.

The first slice should not implement manual split, manual merge, or manual
representative override. Those require a separate curation model and audit
trail.

## Explicit Non-Goals

Do not implement these in Workflow 5:

- screening queue,
- human adjudication,
- AI screening,
- full-text retrieval,
- export packages,
- citation graph analysis,
- snowballing,
- manual duplicate split,
- manual duplicate merge,
- manual representative override,
- broad saved table views,
- administrative unlock UI.

## Route And Policy

Recommended routes:

- `POST /projects/{project}/corpus/deduplicate`
- `GET /projects/{project}/deduplication`
- `POST /projects/{project}/corpus/lock`

Recommended route names:

- `projects.corpus.deduplicate`
- `projects.deduplication.index`
- `projects.corpus.lock`

The implementation can also place the deduplication view under the existing
corpus page as a tab. Use a separate route only if the page grows beyond a
single screen.

Recommended controllers:

- `App\Http\Controllers\Projects\ProjectCorpusDeduplicationController@index`
- `App\Http\Controllers\Projects\ProjectCorpusDeduplicateController@store`
- `App\Http\Controllers\Projects\ProjectCorpusLockController@store`

Recommended policy additions:

- `deduplicateCorpus(User $user, Project $project): bool`
- `lockCorpus(User $user, Project $project): bool`

Role behavior:

| Actor | View dedup | Run dedup | Lock corpus |
| --- | --- | --- | --- |
| Project owner | Yes | Yes | Yes |
| Workspace owner/admin | Yes | Yes | Yes |
| Reviewer | Yes | No | No |
| Viewer | Yes | No | No |
| Unverified or disabled user | No | No | No |
| Suspended workspace member | No | No | No |

Mutation blockers:

- project is already locked,
- workspace is suspended,
- corpus has zero works,
- no completed search-run or query-work membership exists,
- deduplication is already running,
- lock reason is blank,
- representative snapshot cannot be built,
- stale draft membership changed since the latest deduplication run.

## Application Services

Create web-owned services around core handlers instead of putting orchestration
inside controllers.

Recommended classes:

- `App\Services\Projects\ProjectCorpusSliceBuilder`
- `App\Services\Projects\ProjectCorpusDeduplicationRunner`
- `App\Services\Projects\ProjectCorpusLockService`
- `App\Queries\Projects\ProjectDeduplicationReadModel`

### ProjectCorpusSliceBuilder

Responsibilities:

- use `ProjectCorpusWorksPort::workIds($project->id)` for authoritative draft
  membership,
- load domain works through `WorkRepositoryPort::findManyByIds()` with
  internal work IDs,
- return `Nexus\Shared\Domain\CorpusSlice`,
- fail clearly when a work ID cannot be loaded.

### ProjectCorpusDeduplicationRunner

Responsibilities:

- assert project is mutable,
- call `DeduplicateCorpusHandler`,
- persist returned clusters through `ClusterRepositoryPort`,
- replace stale unlocked project clusters before saving the latest run,
- store a web audit event with counts and policy stats,
- update the project status to `draft_corpus` when appropriate.

Core does not currently provide a public `deleteByProject()` cluster port.
The web implementation may need a narrow transaction that deletes unlocked
cluster rows for this project before saving a fresh result. Locked clusters
must never be deleted.

### ProjectCorpusLockService

Responsibilities:

- require an audit reason,
- require fresh deduplication evidence when duplicate candidates exist,
- build representative-aware snapshot membership,
- record project lock state and project-lock audit,
- mark persisted clusters locked,
- record app-level audit event,
- return the latest snapshot payload for redirect messaging.

Use core ports where they support the required behavior. If the default core
snapshot repository would include duplicate members, use the web-owned
representative-aware snapshot path instead.

## Read Model

`ProjectDeduplicationReadModel` should return:

- corpus source: draft or locked,
- dedup status: not run, clean, duplicates found, stale, locked,
- last run summary,
- policy stats,
- cluster count,
- duplicate member count,
- representative count,
- unclustered count,
- lock readiness checks,
- paginated cluster list,
- selected cluster detail,
- latest snapshot payload when locked.

Cluster row fields:

- cluster id,
- representative work id,
- representative title,
- size,
- confidence,
- strategy,
- locked flag,
- member titles,
- provider aliases,
- identifier overlap,
- evidence summary.

Cluster detail fields:

- representative record,
- member records,
- duplicate evidence,
- identifiers,
- providers,
- query provenance,
- missing metadata flags,
- retracted flags.

## UI Shape

Use a dense operational layout:

- `PageShell`
- `PageHeader`
- status badges,
- compact readiness rail,
- cluster review table,
- right-side cluster detail sheet,
- lock confirmation dialog or sheet,
- audit/reason textarea,
- snapshot summary after lock.

Recommended product components:

- `DedupStatusBadge`
- `DedupReadinessRail`
- `DedupClusterTable`
- `DedupClusterDetail`
- `DuplicateEvidenceList`
- `RepresentativeWorkCard`
- `CorpusLockPanel`
- `LockReasonDialog`
- `SnapshotSummaryPanel`

Avoid a decorative dashboard. The user is making a scientific/audit decision,
so the screen should emphasize evidence, representatives, blockers, and final
snapshot consequences.

## UX States

### Not Run

Show corpus counts and an enabled `Run deduplication` action for owners/admins.
Reviewers and viewers see the same readiness state without the action.

### Clean Corpus

Show that no duplicate clusters were found. Allow lock with a required audit
reason.

### Duplicates Found

Show duplicate groups and elected representatives. Allow lock only through the
representative-aware lock path. The confirmation should state how many duplicate
members will be represented by elected representatives in the locked snapshot.

### Stale Deduplication

If query-work membership changed after the last dedup run, show a blocker and
require rerun before lock.

### Locked

Show snapshot metadata and disable deduplication and lock actions. The UI
should link forward to screening setup once Workflow 6 exists.

## Demo Data

Extend `DemoAccessSeeder` only during implementation.

Required states:

- draft corpus with no dedup run,
- draft corpus with duplicate clusters,
- draft corpus with clean dedup result,
- locked representative snapshot,
- reviewer read-only state,
- viewer read-only state.

The existing `Cardiometabolic Review Search Strategy` project can remain the
main duplicate-cluster demo. Add a clean no-duplicate project only if the UI
needs a visually different state.

## Browser Scenarios

Add these to `docs/demo-scenarios.md` during implementation:

1. Owner opens deduplication readiness from the corpus page.
2. Owner runs deduplication and sees persisted cluster counts.
3. Owner opens a duplicate cluster and compares representative vs members.
4. Reviewer opens deduplication and sees read-only evidence.
5. Viewer opens deduplication and sees no mutation controls.
6. Owner attempts to lock without a reason and sees validation.
7. Owner locks with a reason and sees snapshot metadata.
8. Locked project blocks rerun deduplication and search mutation.
9. Stale deduplication blocks lock until rerun.

Screenshot targets:

- `output/playwright/workflow-5-dedup-readiness.png`
- `output/playwright/workflow-5-dedup-cluster-detail.png`
- `output/playwright/workflow-5-lock-confirmation.png`
- `output/playwright/workflow-5-locked-snapshot.png`
- `output/playwright/workflow-5-reviewer-readonly.png`

## Automated Tests

Pest feature tests:

- owner can view deduplication readiness,
- reviewer and viewer can view dedup evidence read-only,
- unrelated users cannot view or mutate,
- suspended workspace blocks access,
- owner can run deduplication for mutable project,
- deduplication persists clusters and representative IDs,
- rerun replaces stale unlocked clusters,
- locked project blocks deduplication rerun,
- lock requires audit reason,
- lock creates representative-aware snapshot,
- lock marks clusters locked,
- lock writes project lock audit and app audit event,
- lock blocks search dispatch after success,
- stale deduplication blocks lock.

Vitest component tests:

- `DedupStatusBadge`,
- `DedupReadinessRail`,
- `DedupClusterTable` sorting/selection/read-only states,
- `DedupClusterDetail` evidence rendering,
- `LockReasonDialog` validation and summary,
- locked state controls disabled.

Browser verification:

- desktop owner flow from corpus review to dedup readiness,
- cluster sheet visual check,
- lock confirmation visual check,
- reviewer read-only check,
- locked state check.

## Implementation Order

1. Add `ProjectDeduplicationReadModel`.
2. Add `ProjectCorpusSliceBuilder`.
3. Add deduplication routes, policy methods, and controller.
4. Add deduplication page or corpus tab with read-only cluster table.
5. Add UI component tests.
6. Implement `ProjectCorpusDeduplicationRunner`.
7. Persist and refresh clusters after running deduplication.
8. Add lock policy method, validation request, and lock controller.
9. Implement representative-aware snapshot creation.
10. Add seeded demo states.
11. Update `docs/demo-scenarios.md`.
12. Run browser verification and screenshots.
13. Run full validation gates.

## Risks

- The default core snapshot path can include duplicate members because it reads
  raw query-work membership. This must be handled before exposing lock.
- Core's cluster repository can save clusters but does not expose a project
  purge operation. Rerun behavior needs a narrow, tested replacement strategy.
- Manual merge/split is not in v1. Do not imply users can override
  representatives yet.
- Lock is scientifically consequential. Require owner/admin and an audit
  reason.
- If membership changes after deduplication, old clusters may be stale. Detect
  this before lock.
- Screening reads locked snapshot membership. Workflow 6 depends on Workflow 5
  producing the correct snapshot.

## Readiness Checklist

Before implementation starts:

- repo is clean,
- branch is fresh,
- representative-aware lock strategy is accepted,
- stale-dedup detection approach is chosen,
- browser scenario names match this document,
- manual merge/split remains out of scope,
- no implementation shells out to `nexus-cli`,
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
