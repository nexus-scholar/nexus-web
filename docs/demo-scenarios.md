# Demo Scenarios

This document is the living browser verification script for Nexus Scholar.
Update it whenever a workflow adds a new actor, role, screen, or state.

## Baseline Setup

Use the default local ports:

```powershell
php artisan serve
npm run dev
```

Use the demo seed data:

```powershell
php artisan migrate --force
php artisan db:seed --class=DemoAccessSeeder --force
```

For a clean scenario run, clear existing browser sessions without deleting demo
data:

```powershell
php artisan tinker --execute='DB::table("sessions")->delete();'
```

The seeder is idempotent. Running it repeatedly should preserve the same demo
actors and reset the intended demo states.

## Demo Accounts

All seeded accounts use the password `password`.

| Actor           | Email                        | Expected role or state                                 |
| --------------- | ---------------------------- | ------------------------------------------------------ |
| Operator        | `operator@nexusscholar.test` | Platform operator with user and workspace controls     |
| Workspace owner | `owner@nexusscholar.test`    | Owner of personal workspace and Evidence Synthesis Lab |
| Workspace admin | `admin@nexusscholar.test`    | Admin in Evidence Synthesis Lab                        |
| Reviewer        | `reviewer@nexusscholar.test` | Member in Evidence Synthesis Lab                       |
| Viewer          | `viewer@nexusscholar.test`   | Member in Evidence Synthesis Lab                       |
| Disabled user   | `disabled@nexusscholar.test` | Disabled account, cannot use the app                   |

Seeded workspace states:

- `Evidence Synthesis Lab`: shared workspace owned by the workspace owner.
- `Suspended Review Group`: suspended workspace for operator review.
- `pending-reviewer@nexusscholar.test`: pending invitation in Evidence Synthesis Lab.

## Workflow 1: Auth, Workspaces, And Access

### Public Entry

1. Clear sessions.
2. Open `http://127.0.0.1:8000/`.
3. Verify the page is Nexus Scholar branded and contains no Laravel starter
   content.
4. Capture `output/playwright/workflow-1-welcome.png`.

Expected signals:

- Title is `Welcome - Nexus Scholar`.
- Primary copy says `Nexus Scholar`.
- Calls to action include `Create account` and `Log in`.

### Owner Workspace Dashboard

1. Log in as `owner@nexusscholar.test`.
2. Open `/dashboard`.
3. Verify the active workspace is `Evidence Synthesis Lab` or switch to it from
   the workspace switcher.
4. Capture `output/playwright/workflow-1-dashboard-fixed.png`.

Expected signals:

- Sidebar brand says `Nexus Scholar`.
- Workspace switcher shows the active workspace and role.
- Dashboard shows personal and shared workspace access.
- `Workspace settings` and `Members` links appear for the active workspace.

### Workspace Member Management

1. As the owner, open the Evidence Synthesis Lab members screen from the
   sidebar.
2. Verify owner, admin, reviewer, and viewer demo accounts are listed.
3. Verify pending invitations are listed.
4. Invite a new address such as `browser-reviewer@nexusscholar.test`.
5. Capture `output/playwright/workflow-1-members-fixed.png` and
   `output/playwright/workflow-1-invite.png` when invite behavior changes.

Expected signals:

- Owners can invite members.
- Owners can see role controls for removable or editable members.
- The workspace switcher remains visible on the members page.
- Pending invitations stay scoped to the current workspace.

### Operator User Controls

1. Clear sessions.
2. Log in as `operator@nexusscholar.test`.
3. Open `/operator/users`.
4. Capture `output/playwright/workflow-1-operator-users.png`.

Expected signals:

- Sidebar includes `Operator users` and `Operator workspaces`.
- `Disabled Researcher` is marked disabled.
- Each operator action requires an audit reason.
- Operators can see disable or enable controls as applicable.

### Operator Workspace Controls

1. As the operator, open `/operator/workspaces`.
2. Capture `output/playwright/workflow-1-operator-workspaces.png`.

Expected signals:

- `Suspended Review Group` is visible as a suspended workspace.
- Workspace suspend or restore actions require an audit reason.
- Operator navigation remains visible.

### Disabled Account

1. Clear sessions.
2. Try to log in as `disabled@nexusscholar.test`.

Expected signals:

- The disabled account cannot proceed into the authenticated app.
- The app does not expose workspace pages after login is blocked.

## UI Hardening: Brand Tokens

Run this after global token or shared component changes.

### Public Brand Smoke

1. Open `http://127.0.0.1:8000/`.
2. Verify the welcome screen uses the Nexus Scholar brand mark, neutral
   surfaces, and semantic icon colors.
3. Capture `output/playwright/nexus-tokens-welcome.png`.

Expected signals:

- The page title is `Welcome - Nexus Scholar`.
- The primary action is visually dominant without using a one-hue theme.
- The preview panel uses `WorkspaceStatusBadge` for the `Active` state.

### Authenticated Shell Token Smoke

1. Log in as `operator@nexusscholar.test`.
2. Open `/dashboard`.
3. Verify the sidebar, workspace switcher, dashboard cards, and workspace type
   badge use the Nexus token system.
4. Capture `output/playwright/nexus-tokens-dashboard.png`.
5. Toggle or force dark mode and capture
   `output/playwright/nexus-tokens-dashboard-dark.png`.

Expected signals:

- `WorkspaceStatusBadge` renders the current workspace type.
- Light mode reads as clean research software, not starter-kit defaults.
- Dark mode keeps card boundaries, text, inputs, and primary actions readable.

## UI Hardening: Mark And Auth Shell

Run this after logo, app shell, or auth layout changes.

### Public Mark Smoke

1. Open `http://127.0.0.1:8000/`.
2. Verify the welcome header uses the Nexus evidence-card mark, not a generic
   starter icon.
3. Capture `output/playwright/nexus-mark-welcome.png`.

Expected signals:

- The mark remains legible at small header size.
- The brand lockup reads `Nexus Scholar`.
- The page keeps the tokenized brand and status colors.

### Auth Shell Smoke

1. Open `/login`.
2. Verify the login page uses the two-column desktop shell: form on the left,
   the generated Nexus research workspace image on the right, and a
   single-column fallback below desktop width.
3. Capture `output/playwright/nexus-auth-login-split.png`.
4. Resize to a mobile viewport and capture
   `output/playwright/nexus-auth-login-mobile.png`.
5. Open `/register`.
6. Verify registration uses the same split shell on desktop.
7. Capture `output/playwright/nexus-auth-register-split.png`.

Expected signals:

- Auth pages no longer look like unbranded starter-kit screens.
- The login page follows the shadcn `login-02` structure without importing a
  Next.js block into the Laravel/Inertia app.
- The logo, heading, description, forms, links, and visual panel fit cleanly at
  desktop width.
- The auth copy addresses the research workspace, not a generic account portal.
- The visual panel image is decorative for assistive technology and does not
  introduce readable pseudo-text or a heavy PNG payload.

## Browser Verification Rules

- Prefer the Browser MCP for local visual checks.
- Use screenshots for the first pass of every new workflow and for every bug
  fixed through visual inspection.
- Keep screenshots in `output/playwright/`; this path is ignored by Git.
- When a visual check reveals a UI bug, update this document with the scenario
  that would have caught it.
- Finish each workflow with the relevant automated gates:

```powershell
composer test
npm run lint:check
npm run format:check
npm run types:check
npm run build
composer validate --strict
composer audit --format=plain --abandoned=ignore
git diff --check
```

## Growth Rule

Each future workflow must add:

- seeded demo data,
- at least one actor-based scenario,
- expected browser-visible signals,
- screenshot artifact names,
- automated tests that protect the same behavior.
