# Workflow 7: Full-Text Retrieval And Artifact Audit

Prepared on 2026-05-30.

Status: preparation only. Workflow 6 now produces final per-work screening
outcomes and a full-text readiness count. Workflow 7 turns those ready records
into an auditable legal open-access retrieval workflow.

Wireframes: `docs/wireframes/workflow-7-full-text-wireframes.html`.

## Goal

Retrieve and audit legal open-access full-text artifacts for records that
survived title and abstract screening.

This workflow must help research teams answer three operational questions:

- Which included or maybe records need full text?
- Which records already have a validated legal artifact?
- Which records failed, were skipped, or need manual follow-up?

It must not imply that every record can be retrieved automatically. Full-text
coverage is partial by nature, and failures are part of the evidence trail.

## Sources Checked

- `docs/workflow-6-title-abstract-screening.md`
- `docs/demo-scenarios.md`
- `docs/core-cli-workflow-scan.md`
- `docs/developer-handoff.md`
- `repos/core/README.md`
- `repos/core/docs/v1.0/modules/07-core-full-text-and-dissemination.md`
- `repos/core/docs/v1.0/modules/08-core-laravel-integration-persistence-jobs-read-apis.md`
- `repos/core/docs/v1.0/tutorials/advanced-full-text-retrieval-and-export-audit.md`
- `repos/core/src/Dissemination/Application/UseCase/RetrieveFullText.php`
- `repos/core/src/Dissemination/Application/UseCase/RetrieveFullTextHandler.php`
- `repos/core/src/Dissemination/Domain/Port/FullTextFetchReaderPort.php`
- `repos/core/src/Dissemination/Domain/Port/PdfFetchRepositoryPort.php`
- `repos/core/src/Laravel/Persistence/EloquentPdfFetchRepository.php`
- `repos/core/src/Laravel/Persistence/EloquentFullTextFetchReader.php`
- `repos/core/src/Laravel/Job/RetrieveFullTextJob.php`
- `repos/nexus-cli/docs/commands/nexus-fetch-full-text/README.md`
- `repos/nexus-cli/tests/Feature/Commands/NexusFetchPdfsTest.php`
- `repos/nexus-web/database/migrations/2026_04_27_000013_create_pdf_fetches_table.php`
- `repos/nexus-web/config/nexus.php`

## Product Boundary

Nexus Scholar Web owns:

- the product workflow around a completed screening batch,
- the "ready for full text" candidate set,
- role-scoped retrieval controls,
- background batch orchestration,
- browser-facing status summaries and artifact audit views,
- private artifact download or inspection routes,
- demo data and browser scenarios.

`nexus-scholar/core` owns:

- legal open-access source resolution,
- source configuration,
- PDF/XML/text validation,
- retry and cooldown policy,
- artifact storage through ports,
- `pdf_fetches` audit records,
- `FullTextFetchReaderPort`,
- locked-corpus membership checks.

Do not shell out to `nexus-cli`. Treat it as a workflow reference only. Use core
handlers, ports, jobs, and read APIs directly inside Laravel.

## Input Contract

Workflow 7 starts only when:

- the project has a completed title-and-abstract screening batch,
- the batch belongs to the latest locked representative snapshot,
- final per-work outcomes are available in `batch.counts.outcomes`,
- at least one work has final `include` or `needs_review`,
- the actor can manage full-text retrieval for the project,
- the workspace is not suspended.

The authoritative candidate set is:

- final `include` records,
- final `needs_review` records,
- never final `exclude` records by default.

The candidate set must be derived from assignment-linked screening decisions
and resolved conflicts, not from mutable draft corpus membership.

## Protocol Policy

The existing protocol field `full_text_policy` should shape the UI:

| Policy | Workflow behavior |
| --- | --- |
| `optional` | Retrieval is useful but missing artifacts do not block later review setup. |
| `required_for_inclusion` | Missing full text should be shown as a blocker before full-text screening or final inclusion. |
| `manual_uploads_only` | Automatic legal OA retrieval is disabled; the UI should prepare manual upload states instead. |

The first implementation slice should support automatic legal OA retrieval for
`optional` and `required_for_inclusion`. Manual upload can be documented in the
UI as unavailable in the first slice unless implementation scope explicitly
includes uploads.

## Core Retrieval Behavior

Core `RetrieveFullTextHandler` returns one `FullTextResult` per work:

- `success`
- `failure`
- `skipped`

Core source order and availability come from `config/nexus.php`:

- direct PDF URLs,
- Unpaywall, only when email is configured,
- PubMed Central,
- Europe PMC,
- arXiv,
- OpenAlex PDF metadata,
- Semantic Scholar PDF metadata.

Shadow-library retrieval stays disabled. Do not add bypass sources.

Core validates:

- primary identifier availability,
- locked project membership when a project id is supplied,
- recent failure cooldown,
- max download attempts,
- max artifact size,
- PDF media and file signature,
- XML/text artifacts where supported,
- deterministic storage paths.

## Recommended Web Data Model

Core already persists the source-level audit trail in `pdf_fetches`. The web app
still needs a product batch model so users can see one retrieval run, progress,
and candidate-level status.

Add web-owned tables:

- `project_full_text_batches`
- `project_full_text_items`

### project_full_text_batches

Purpose: one retrieval run for one completed screening handoff.

Recommended columns:

- `id`
- `project_id`
- `screening_batch_id`
- `snapshot_id`
- `status` one of `queued`, `running`, `completed`, `completed_with_failures`,
  `failed`, `cancelled`
- `candidate_count`
- `success_count`
- `failed_count`
- `skipped_count`
- `missing_count`
- `destination_folder`
- `source_policy` JSON
- `requested_by`
- `started_at`
- `completed_at`
- timestamps

Recommended indexes:

- `project_id`, `status`
- `screening_batch_id`, `status`
- `project_id`, `created_at`

### project_full_text_items

Purpose: one candidate work inside one retrieval batch.

Recommended columns:

- `id`
- `project_id`
- `batch_id`
- `work_id`
- `screening_decision` one of `include`, `needs_review`
- `status` one of `queued`, `running`, `success`, `failed`, `skipped`,
  `manual_needed`
- `source_alias`
- `artifact_type` nullable, one of `pdf`, `xml`, `text`
- `artifact_path`
- `http_status`
- `error_message`
- `metadata` JSON
- `started_at`
- `completed_at`
- timestamps

Recommended uniqueness:

- `batch_id`, `work_id`

Do not duplicate `pdf_fetches` as the source audit table. Store only the current
product-facing item state and link by work, destination, and timestamps when
deep source audit is needed.

## Application Services

Recommended classes:

- `App\Actions\Projects\BuildProjectFullTextCandidates`
- `App\Actions\Projects\StartProjectFullTextBatch`
- `App\Jobs\RunProjectFullTextBatchJob`
- `App\Actions\Projects\RefreshProjectFullTextBatchCounts`
- `App\Queries\Projects\ProjectFullTextReadModel`
- `App\Queries\Projects\ProjectFullTextArtifactReadModel`

### BuildProjectFullTextCandidates

Responsibilities:

- load the latest completed screening batch,
- reconstruct final per-work outcomes,
- include only `include` and `needs_review`,
- attach work metadata and identifiers,
- reject stale or missing locked snapshot state,
- preserve deterministic ordering.

### StartProjectFullTextBatch

Responsibilities:

- assert project and workspace permissions,
- assert completed screening handoff exists,
- assert protocol policy allows automatic retrieval,
- create a batch and item rows,
- dispatch background retrieval,
- record `project.full_text.batch_started`.

### RunProjectFullTextBatchJob

Responsibilities:

- run in the queue, never during the HTTP request,
- call `RetrieveFullTextHandler` once per item,
- pass the project id so core enforces locked membership,
- write item status from `FullTextResult`,
- refresh batch counts after each item or chunk,
- record `project.full_text.batch_completed` or `project.full_text.batch_failed`.

### ProjectFullTextReadModel

Return:

- screening handoff summary,
- protocol full-text policy,
- candidate counts,
- latest retrieval batch,
- item status table,
- source configuration visibility,
- recent source audit records through `FullTextFetchReaderPort`,
- actor permissions.

## Route And Policy

Recommended routes:

- `GET /projects/{project}/full-text`
- `POST /projects/{project}/full-text/batches`
- `GET /projects/{project}/full-text/artifacts/{item}`

Recommended route names:

- `projects.full-text.index`
- `projects.full-text.batches.store`
- `projects.full-text.artifacts.show`

Recommended policy methods:

- `viewFullText(User $user, Project $project): bool`
- `manageFullText(User $user, Project $project): bool`
- `downloadFullTextArtifact(User $user, Project $project): bool`

Role behavior:

| Actor | View | Start retrieval | Download artifact |
| --- | --- | --- | --- |
| Project owner | Yes | Yes | Yes |
| Workspace owner/admin | Yes | Yes | Yes |
| Adjudicator | Yes | No | Yes |
| Reviewer | Yes | No | Yes |
| Viewer | Yes | No | Yes, unless policy later restricts |
| Disabled user | No | No | No |
| Suspended workspace member | No | No | No |

## UI Shape

Use a focused operations screen, not a dashboard page.

Primary sections:

- readiness header from Workflow 6,
- source and policy card,
- retrieval batch progress strip,
- candidate table,
- artifact detail sheet,
- recent audit trail.

Candidate table controls:

- search by title or identifier,
- status filter,
- decision filter (`include`, `maybe`),
- source filter,
- column visibility,
- sorting,
- pagination,
- horizontal overflow for dense metadata.

Artifact detail sheet:

- title, year, venue, identifiers,
- final screening decision and rationale,
- artifact status,
- source alias and URL when safe to display,
- artifact type,
- HTTP status,
- duration,
- error message,
- latest source attempts from `FullTextFetchReaderPort`,
- authorized download/open action for successful artifacts.

## UX States

### Not Ready

No completed screening batch exists. Show the next action as screening.

### Ready But Not Started

Show the candidate count from final include/maybe outcomes and a start action
for owners/admins.

### Queued Or Running

Show progress, disable duplicate start, and keep the candidate table readable.

### Completed

Show success, failed, skipped, and manual-needed counts. Treat failures as audit
facts, not generic errors.

### Completed With Failures

Show the same completed UI with retry guidance. Do not hide successful
artifacts because other records failed.

### Manual Upload Policy

If the protocol says `manual_uploads_only`, disable automatic retrieval and
show a manual upload placeholder state. Do not silently run automated sources.

## Demo Data

Extend `DemoAccessSeeder` during implementation.

Required seeded states:

- completed screening handoff with no full-text batch,
- running or queued full-text batch,
- completed full-text batch with success, failure, and skipped items,
- success item with artifact metadata,
- failed item with source error,
- skipped item with no primary ID or no legal source,
- owner/admin mutation access,
- reviewer/viewer read-only access.

Use fake artifact paths and deterministic `pdf_fetches` records in the seeder.
Do not make live network calls from seeding or browser scenarios.

## Browser Scenarios

Add these to `docs/demo-scenarios.md` during implementation:

1. Owner opens a completed screening project and sees full-text readiness.
2. Owner opens the full-text page before retrieval and sees candidate counts.
3. Owner starts retrieval and sees a queued/running batch state.
4. Owner sees completed retrieval with success, failed, and skipped counts.
5. Owner opens an artifact detail sheet and sees source audit records.
6. Reviewer opens the same page read-only.
7. Viewer can inspect status without mutation controls.
8. Manual-upload-only protocol disables automatic retrieval.

Screenshot targets:

- `output/playwright/workflow-7-full-text-ready.png`
- `output/playwright/workflow-7-full-text-running.png`
- `output/playwright/workflow-7-full-text-completed.png`
- `output/playwright/workflow-7-full-text-artifact-detail.png`
- `output/playwright/workflow-7-reviewer-readonly.png`

## Automated Tests

Pest feature tests:

- completed screening batch exposes full-text candidates,
- excluded records are not candidates,
- owner/admin can start retrieval,
- reviewer/viewer cannot start retrieval,
- manual-upload-only policy blocks automatic retrieval,
- background job records success, failure, and skipped item states,
- core `RetrieveFullTextHandler` is called with project id,
- batch counts refresh correctly,
- source audit reads through `FullTextFetchReaderPort`,
- artifact route enforces project access.

Vitest component tests:

- full-text progress strip,
- full-text candidate table empty and populated states,
- status badges for success/failure/skipped/manual-needed,
- artifact detail sheet,
- readonly action states.

Browser verification:

- owner start and completed state,
- reviewer read-only state,
- artifact detail sheet,
- manual-upload-only blocker.

## First Implementation Slice

Build the host workflow without manual uploads:

1. Add web-owned batch and item tables.
2. Add candidate builder from completed screening outcomes.
3. Add full-text policy methods.
4. Add full-text overview route and page.
5. Add start action that dispatches the background job.
6. Use core `RetrieveFullTextHandler` in the job.
7. Read source audit through `FullTextFetchReaderPort`.
8. Seed no-batch and completed-batch states with fake audit records.
9. Add Pest and component tests.
10. Verify with Python Playwright screenshots.

## Explicit Non-Goals

Do not implement these in the first slice:

- full-text screening decisions,
- manual PDF upload,
- OCR,
- paywall bypass,
- shadow-library sources,
- final PRISMA report,
- citation graph generation,
- export packages,
- mobile-first screening optimization.

## Risks

- Retrieval can be slow and flaky if run during HTTP requests. Always use the
  queue.
- `pdf_fetches` is source audit, not a product batch table. Do not force it to
  carry UI progress alone.
- Successful artifact paths need authorized access. Do not expose raw storage
  paths as public URLs without a project policy check.
- Protocol policy must be visible. Users should know whether missing full text
  is acceptable or blocking.
- Legal source posture must stay explicit. Do not add non-OA retrieval sources.
- Candidate reconstruction must use final screening outcomes, not raw reviewer
  votes.

## Readiness Checklist

Before implementation starts:

- repo is clean,
- branch is fresh,
- Workflow 6 completed handoff is merged,
- full-text candidate rules are accepted,
- manual upload is confirmed as in or out of first slice,
- artifact download policy is accepted,
- no live network calls are required for seeded demos,
- browser scenarios and screenshot names are agreed.

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
