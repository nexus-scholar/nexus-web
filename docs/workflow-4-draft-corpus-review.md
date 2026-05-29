# Workflow 4: Draft Corpus Review

Prepared on 2026-05-29.

Status: preparation only. This document defines the next implementation slice
and its dependencies before code is written.

## Goal

Turn completed search runs into a trustworthy draft-corpus review surface.
Researchers should be able to inspect what entered the project corpus, where
each record came from, which metadata is missing, and whether the project is
ready for deduplication and eventual lock.

Workflow 4 is the bridge between search execution and screening readiness. It
must make corpus membership visible before the app asks a team to freeze the
scientific boundary of a review.

## Sources Checked

- `docs/core-cli-workflow-scan.md`
- `docs/workflow-3-search-plan-run.md`
- `docs/demo-scenarios.md`
- `docs/frontend-quality.md`
- `docs/ui-system-shadcn.md`
- `repos/core/docs/v1.0/modules/03-core-search-and-providers.md`
- `repos/core/docs/v1.0/modules/04-core-deduplication-and-corpus-lock.md`
- `repos/core/docs/v1.0/modules/05-core-screening-and-adjudication.md`
- `repos/core/docs/v1.0/tutorials/advanced-citation-graphs-and-snowballing.md`
- `repos/core/src/Shared/Port/ProjectCorpusWorksPort.php`
- `repos/core/src/Laravel/Persistence/EloquentProjectWorkMembership.php`
- `repos/core/src/Laravel/Persistence/EloquentCorpusSnapshotRepository.php`
- `repos/core/src/Laravel/Persistence/EloquentScreeningWorkSource.php`
- `repos/nexus-web/app/Jobs/RunProjectSearchPlanJob.php`
- `repos/nexus-web/app/Http/Controllers/Projects/ProjectSearchRunController.php`
- `repos/nexus-web/database/migrations/2026_04_27_000003_create_scholarly_works_table.php`
- `repos/nexus-web/database/migrations/2026_04_27_000009_create_query_works_table.php`
- `repos/nexus-web/database/migrations/2026_04_28_000010_create_corpus_snapshots_table.php`

## Product Boundary

Nexus Scholar Web owns:

- the project-facing corpus review page,
- filters, pagination, and browser-facing record detail,
- role-scoped access,
- demo data for stable local verification,
- workflow navigation from project overview and search-run pages,
- visual explanations for draft, locked, empty, and incomplete states.

`nexus-scholar/core` owns:

- search persistence,
- canonical scholarly work persistence,
- project corpus membership inference,
- lock state and corpus snapshots,
- deduplication handlers and clusters,
- locked-corpus guarantees required by screening.

Do not shell out to `nexus-cli`. Use core tables, ports, and handlers through
Laravel.

## Core Contract

Draft corpus membership is inferred from:

- `search_queries.project_id`,
- `query_works.search_query_id`,
- `query_works.work_id`.

Locked corpus membership is read from:

- `corpus_snapshots`,
- `corpus_snapshot_works`.

Record details come from:

- `scholarly_works`,
- `work_external_ids`,
- `work_providers`,
- `authors`,
- `work_authors`,
- `search_queries`,
- `query_works`,
- optionally `dedup_clusters` and `cluster_members` when clusters exist.

Important behavior from core:

- `ProjectCorpusWorksPort::workIds()` returns authoritative work ids for a
  project. Draft projects infer membership from query links. Locked projects
  use the latest immutable snapshot.
- `EloquentCorpusSnapshotRepository` stores query ids, provider aliases, and
  query-work provenance at lock time.
- `ScreenCorpusHandler` requires locked corpus membership. Do not start the
  screening workflow until lock behavior is designed and tested.
- Search and snowballing mutate corpus membership and must be blocked after
  lock.

## First Slice

Implement a read-first corpus review workflow:

1. Add `GET /projects/{project}/corpus`.
2. Add a project overview action when the project has a draft or locked corpus.
3. Add a search-run overview action after a run has completed or partially
   completed.
4. Show corpus metrics:
   - unique works,
   - raw query links,
   - search queries represented,
   - provider coverage,
   - year range,
   - missing abstracts,
   - missing identifiers,
   - retracted records,
   - duplicate cluster count when clusters exist.
5. Show a paginated corpus record list with server-side filters.
6. Show record detail in the same page through a responsive detail panel.
7. Show provenance for each record:
   - search query label or text,
   - provider alias,
   - provider work id,
   - rank,
   - first seen timestamp.
8. Keep the surface read-only for owner, workspace admin, reviewer, and viewer.
9. Show locked corpus snapshot metadata when the project is locked.

This slice should not mutate corpus membership.

## Explicit Non-Goals

Do not implement these in the first slice:

- screening queue,
- human adjudication,
- AI screening,
- full-text retrieval,
- export packages,
- citation graph analysis,
- snowballing,
- manual record include/exclude,
- manual duplicate merge or split,
- corpus lock and unlock actions.

The page may show readiness for deduplication and locking, but the first slice
does not execute those operations.

## Route And Policy

Recommended route:

- `GET /projects/{project}/corpus`

Recommended route name:

- `projects.corpus.index`

Recommended controller:

- `App\Http\Controllers\Projects\ProjectCorpusController@index`

Recommended policy:

- Add `viewCorpus(User $user, Project $project): bool`.
- For the first slice, delegate to `view()`.
- Keep future mutation policies separate:
  - `deduplicateCorpus`,
  - `lockCorpus`,
  - `unlockCorpus`.

Role behavior:

| Actor | Corpus review | Future dedup | Future lock |
| --- | --- | --- | --- |
| Project owner | Yes | Later | Later |
| Workspace owner/admin | Yes | Later | Later |
| Reviewer | Yes | No | No |
| Viewer | Yes | No | No |
| Unverified or disabled user | No | No | No |
| Suspended workspace member | No | No | No |

## Read Model

Create a web-owned read model instead of putting SQL inside the controller.

Recommended class:

- `App\Queries\Projects\ProjectCorpusReadModel`

Responsibilities:

- choose draft membership or locked snapshot membership,
- return page metrics,
- return paginated rows,
- return filter options,
- return the selected record detail,
- avoid N+1 queries for identifiers, providers, authors, and provenance.

Recommended method shape:

```php
public function forProject(Project $project, CorpusFilters $filters): CorpusPage;
```

The read model should use query builder or small web Eloquent models for
read-only payload assembly. Do not depend on core internal Eloquent model
classes from the web namespace.

### Draft Query Shape

Base membership:

```sql
select distinct query_works.work_id
from query_works
join search_queries on search_queries.id = query_works.search_query_id
where search_queries.project_id = :project_id
```

Useful aggregate joins:

- `scholarly_works.id = membership.work_id`
- `work_external_ids.work_id = scholarly_works.id`
- `work_providers.work_id = scholarly_works.id`
- `query_works.work_id = scholarly_works.id`
- `search_queries.id = query_works.search_query_id`

### Locked Query Shape

Base membership:

```sql
select corpus_snapshot_works.work_id
from corpus_snapshot_works
join corpus_snapshots on corpus_snapshots.id = corpus_snapshot_works.snapshot_id
where corpus_snapshots.project_id = :project_id
order by corpus_snapshots.locked_at desc, corpus_snapshots.created_at desc
```

Use snapshot `search_query_ids`, `provider_aliases`, and `provenance` as the
final audit source when locked. Query-work rows may still exist, but snapshot
metadata is the citable source for locked membership.

## Filters

First-slice filters:

- text search over title and abstract,
- provider alias,
- search query,
- year from and year to,
- identifier namespace,
- missing abstract,
- missing identifier,
- retracted records,
- duplicate status:
  - all,
  - in cluster,
  - not clustered.

Use URL query parameters and Inertia page props. Do not introduce a client-side
store for durable filter state.

## UI Shape

Use a dense operational layout:

- `PageShell`
- `PageHeader`
- existing metric cards,
- a compact filter bar,
- a table or table-like list,
- right-side detail panel on desktop,
- stacked detail below the list on narrow screens.

Recommended new product components:

- `CorpusStatusBadge`
- `CorpusMetricStrip`
- `CorpusFilterBar`
- `CorpusRecordTable`
- `CorpusRecordDetail`
- `ProviderProvenanceList`
- `WorkIdentifierList`
- `MetadataCompletenessBadge`

Do not build the workflow as a marketing page, hero, or decorative dashboard.
The user is evaluating research evidence, so the interface should prioritize
scan speed, provenance, and audit confidence.

## UI Dependencies

Current installed shadcn primitives do not include `table`.

The shadcn registry search on 2026-05-29 returned:

- `@shadcn/table`,
- `@shadcn/table-demo`,
- `@shadcn/data-table-demo`,
- `@shadcn/typography-table`,
- `@shadcn/dashboard-01`.

Dependency decision:

- Add `@shadcn/table` during implementation.
- Do not add a full DataTable dependency in this slice.
- Use server-side filters and pagination through Laravel/Inertia.
- Revisit TanStack Table only if users need column sorting, row selection,
  bulk actions, or saved table views.

Command to run during implementation:

```powershell
npx shadcn@latest add table
```

Note: `npx shadcn@latest docs table --json` failed once during preparation
because `ui.shadcn.com` DNS resolution failed locally. `npx shadcn@latest info
--json` and registry search worked.

## Seeder Dependencies

Extend `DemoAccessSeeder` with deterministic corpus data for the
`Cardiometabolic Review Search Strategy` project.

Seed:

- a completed search run,
- two core search query rows matching the demo search-plan items,
- 12 to 20 scholarly works,
- mixed providers:
  - `openalex`,
  - `crossref`,
  - `pubmed`,
  - `semantic_scholar`,
- external ids:
  - DOI,
  - OpenAlex,
  - PubMed,
  - Semantic Scholar,
- authors and work-author rows,
- query-work provenance with ranks and seen timestamps,
- at least one missing abstract,
- at least one missing identifier,
- at least one retracted record,
- at least one duplicate cluster with two members.

The seeder must stay idempotent. Use stable `updateOrCreate` keys for demo
queries and deterministic external ids.

Do not rely on live provider calls for browser demos or CI.

## Browser Scenarios

Add these to `docs/demo-scenarios.md` once implementation starts:

1. Owner opens the corpus review from the project overview.
2. Owner opens corpus review from a completed search-run overview.
3. Reviewer opens corpus review and sees read-only provenance.
4. Viewer opens corpus review and sees no mutation controls.
5. Empty draft project shows a clear "run search first" state.
6. Locked project shows snapshot metadata instead of draft-only messaging.
7. Filters preserve URL state and do not lose the selected project context.
8. Record detail shows identifiers, providers, query provenance, and metadata
   quality flags.

Screenshot targets:

- `output/playwright/workflow-4-corpus-overview.png`
- `output/playwright/workflow-4-corpus-detail.png`
- `output/playwright/workflow-4-corpus-filters.png`
- `output/playwright/workflow-4-reviewer-corpus-readonly.png`
- `output/playwright/workflow-4-corpus-empty.png`
- `output/playwright/workflow-4-corpus-locked.png`

## Automated Tests

Pest feature tests:

- owner can view draft corpus,
- reviewer and viewer can view draft corpus read-only,
- unrelated project member cannot view corpus,
- suspended workspace blocks corpus review,
- empty project returns empty-state props,
- draft membership is inferred from query-work rows,
- locked membership uses the latest corpus snapshot,
- filters constrain corpus rows on the server,
- pagination preserves filter query parameters,
- provenance payload includes provider alias, provider work id, query id, rank,
  and seen timestamp,
- metrics include missing abstract, missing identifier, retracted, provider,
  query, and duplicate counts.

Vitest component tests:

- `CorpusStatusBadge`,
- `MetadataCompletenessBadge`,
- `WorkIdentifierList`,
- `ProviderProvenanceList`,
- `CorpusFilterBar` submit/reset behavior,
- `CorpusRecordDetail` missing metadata states.

Browser verification:

- run seeded actor scenarios on desktop,
- capture screenshots,
- check bottom-of-page filters, popovers, and detail panels do not overflow,
- keep mobile as a basic responsive check only. Screening can become
  mobile-friendly later.

Validation gates before commit:

```powershell
composer test
npm run lint:check
npm run format:check
npm run types:check
npm run test:ui
npm run build:check
composer validate --strict
composer audit --format=plain --abandoned=ignore
git diff --check
```

## Implementation Order

1. Add shadcn `table`.
2. Add corpus read model and focused DTO/value objects if needed.
3. Add corpus route, policy method, and controller.
4. Add Inertia page with read-only payload.
5. Add product UI components and component tests.
6. Extend demo seeder with deterministic corpus rows.
7. Add Pest feature coverage.
8. Add project overview and search-run links.
9. Update demo scenarios and run browser verification.
10. Run full validation gates.

## Risks

- Core `scholarly_works` does not contain `project_id`; project membership
  must come through query links or locked snapshots.
- Draft membership can contain repeated query-work links for the same work.
  The UI must distinguish unique works from raw provenance links.
- Locked snapshots are the final audit source. The UI must not quietly rebuild
  locked membership from mutable query-work rows.
- Dedup clusters can exist before lock, but the first slice should not imply
  deduplication has been run when no clusters exist.
- Search-run totals can be zero when CI uses a fake executor. Demo corpus data
  must be seeded explicitly.
- Provider alias normalization must stay compatible with core:
  `semantic_scholar`, not `semantic-scholar`, in persisted rows.

## Readiness Checklist

Before implementation starts, confirm:

- branch is `cdx/workflow-4-corpus-prep` or a fresh implementation branch,
- repo is clean,
- `@shadcn/table` install diff is reviewed,
- demo corpus fixture shape is accepted,
- corpus lock remains deferred from the first slice,
- browser scenario names match this document,
- the page is read-only for every actor in this slice.

