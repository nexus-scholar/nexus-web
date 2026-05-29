# Workflow 3: Search Plan And Search Run

This document prepares the first implementation slice after project creation
and protocol completion.

## Goal

Turn a completed project protocol into a repeatable search-plan draft, let an
authorized owner or workspace admin run it in the background, and show the
resulting search-run status and provider provenance.

Workflow 3 starts corpus construction. It should not implement screening,
adjudication, full-text retrieval, graph analysis, or exports.

## Sources Checked

- `docs/core-cli-workflow-scan.md`
- `docs/demo-scenarios.md`
- `repos/core/docs/v1.0/modules/03-core-search-and-providers.md`
- `repos/core/docs/v1.0/modules/08-core-laravel-integration-persistence-jobs-read-apis.md`
- `repos/core/src/Search/Application/UseCase/SearchAcrossProviders.php`
- `repos/core/src/Search/Application/UseCase/PersistentSearchRunner.php`
- `repos/core/src/Search/Application/Plan/SearchPlan*.php`
- `repos/core/src/Search/Infrastructure/Plan/YamlSearchPlanParser.php`
- `repos/core/src/Shared/Port/JobLifecycleReaderPort.php`
- `repos/nexus-cli/app/Console/Commands/NexusSearch.php`
- `repos/nexus-cli/docs/commands.md`

## Product Boundary

Nexus Scholar Web owns:

- search-plan draft UI and validation,
- project/protocol-to-plan conversion,
- plan versioning or draft persistence,
- background dispatch UX,
- SaaS limits and owner/admin authorization,
- browser-facing run status, provider progress, and error presentation.

`nexus-scholar/core` owns:

- provider adapters and aliases,
- `SearchAcrossProviders`,
- `SearchExecutorPort`,
- `PersistentSearchRunner`,
- `SearchPlanRunner`,
- search query, provider, work, and provenance persistence,
- project lock blocking,
- job lifecycle read APIs.

Do not shell out to `nexus-cli`. Use it only as a sequencing reference.

## Core Integration Shape

The direct single-query execution command is:

- `SearchAcrossProviders`
  - `query`
  - `projectId`
  - `maxResults`
  - `yearFrom`
  - `yearTo`
  - `providerAliases`
  - `includeRawData`

The package service provider binds `SearchExecutorPort` to the persistent
runner, so web jobs should depend on the port. `PersistentSearchRunner` records
started, provider stats, work rows, completed, and failed states. It throws when
core reports the project is locked.

The core plan runner accepts `SearchPlan`, `SearchPlanItem`, and
`SearchPlanRunOptions`, but the web app does not need to write YAML files. The
first web slice can store a host-owned structured plan draft and convert each
item into `SearchAcrossProviders`.

## First Slice

1. Add host-owned search-plan draft persistence.
2. Add a project search-plan page reachable from the project overview after the
   protocol is complete.
3. Seed one demo project with a completed protocol and a ready search-plan
   draft.
4. Build a guided search-plan form:
   - query id,
   - label,
   - query text,
   - providers,
   - year range from protocol defaults,
   - per-query result limit,
   - include raw provider payload policy.
5. Validate providers against the known core aliases:
   - `openalex`
   - `crossref`
   - `semantic_scholar`
   - `arxiv`
   - `pubmed`
   - `doaj`
   - `ieee`
6. Add a background job that runs a selected plan item or all draft items
   through `SearchExecutorPort`.
7. Add a search-run overview page that reads persisted core rows and job
   lifecycle state.

## Initial Routes

Use product-owned routes under the existing project boundary:

- `GET /projects/{project}/search-plan`
- `PATCH /projects/{project}/search-plan`
- `POST /projects/{project}/search-runs`
- `GET /projects/{project}/search-runs/{run}`

Keep the project overview as the main entry point. Add a `Search plan` action
only when the protocol is complete or ready for search.

## Policy Rules

- Owner: view, edit plan, run search.
- Workspace admin: view, edit plan, run search.
- Reviewer: view plan and run status only.
- Viewer: view plan and run status only.
- Unverified user: denied by existing verified middleware.
- Locked project: cannot edit or run a search plan.

## UI Rules

- Keep the page dense and operational, matching the project/protocol UI.
- Reuse `ProviderTagSelector` for providers.
- Use a query-list editor before introducing a heavy table dependency.
- Show provider failures as audit facts, not generic fatal errors.
- Show raw and unique counts separately.
- Always make the background state visible after dispatch.

## Test And Demo Requirements

Automated coverage:

- Pest route, policy, validation, background dispatch, and locked-project tests.
- Vitest tests for plan form, provider reuse, read-only state, and run status
  components.
- Build, lint, type, format, and `git diff --check` gates.

Browser scenarios:

- owner creates or edits a search-plan draft,
- owner dispatches a search run,
- reviewer can view but not edit or run,
- viewer can view but not edit or run,
- locked project blocks search dispatch,
- provider failure is visible without losing successful providers.

## Risks To Handle Early

- Core and web share the `projects` table. Keep status values compatible with
  core, especially `locked`.
- Provider aliases must remain normalized to core values; avoid mixing
  `semantic-scholar` and `semantic_scholar` in persisted state.
- Search mutates corpus membership. Do not allow it after lock.
- Search can be slow or partially fail. The UI should assume background
  execution from the first implementation.
- Job lifecycle records may not map one-to-one with product run pages. Add a
  host-owned search-run record if a stable browser URL needs more metadata than
  core provides.

## Next Action

Start with the data model and route shell:

1. Add host-owned search-plan draft tables and factories.
2. Add project policy methods for `viewSearchPlan`, `updateSearchPlan`, and
   `runSearch`.
3. Add a minimal Inertia search-plan page with provider tags and query draft
   rows.
4. Protect the first slice with Pest and Vitest before wiring the background
   search job.
