# Workflow 2 UI Preparation

Status: implemented and merged.

This note records the web UI preparation for project creation and protocol
definition. It should stay focused on reusable frontend pieces; backend schema
and policy decisions live in the parent product workflow docs.

## Scope

Workflow 2 starts the research project without starting search, import,
deduplication, screening, or export. The first UI should cover:

- project shell creation,
- project status and role display,
- protocol draft completion,
- protocol readiness before search,
- compact overview cards,
- protocol activity and version-history entry points.

The sidebar should not gain project navigation until project routes exist.
Before that, project cards and overview links from the dashboard are enough.

## Local Codebase Findings

Current reusable surfaces:

- `PageShell` and `PageHeader` already fit workflow pages.
- `MetricCard` works for compact project counts and workflow totals.
- `SettingsSection` works for account/security panels but should not be reused
  for project protocol content.
- `WorkspaceStatusBadge` established the domain-badge pattern.
- The dashboard and workspace pages already use card-based dense panels.

New reusable workflow 2 components:

- `ProjectStatusBadge` for project lifecycle display.
- `ProtocolStatusBadge` for protocol draft, complete, locked, and amended
  states.
- `ProjectRoleBadge` for owner, reviewer, adjudicator, and viewer roles.
- `WorkflowStepCard` for the guided project/protocol setup path.
- `ProtocolReadinessList` for required protocol fields before search can
  start.

## shadcn Registry Findings

Checked with `npx shadcn@latest` on 2026-05-29.

- Current app baseline remains Laravel, Tailwind v4, Radix, TypeScript, lucide,
  and `new-york` style.
- Installed primitives already cover buttons, cards, badges, labels, inputs,
  selects, dialogs, dropdowns, sidebar, sheet, tooltip, skeleton, and toast.
- `@shadcn/dashboard-01` is a useful reference for dense dashboard rhythm, but
  it pulls chart, table, drawer, TanStack Table, DnD, Tabler icons, and sample
  data patterns. Do not install it wholesale for workflow 2.
- `@shadcn/form` and form examples are oriented around React Hook Form. For
  Nexus Scholar v1, keep Inertia `useForm` as the mutation boundary unless a
  specific protocol editor interaction proves otherwise.
- `textarea` is the right primitive for protocol question, rationale, inclusion,
  and exclusion fields and has been installed locally.
- Add `tabs` only when the project overview needs first-class Overview,
  Protocol, Members, and Activity panels.
- Add `table` or a DataTable wrapper only when record lists, imports, screening
  queues, or audit tables exist. Avoid introducing TanStack Table before there
  is a real list workflow.

## Implementation Shape

Use the first implementation to compose pages from existing shell primitives and
the new workflow components:

1. `GET /projects/create` uses `PageShell`, `PageHeader`, `WorkflowStepCard`,
   shadcn `Card`, `Input`, `Textarea`, `Select`, and Inertia `useForm`.
2. `GET /projects/{project}` uses `ProjectStatusBadge`, `ProtocolStatusBadge`,
   `ProjectRoleBadge`, `MetricCard`, and compact cards for status and blockers.
3. `GET /projects/{project}/protocol` uses `ProtocolReadinessList`, `Textarea`,
   `Select`, and explicit save/complete actions.
4. `GET /projects/{project}/activity` starts as an audit-event list. Add a
   dedicated `AuditEventRow` after real activity data is rendered.

## Test Expectations

Every workflow 2 slice should add:

- Pest coverage for routes, policies, verified-email gates, project membership,
  and Inertia props.
- Vitest coverage for reusable project/protocol components and any extracted
  page behavior.
- Browser scenarios for owner, admin, reviewer, viewer, unverified user, and
  operator-visible boundaries.

Do not use broad snapshots for project pages. Test the product signals: status,
role, readiness, required audit reason, and allowed actions.
