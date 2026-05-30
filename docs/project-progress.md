# Project Progress

Last updated: 2026-05-30.

This file tracks the hosted Nexus Scholar Web MVP from the live repository
state. It should be updated when a workflow is prepared, implemented, or
merged.

## Current Status

Overall progress toward a handoff-ready hosted systematic-review MVP: **64%**.

The implemented product path now covers the review from sign-in through legal
open-access full-text retrieval and artifact audit. The remaining work starts
where scientific full-text eligibility decisions begin.

The percentage is intentionally conservative:

- workflow count: 8 of 13 major milestones are implemented, or about 62%;
- weighted product risk: later scientific workflows are heavier than setup
  screens, so the working estimate is 64%, not 70%+;
- CI and local validation are green on `master` after Workflow 7.

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
| Workflow 8: full-text screening | Prepared | `docs/workflow-8-full-text-screening.md` |
| Workflow 9: data extraction | Not started | Needs spec |
| Workflow 10: quality appraisal / risk of bias | Not started | Needs spec |
| Workflow 11: synthesis, PRISMA counts, exports | Not started | Needs spec |
| Workflow 12: production hardening and launch controls | Partial | Auth, access, CI, demo data, and bundle checks exist; billing, quotas, and observability are not complete |

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
- deterministic demo data and browser scenarios for the implemented workflows.

## Current Branch And Release State

- Default branch: `master`.
- Workflow 7 merge commit: `9d9b2e3`.
- Package dependency: `nexus-scholar/core:^1.0` from Packagist.
- Composer path repositories are not used by the default web-app setup.
- Local managed repositories are clean as of this progress pass.

## Remaining Product Work

The next high-value slice is Workflow 8: full-text screening. It should be
implemented before extraction or exports because extraction depends on a stable
set of included full-text studies.

After Workflow 8, the strongest sequence is:

1. Data extraction schema and reviewer extraction workflow.
2. Quality appraisal or risk-of-bias workflow.
3. Synthesis, PRISMA-style counts, and export packages.
4. Production controls: quotas, background-job observability, error reporting,
   hosted storage policy, and billing or usage limits.

## Current Risks

- Workflow 8 must not treat a retrieved PDF as automatically included. It needs
  a separate human full-text eligibility decision loop.
- Missing full text should remain an auditable state, not a hidden exclusion.
- Existing screening tables can support `full_text` stage, but implementation
  must keep title-and-abstract and full-text queues clearly separated.
- Later exports depend on trustworthy final decisions from Workflow 8 and
  should not be started before that state is stable.
