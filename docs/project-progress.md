# Project Progress

Last updated: 2026-05-30.

This file tracks the hosted Nexus Scholar Web MVP from the live repository
state. It should be updated when a workflow is prepared, implemented, or
merged.

## Current Status

Overall implementation progress toward a handoff-ready hosted
systematic-review MVP: **72%**.

The implemented product path now covers the review from sign-in through human
full-text eligibility decisions with artifact provenance, reviewer queues,
conflict handling, and follow-up artifact states. The remaining work starts
where included full-text studies become structured extraction records.

Planning and documentation coverage for the 13 tracked milestones is now
complete through Workflow 12. Workflows 9-12 are prepared but not implemented.

The percentage is intentionally conservative:

- workflow count: 9 of 13 major milestones are implemented, or about 69%;
- weighted product risk: later scientific workflows are heavier than setup
  screens, so the working estimate is 72%, not 80%+;
- local validation is green for the Workflow 8 branch before merge;
- this pass prepares Workflows 9-12 without changing runtime implementation.

## Milestone Table

| Milestone | Status | Evidence |
| --- | --- | --- |
| Phase 0: scaffold and local dev | Done | `docs/phase-0-local-dev.md` |
| Workflow 1: auth, workspaces, access, operator controls | Done | `docs/demo-scenarios.md` |
| Workflow 2: project creation and protocol | Done | `docs/workflow-2-project-protocol-ui.md` |
| Workflow 3: search plan and search run | Done | `docs/workflow-3-search-plan-run.md` |
| Workflow 4: draft corpus review | Done | `docs/workflow-4-draft-corpus-review.md` |
| Workflow 5: deduplication and corpus lock | Done | `docs/workflow-5-dedup-corpus-lock.md` |
| Workflow 6: title and abstract screening | Done | `docs/workflow-6-title-abstract-screening.md` |
| Workflow 7: full-text retrieval and artifact audit | Done | `docs/workflow-7-full-text-retrieval.md` |
| Workflow 8: full-text screening | Done | `docs/workflow-8-full-text-screening.md` |
| Workflow 9: data extraction | Prepared | `docs/workflow-9-data-extraction.md` |
| Workflow 10: quality appraisal / risk of bias | Prepared | `docs/workflow-10-quality-appraisal-risk-of-bias.md` |
| Workflow 11: synthesis, PRISMA counts, exports | Prepared | `docs/workflow-11-synthesis-prisma-exports.md` |
| Workflow 12: production hardening and launch controls | Prepared, partially implemented by earlier infrastructure | `docs/workflow-12-production-hardening-launch-controls.md`; auth, access, CI, demo data, and bundle checks exist; quotas, job health, storage usage, and operator observability remain implementation work |

## Implemented Surface

The merged app supports:

- user registration, login, disabled-user blocking, and operator controls;
- workspaces, roles, invitations, and workspace switching;
- project creation and protocol editing with research-review fields;
- search-plan drafting and background search-run dispatch;
- draft corpus review with provenance, metadata quality, filters, table
  controls, and record detail sheets;
- deduplication review and representative corpus lock;
- title and abstract screening with reviewer queues, conflict resolution,
  adjudication reason capture, and final handoff counts;
- full-text retrieval with background batches, legal source policy, artifact
  audit details, source attempts, and read-only reviewer access;
- full-text screening with artifact-linked reviewer queues, artifact-inspected
  confirmation, structured exclusion reasons, conflict resolution, and final
  full-text outcome counts;
- deterministic demo data and browser scenarios for the implemented workflows;
- prepared specs, wireframes, and scenario placeholders for Workflows 9-12.

## Current Branch And Release State

- Default branch: `master`.
- Workflow 8 merge commit: `dfab33f`.
- Package dependency: `nexus-scholar/core:^1.0` from Packagist.
- Composer path repositories are not used by the default web-app setup.
- Local managed repositories are clean as of this progress pass.

## Remaining Product Work

The next high-value slice is Workflow 9: data extraction. It should consume only
the completed full-text screening `include` outcome set rather than
title-and-abstract screening, raw full-text retrieval rows, or unresolved
full-text maybes.

The strongest sequence is:

1. Implement Workflow 9 data extraction schema and reviewer extraction
   workflow from the prepared spec.
2. Implement Workflow 10 quality appraisal or risk-of-bias workflow.
3. Implement Workflow 11 synthesis, PRISMA-style counts, and export packages.
4. Implement Workflow 12 production controls: quotas, background-job
   observability, error reporting, hosted storage policy, and billing or usage
   limits.

## Current Risks

- Workflow 9 must consume only final full-text outcomes and must not reopen
  failed or skipped retrieval rows as included studies.
- Missing full text remains an auditable follow-up state in Workflow 8; future
  export logic must preserve that distinction.
- Later exports depend on trustworthy final decisions, extraction fields, and
  risk-of-bias state, so PRISMA/export work should stay behind extraction and
  appraisal.
