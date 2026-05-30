# Workflow 11: Synthesis, PRISMA Counts, And Exports

Prepared on 2026-05-30.

Status: prepared for implementation. This workflow turns the completed review
state into synthesis-ready tables, PRISMA-style counts, and auditable export
packages.

Wireframes: `docs/wireframes/workflow-11-synthesis-exports-wireframes.html`.

## Goal

Let research teams inspect final review counts, build evidence tables, and
export traceable review artifacts.

Workflow 11 answers these questions:

- What are the final PRISMA-style counts from search through inclusion?
- Which studies, extraction fields, and appraisal ratings are ready for export?
- Which export package was generated, by whom, from which locked state?
- Can another developer, reviewer, or auditor reproduce the exported dataset?

Exports should be boring and trustworthy. They should not invent scientific
conclusions; they should package the review state that the team already
approved.

## Sources Checked

- `docs/workflow-5-dedup-corpus-lock.md`
- `docs/workflow-6-title-abstract-screening.md`
- `docs/workflow-7-full-text-retrieval.md`
- `docs/workflow-8-full-text-screening.md`
- `docs/workflow-9-data-extraction.md`
- `docs/workflow-10-quality-appraisal-risk-of-bias.md`
- `docs/core-cli-workflow-scan.md`
- `repos/core/docs/v1.0/modules/02-core-shared-kernel.md`
- `repos/core/docs/v1.0/modules/07-core-full-text-and-dissemination.md`
- `repos/core/docs/v1.0/modules/08-core-laravel-integration-persistence-jobs-read-apis.md`
- `repos/core/docs/v1.0/tutorials/build-a-laravel-review-cli-with-core.md`
- `repos/core/docs/v1.0/tutorials/advanced-full-text-retrieval-and-export-audit.md`
- `repos/nexus-cli/docs/commands/nexus-export-bibliography/README.md`
- `repos/nexus-cli/app/Console/Commands/NexusExportBibliography.php`
- `repos/nexus-cli/app/Console/Commands/NexusExports.php`

## Product Boundary

Nexus Scholar Web owns:

- synthesis overview and evidence table UI;
- PRISMA-style count reconstruction across web-owned workflow tables;
- export package builder;
- export authorization and download routes;
- export run status and audit logs;
- JSON audit package structure;
- full-text ZIP packaging policy;
- demo data, browser scenarios, and UI tests.

`nexus-scholar/core` owns:

- bibliography serialization;
- export history recording and reading;
- export storage where used by core handlers;
- citable/final metadata from corpus lock state;
- full-text artifact audit records;
- graph and bibliography export handlers where supported.

Use core export handlers and read ports when the artifact type matches core's
contract. Keep host-specific package assembly in the web app.

## Input Contract

Workflow 11 starts when:

- Workflow 8 has completed full-text outcomes;
- Workflow 9 has a completed extraction dataset for study-level and field
  exports;
- Workflow 10 has a completed appraisal matrix when appraisal exports are
  requested;
- the project still has a locked representative corpus snapshot;
- the actor can manage exports for the project;
- the workspace is active.

Export readiness should be explicit per artifact:

| Artifact | Minimum readiness |
| --- | --- |
| Bibliography CSV/BibTeX/RIS | locked corpus and final included/excluded state |
| PRISMA-style counts | search, dedup, screening, full-text screening state |
| Extraction CSV | completed extraction dataset |
| Appraisal CSV | completed appraisal matrix |
| JSON audit package | completed upstream states for selected package sections |
| Full-text ZIP | retrieved artifacts with project-authorized access |

## Export Formats

First-slice export package should support:

- CSV;
- BibTeX;
- RIS;
- PRISMA-style counts as CSV and JSON;
- JSON audit package;
- full-text ZIP for successful artifacts when permitted.

Later formats can add:

- XLSX;
- PRISMA diagram image;
- report document;
- citation graph exports;
- API export tokens.

## Data Model

Core already ships `export_histories`. The web app needs a product-level export
run table for package assembly, status, and download authorization.

Recommended tables:

- `project_export_packages`
- `project_export_package_items`

### project_export_packages

Purpose: one user-requested export package.

Recommended columns:

- `id`
- `project_id`
- `snapshot_id`
- `full_text_screening_batch_id` nullable
- `extraction_batch_id` nullable
- `appraisal_batch_id` nullable
- `status` one of `queued`, `running`, `completed`, `failed`, `expired`
- `package_type` one of `bibliography`, `prisma`, `extraction`,
  `appraisal`, `audit`, `full_text_zip`, `combined`
- `requested_formats` JSON
- `storage_path`
- `manifest` JSON
- `error_message`
- `requested_by`
- `started_at`
- `completed_at`
- `expires_at`
- timestamps

### project_export_package_items

Purpose: one file inside a package.

Recommended columns:

- `id`
- `project_id`
- `package_id`
- `item_type`
- `format`
- `filename`
- `storage_path`
- `size_bytes`
- `checksum`
- `metadata` JSON
- timestamps

Recommended indexes:

- `project_id`, `status`
- `package_id`, `item_type`

## PRISMA-Style Counts

Counts must be reconstructed from workflow state, not manually typed.

Recommended count groups:

- records identified by provider;
- records after deduplication;
- duplicate records removed;
- records screened at title and abstract;
- records excluded at title and abstract;
- full-text records sought;
- full-text records retrieved;
- full-text records unavailable or manual follow-up;
- full-text records assessed for eligibility;
- full-text records excluded with structured reasons;
- studies included in synthesis;
- studies with completed extraction;
- studies with completed appraisal.

Keep a clear distinction between:

- records;
- deduplicated representative works;
- reports or artifacts;
- included studies.

## JSON Audit Package

The JSON audit package should be deterministic and documented.

Recommended top-level sections:

- `project`
- `protocol`
- `search_runs`
- `deduplication`
- `locked_snapshot`
- `title_abstract_screening`
- `full_text_retrieval`
- `full_text_screening`
- `extraction`
- `quality_appraisal`
- `prisma_counts`
- `export_manifest`

Every section should include:

- source table or read-model provenance;
- generated timestamp;
- actor id;
- source batch ids;
- counts;
- warnings for incomplete sections.

Do not include private full-text files inside the JSON package. Link them by
package item when the user also requests a full-text ZIP.

## Application Services

Recommended classes:

- `App\Queries\Projects\ProjectSynthesisReadModel`
- `App\Queries\Projects\ProjectPrismaCountsReadModel`
- `App\Queries\Projects\ProjectEvidenceTableReadModel`
- `App\Actions\Projects\StartProjectExportPackage`
- `App\Jobs\BuildProjectExportPackageJob`
- `App\Actions\Projects\BuildProjectAuditPackage`
- `App\Actions\Projects\BuildProjectFullTextZip`
- `App\Queries\Projects\ProjectExportPackageReadModel`

### ProjectPrismaCountsReadModel

Responsibilities:

- reconstruct counts from persisted workflow tables;
- expose count warnings when a stage is incomplete;
- separate record, work, report, and study counts;
- group full-text exclusions by structured reason;
- return deterministic output for CSV and JSON.

### StartProjectExportPackage

Responsibilities:

- assert export permissions;
- validate requested package type and formats;
- assert readiness for each requested artifact;
- create package and package item placeholders;
- dispatch package build to the queue;
- record `project.export.package_started`.

### BuildProjectExportPackageJob

Responsibilities:

- run in the background;
- call core bibliography export handlers for CSV, BibTeX, and RIS where
  applicable;
- generate host-owned extraction, appraisal, PRISMA, audit, and ZIP artifacts;
- calculate checksums;
- store package manifest;
- mark package completed or failed.

### ProjectExportPackageReadModel

Responsibilities:

- show package history;
- show item list, format, size, checksum, and readiness;
- read core export history through `ExportHistoryReaderPort`;
- expose authorized download URLs only to permitted actors.

## Route And Policy

Recommended routes:

- `GET /projects/{project}/synthesis`
- `GET /projects/{project}/exports`
- `POST /projects/{project}/exports/packages`
- `GET /projects/{project}/exports/packages/{package}`
- `GET /projects/{project}/exports/packages/{package}/items/{item}`

Recommended route names:

- `projects.synthesis.index`
- `projects.exports.index`
- `projects.exports.packages.store`
- `projects.exports.packages.show`
- `projects.exports.items.show`

Recommended policy methods:

- `viewSynthesis`
- `viewExports`
- `manageExports`
- `downloadExportPackage`

## UI Shape

Use a synthesis command center with package history.

Primary surfaces:

- synthesis overview;
- PRISMA count review;
- evidence table preview;
- export builder sheet;
- package history table;
- package detail sheet;
- full-text ZIP warning and confirmation.

Synthesis overview:

- status strip for screening, extraction, appraisal, and export readiness;
- PRISMA count blocks grouped by workflow;
- evidence table preview with column visibility and horizontal scrolling;
- quality/appraisal summary matrix;
- export action opening a right-side builder.

Export builder:

- artifact checklist;
- format selector;
- readiness warnings;
- included files preview;
- audit reason;
- background export confirmation.

Package history:

- sorting, status filters, package type filters, and pagination;
- item detail in a right-side sheet;
- checksum and manifest visibility.

## UX States

### Not Ready

No completed full-text include set exists. Link back to Workflow 8.

### Partial Synthesis

Show PRISMA and screening counts, but mark extraction and appraisal exports as
unavailable until those workflows complete.

### Ready For Export

Show eligible package types and formats. Export building always runs in the
background.

### Export Running

Show package progress and keep package history readable. Do not block the page.

### Export Failed

Show failure reason, package manifest warnings, and retry controls for owners or
admins.

### Completed Package

Show package items, checksums, citable/final metadata, and authorized downloads.

## Demo Data

Extend `DemoAccessSeeder` during implementation.

Required seeded states:

- synthesis overview with completed extraction and appraisal;
- partial synthesis with missing appraisal;
- completed PRISMA-style count set;
- queued export package;
- completed export package with CSV, BibTeX, RIS, JSON audit package, and
  full-text ZIP item metadata;
- failed export package;
- viewer read-only export history.

Use fake storage paths and deterministic checksums. Do not write large binary
ZIP files from the seeder.

## Browser Scenarios

Add these to `docs/demo-scenarios.md` during implementation:

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

Screenshot targets:

- `output/playwright/workflow-11-synthesis-overview.png`
- `output/playwright/workflow-11-prisma-counts.png`
- `output/playwright/workflow-11-evidence-table.png`
- `output/playwright/workflow-11-export-builder.png`
- `output/playwright/workflow-11-package-history.png`
- `output/playwright/workflow-11-package-detail.png`
- `output/playwright/workflow-11-viewer-readonly.png`

## Automated Tests

Pest feature tests:

- PRISMA counts reconstruct from workflow tables;
- counts distinguish raw records, representative works, artifacts, and studies;
- export readiness blocks unavailable package sections;
- owner/admin can start export package;
- reviewer/viewer cannot start export package;
- package building dispatches to the queue;
- bibliography exports call core export handlers where applicable;
- package items record checksums and manifest metadata;
- download route enforces project access;
- failed package state preserves error message.

Vitest component tests:

- synthesis readiness strip;
- PRISMA counts table;
- evidence table preview;
- export builder sheet;
- package history table;
- package detail sheet.

Browser verification:

- synthesis overview;
- export builder;
- package detail;
- viewer read-only state.

## First Implementation Slice

1. Add export package tables and models.
2. Add PRISMA counts read model.
3. Add synthesis overview page.
4. Add evidence table preview from extraction and appraisal read models.
5. Add export package builder and queue job.
6. Integrate core bibliography export handlers and export history reader.
7. Generate host-owned PRISMA, extraction, appraisal, audit, and full-text ZIP
   package items.
8. Add authorized download routes.
9. Extend demo seed data.
10. Add Pest and component coverage.
11. Update `docs/demo-scenarios.md`.
12. Verify with browser screenshots.

## Explicit Non-Goals

Do not implement these in the first slice:

- statistical meta-analysis;
- automatic narrative synthesis;
- public sharing links;
- report writing;
- PRISMA diagram image generation;
- payment or billing enforcement;
- external API export tokens.

## Risks

- PRISMA-style counts can be scientifically misleading if record, work, report,
  and study units are mixed. Keep units explicit.
- Export packages must be background jobs. ZIP assembly and full audit JSON can
  be slow.
- Full-text ZIP downloads need strict project authorization.
- Export history should use core read ports for core-generated artifacts and
  host tables for package-level assembly.
- Exported data should include enough provenance to reproduce the package.
- Large evidence tables need pagination, column visibility, and lazy detail.

## Readiness Checklist

Before implementation starts:

- Workflow 9 and Workflow 10 first slices are accepted;
- export formats are accepted;
- JSON audit package sections are accepted;
- full-text ZIP policy is accepted;
- browser scenarios and screenshot names are accepted.

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
