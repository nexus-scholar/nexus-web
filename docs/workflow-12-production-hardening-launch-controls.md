# Workflow 12: Production Hardening And Launch Controls

Prepared on 2026-05-30.

Status: prepared for implementation. This workflow is the hosted-SaaS hardening
layer around the scientific workflows, not a replacement for Workflows 9-11.

Wireframes:
`docs/wireframes/workflow-12-production-controls-wireframes.html`.

## Goal

Make Nexus Scholar Web reliable enough for a hosted MVP with free signup,
private projects by default, background jobs, usage limits, auditability, and
operational visibility.

Workflow 12 answers these questions:

- Can the app protect limited server resources without blocking legitimate
  early users?
- Can an operator understand job health, storage growth, and failed workflows?
- Can users see their workspace usage and soft limits?
- Can admins review audit events and explain sensitive actions?
- Can the project be handed off without hidden local assumptions?

## Sources Checked

- `docs/demo-scenarios.md`
- `docs/developer-handoff.md`
- `docs/frontend-quality.md`
- `docs/design-tokens.md`
- `docs/ui-system-shadcn.md`
- `docs/project-progress.md`
- `docs/workflow-7-full-text-retrieval.md`
- `docs/workflow-8-full-text-screening.md`
- `repos/core/docs/v1.0/modules/08-core-laravel-integration-persistence-jobs-read-apis.md`
- `repos/core/docs/v1.0/release-state-audit.md`
- `repos/nexus-cli/docs/agent-notes.md`
- `repos/nexus-cli/docs/command-runs/1.0-release-smoke/README.md`

## Product Boundary

Nexus Scholar Web owns:

- hosted signup policy;
- workspace/project usage counters;
- soft limits and operator overrides;
- background job visibility;
- storage usage reporting;
- audit-log surfaces;
- operator health pages;
- backup and retention policy documentation;
- production environment checks;
- release and handoff runbooks.

`nexus-scholar/core` owns reusable job lifecycle and export/full-text read ports
where available. The web app should read those ports when presenting core-owned
job and export state, but SaaS limits and operator surfaces stay host-owned.

## Launch Policy

Current product decisions:

- hosted SaaS-style app from day one;
- free signup at the start;
- no organization verification at first;
- projects private by default;
- users can create workspaces until server resources force tighter limits;
- soft limits should exist before hard billing;
- full-text retrieval and exports should always run in the background;
- owner/admin/operator actions that affect access or audit state require a
  reason.

## Hardening Areas

### Usage And Limits

Recommended workspace usage counters:

- active projects;
- total works;
- search runs per month;
- full-text retrieval candidates per month;
- stored full-text artifact size;
- export package size;
- active reviewer seats;
- queued jobs.

Recommended first soft limits:

| Resource | Suggested starter limit |
| --- | --- |
| projects per workspace | 5 active projects |
| works per project | 2,000 representative works |
| monthly search results | 10,000 raw records |
| monthly full-text attempts | 500 candidates |
| storage per workspace | 2 GB |
| export package retention | 30 days |
| concurrent background jobs | 2 per workspace |

Soft limit behavior:

- warn at 80%;
- block new heavy operations at 100% unless an owner/admin requests an override
  or the operator raises the limit;
- never delete user data automatically to enforce a limit;
- always explain which workflow is blocked and why.

### Background Jobs

Jobs that must stay background-only:

- search dispatch;
- full-text retrieval;
- export package assembly;
- future extraction/appraisal imports;
- future AI assistance.

Operator visibility should show:

- queued jobs;
- running jobs;
- failed jobs;
- retry count;
- workspace/project context;
- duration;
- last exception summary;
- next retry time;
- related workflow batch.

### Audit Events

Audit surfaces should cover:

- user disable/enable;
- workspace suspend/restore;
- role changes and invitations;
- protocol completion and amendment;
- corpus lock;
- screening conflict resolution;
- full-text screening conflict resolution;
- extraction template lock and conflict resolution;
- appraisal tool lock and conflict resolution;
- export package creation;
- operator limit overrides.

Sensitive mutation forms should require an audit reason.

### Storage

Storage policy should cover:

- full-text artifacts;
- export packages;
- generated JSON audit packages;
- local development fake artifacts;
- retention period;
- deletion and recovery expectations;
- backup expectations.

The first implementation should not expose raw storage paths. Use authorized
download routes.

### Observability

Recommended pages:

- operator health overview;
- operator job monitor;
- operator storage and usage dashboard;
- workspace usage page;
- project audit timeline;
- project export/job history.

Recommended signals:

- queue latency;
- failed job count;
- storage growth;
- export package failures;
- full-text source failure rate;
- top workspaces by storage and jobs;
- recent operator actions.

## Data Model

Recommended tables:

- `workspace_usage_snapshots`
- `workspace_limits`
- `workspace_limit_overrides`
- `project_audit_events` already exists if current app uses project events;
  extend rather than duplicate where practical.

### workspace_usage_snapshots

Purpose: periodic or on-demand usage summary for a workspace.

Recommended columns:

- `id`
- `workspace_id`
- `project_count`
- `work_count`
- `monthly_search_result_count`
- `monthly_full_text_attempt_count`
- `storage_bytes`
- `export_storage_bytes`
- `active_seat_count`
- `queued_job_count`
- `measured_at`
- timestamps

### workspace_limits

Purpose: effective plan or custom limits.

Recommended columns:

- `id`
- `workspace_id`
- `plan_key`
- `project_limit`
- `work_limit`
- `monthly_search_result_limit`
- `monthly_full_text_attempt_limit`
- `storage_limit_bytes`
- `export_retention_days`
- `concurrent_job_limit`
- timestamps

### workspace_limit_overrides

Purpose: operator-granted temporary or permanent overrides.

Recommended columns:

- `id`
- `workspace_id`
- `limit_key`
- `old_value`
- `new_value`
- `reason`
- `created_by`
- `expires_at`
- timestamps

## Application Services

Recommended classes:

- `App\Actions\Workspaces\RefreshWorkspaceUsage`
- `App\Actions\Workspaces\CheckWorkspaceLimit`
- `App\Actions\Workspaces\RecordWorkspaceLimitOverride`
- `App\Queries\Workspaces\WorkspaceUsageReadModel`
- `App\Queries\Operator\OperatorHealthReadModel`
- `App\Queries\Operator\OperatorJobMonitorReadModel`
- `App\Queries\Operator\OperatorStorageReadModel`
- `App\Queries\Projects\ProjectAuditTimelineReadModel`

### RefreshWorkspaceUsage

Responsibilities:

- calculate workspace usage from projects, works, artifacts, exports, and jobs;
- store a snapshot;
- avoid slow full-table scans on hot request paths;
- expose warnings for near-limit states.

### CheckWorkspaceLimit

Responsibilities:

- check the current effective limit before heavy operations;
- return warnings at 80% and block messages at 100%;
- include the blocked workflow and remediation path;
- keep enforcement deterministic in tests.

### OperatorHealthReadModel

Responsibilities:

- expose app health without leaking secrets;
- summarize queue, storage, failed jobs, usage, and recent operator actions;
- link to job and workspace detail pages.

## Route And Policy

Recommended routes:

- `GET /settings/workspace/usage`
- `GET /projects/{project}/activity`
- `GET /operator/health`
- `GET /operator/jobs`
- `GET /operator/storage`
- `POST /operator/workspaces/{workspace}/limits`
- `POST /operator/jobs/{job}/retry`

Recommended policy methods:

- `viewWorkspaceUsage`
- `viewProjectAuditTimeline`
- `viewOperatorHealth`
- `manageWorkspaceLimits`
- `retryOperatorJob`

## UI Shape

Use operational pages, not marketing dashboards.

Workspace usage page:

- current plan/limit summary;
- usage rows with progress bars and exact numbers;
- warnings and blocked-workflow messages;
- recent heavy operations;
- link to support/operator contact later.

Operator health page:

- compact health strip;
- queue and failed job panels;
- storage and usage panels;
- recent operator actions;
- drill-down links.

Job monitor:

- table with filters by status, workflow, workspace, project, and age;
- right-side job detail sheet;
- retry action with audit reason for allowed jobs;
- pagination and sorting.

Storage dashboard:

- workspace usage table;
- artifact type breakdown;
- export retention state;
- oversized package list;
- no raw path exposure.

## UX States

### Healthy

Show normal queue latency, storage usage, and no failed critical jobs.

### Near Limit

Show soft warnings to workspace owners/admins and explain what action will be
blocked if usage reaches the limit.

### Limit Reached

Block heavy new operations with a precise message. Existing data remains
readable.

### Job Failures

Show failed jobs with retry guidance and a concise exception summary. Avoid
showing secrets or full stack traces in the UI.

### Operator Override

Operator can change a workspace limit only with an audit reason.

## Demo Data

Extend `DemoAccessSeeder` during implementation.

Required seeded states:

- workspace under 80% usage;
- workspace over 80% usage;
- workspace at or over a soft limit;
- failed full-text retrieval job;
- failed export package job;
- queued long-running job;
- operator limit override with reason;
- project audit timeline with workflow events.

Use deterministic fake job rows and storage numbers. Do not create large
artifact files in the seeder.

## Browser Scenarios

Add these to `docs/demo-scenarios.md` during implementation:

1. Owner opens workspace usage and sees current limits and usage.
2. Owner sees a near-limit warning.
3. Owner attempts a blocked heavy operation and sees a clear limit message.
4. Operator opens health overview and sees queue, storage, and failed job
   summaries.
5. Operator opens job monitor, filters failed jobs, and opens a job detail
   sheet.
6. Operator retries an allowed failed job with an audit reason.
7. Operator adjusts a workspace limit with an audit reason.
8. Owner opens project activity and sees workflow audit events.
9. Viewer cannot access operator health or workspace limits.

Screenshot targets:

- `output/playwright/workflow-12-workspace-usage.png`
- `output/playwright/workflow-12-near-limit-warning.png`
- `output/playwright/workflow-12-limit-blocker.png`
- `output/playwright/workflow-12-operator-health.png`
- `output/playwright/workflow-12-job-monitor.png`
- `output/playwright/workflow-12-job-detail.png`
- `output/playwright/workflow-12-storage-dashboard.png`
- `output/playwright/workflow-12-project-activity.png`

## Automated Tests

Pest feature tests:

- usage snapshots calculate deterministic counts;
- near-limit warnings appear at configured threshold;
- soft limit blocks heavy operations and leaves reads available;
- operator can override limits with audit reason;
- operator cannot override limits without reason;
- workspace owner can view workspace usage;
- viewer cannot manage limits;
- operator health hides secrets;
- job retry requires operator access and audit reason;
- project activity timeline contains workflow events.

Vitest component tests:

- workspace usage meter;
- limit warning banner;
- operator health summary;
- job monitor table;
- job detail sheet;
- storage dashboard table;
- audit timeline.

Browser verification:

- owner workspace usage;
- operator health and job monitor;
- limit override;
- project audit timeline.

## First Implementation Slice

1. Add workspace usage and limit tables.
2. Add usage refresh and limit-check services.
3. Add workspace usage settings page.
4. Enforce limits around search, full-text retrieval, and export package
   dispatch.
5. Add operator health overview.
6. Add operator job monitor and retry action.
7. Add operator storage dashboard.
8. Add project audit timeline read model if current activity view is not enough.
9. Extend demo seed data.
10. Add Pest and component coverage.
11. Update `docs/demo-scenarios.md`.
12. Verify with browser screenshots.

## Explicit Non-Goals

Do not implement these in the first slice:

- full billing and payment;
- enterprise SSO;
- public project sharing;
- automated data deletion;
- multi-region deployment;
- full incident management;
- external observability vendor integration unless chosen later.

## Risks

- Soft limits must not corrupt workflows already in progress.
- Usage counters can become slow if calculated synchronously during hot
  requests. Use snapshots and focused checks.
- Operator pages must avoid exposing secrets in exception messages.
- Storage size reporting differs by driver. Keep the first implementation
  conservative and test local storage deterministically.
- Job retry can duplicate side effects if jobs are not idempotent. Retry only
  jobs with explicit safe retry behavior.
- Production hardening should not delay scientific workflow implementation
  unless it protects data, costs, or handoff reliability.

## Readiness Checklist

Before implementation starts:

- initial soft limits are accepted;
- operator retry policy is accepted;
- workspace usage page scope is accepted;
- storage retention policy is accepted;
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
