# Core And CLI Workflow Scan

This note records the scan that should guide the next Nexus Scholar Web
workflow after project/protocol setup.

## Sources Scanned

- `repos/core/README.md`
- `repos/core/docs/v1.0/tutorials/*.md`
- `repos/core/docs/v1.0/modules/01-core-architecture-and-package-boundary.md`
- `repos/core/docs/v1.0/modules/08-core-laravel-integration-persistence-jobs-read-apis.md`
- `repos/core/src/Laravel/Command/NexusSearchCommand.php`
- `repos/core/src/Laravel/Command/NexusScreenCommand.php`
- `repos/core/src/Laravel/Migration`
- `repos/nexus-cli/app/Console/Commands`
- `repos/nexus-cli/docs/commands.md`
- `repos/nexus-cli/docs/commands/pipeline/README.md`
- `repos/nexus-cli/docs/agent-notes.md`

## Boundary

`nexus-scholar/core` is the reusable engine. It owns search, persisted corpus
membership, deduplication, corpus locking, screening, adjudication, graph,
full-text retrieval, exports, jobs, events, migrations, and read ports.

The web application should own product UX, user/workspace access, project
membership, protocol authoring, form validation, background-job orchestration,
and browser-facing reports.

Do not shell out to `nexus-cli` from the web app. Use `core` handlers, jobs,
ports, and read APIs directly from Laravel. Treat `nexus-cli` as the proven
workflow reference for sequencing, command UX, and audit expectations.

## Core Package Surface

Core package-owned commands are intentionally limited to:

- `nexus:search`
- `nexus:screen`

Host applications are expected to compose richer workflows around:

- `SearchExecutorPort`
- `SearchPlanParserPort`
- `SearchPlanRunner`
- `LockCorpusHandler`
- `ScreenCorpusHandler`
- `AdjudicateScreeningDecisionsHandler`
- `CompareScreeningRunsHandler`
- `RetrieveFullTextHandler`
- `ExportBibliographyHandler`
- `ExportHistoryReaderPort`
- `JobLifecycleReaderPort`
- `FullTextFetchReaderPort`

## CLI Workflow Shape

The CLI's DB-backed systematic-review path is:

1. Run search with a project id.
2. Review run stats.
3. Screen project works.
4. Lock the corpus when membership is ready to freeze.
5. Record human adjudication decisions.
6. Compare screening runs.
7. Retrieve legal open-access full text.
8. Inspect full-text fetch history.
9. Build graph artifacts.
10. Export final or citable bibliography.
11. Inspect export history and job progress.

Run-file and wiki workflows remain useful for local exploration, but project
mode is the path to bring into Nexus Scholar Web.

## Web Implementation Implications

Workflow 2 should remain project/protocol only. It should not start search,
import, deduplication, screening, or export.

The next workflow should start with search setup:

- convert the completed protocol into a host-owned search plan draft;
- let users choose providers, query strings, date ranges, raw payload policy,
  and provider limits;
- dispatch search in the background through core's search execution surface;
- show job lifecycle state through the core read port;
- show provider-level progress, failures, raw counts, and unique-work counts;
- persist search provenance through core's package tables.

The web app already uses the core-owned `projects` table. Project UI columns
such as `workspace_id`, `owner_user_id`, `slug`, `review_type`, memberships,
and protocol records extend that table for product UX. Core workflow status
values must remain compatible with the package, especially the lock status
value `locked`.

## Integration Risks

- Core sets `projects.status` to `locked` when `LockCorpusHandler` locks a
  corpus. The web status enum must accept this value.
- Product statuses such as `ready_for_search` and `draft_corpus` are web UX
  states. Core may not know about them.
- Search and snowballing mutate corpus membership and must be blocked once the
  core lock is active.
- Screening and adjudication expect stable project membership. Core screening
  requires locked corpus membership.
- Full-text retrieval is partial by design. The UI should present success,
  skipped, and failed fetches as audit facts, not as generic errors.
- Export history and full-text history should be read through core reader
  ports, not direct SQL.

## Next Workflow Candidate

Start with `Workflow 3: Search Plan And Search Run`.

First slice:

1. Add a project search-plan page.
2. Store host-owned plan drafts or plan versions.
3. Validate provider selection and query fields against protocol constraints.
4. Dispatch a background search job through core.
5. Add a search-run overview page backed by core job lifecycle and search
   provenance.
6. Add browser scenarios for owner/admin allowed, reviewer/viewer read-only,
   unverified denied, and locked project blocked.
