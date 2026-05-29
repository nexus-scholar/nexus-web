# Frontend Quality

This is the working standard for React code in Nexus Scholar. Keep it small,
enforced, and practical.

## Architecture Boundary

Nexus Scholar is a Laravel/Inertia application. The server owns durable product
state: users, workspaces, memberships, audit events, imports, review records,
screening decisions, and exports.

React owns presentation state:

- open or closed UI controls,
- form field state,
- transient loading or copied states,
- local filters before they are submitted,
- modal step state.

Do not copy durable server state into a client store unless there is a measured
reason. Prefer page props, shared props, and partial reloads before adding a
separate client cache.

## State Management

Current rule:

- Use Inertia page props for server-provided screen state.
- Use Inertia `Form` or `useForm` for mutations and validation.
- Use local `useState` for small UI state.
- Use `useReducer` when one component has several related state transitions.
- Use React context only for stable UI shell concerns.
- Do not add Redux or Zustand for v1 access/workspace flows.

Add a store only when the problem is cross-screen client state that is not
server-owned and cannot be handled cleanly by layout props or URL state. If that
happens, start with a small Zustand store scoped to one concern. Do not put
permissions, workspace truth, or audit history in it.

Add TanStack Query only if Nexus Scholar gains independent background data
fetching that does not map well to Inertia visits or partial reloads.

## Hooks

Keep hooks boring:

- Effects synchronize with external systems only.
- Derive render data during render instead of writing effects that copy props
  into state.
- Memoize only when the value is expensive, passed through stable component
  boundaries, or returned from a shared hook.
- Keep hook return values stable when they are consumed by navigation, shell, or
  repeated list components.
- Let React Compiler handle routine component memoization.

The app already enables React Compiler through `babel-plugin-react-compiler` in
`vite.config.ts`. Manual `useMemo`, `useCallback`, and `memo` should be used as
precision tools, not as decoration.

## Component Design

Use shadcn components as local source-owned primitives. Build Nexus-specific
components when they encode product meaning, for example:

- `WorkspaceStatusBadge`
- `DecisionBadge`
- `AuditEventRow`
- `ReviewerAssignment`
- `ExportPackageCard`

Avoid large pages that mix data mapping, mutation handling, permissions, and
visual details in one file. When a page grows, extract in this order:

1. Product-specific display components.
2. Form sections.
3. Small hooks for derived page behavior.
4. Reducers for complex local interaction state.

## Testing

Use the narrowest test that catches the failure:

- Pest feature tests for routes, permissions, policies, redirects, and Inertia
  props.
- React component tests for domain UI components, complex forms, and custom
  hooks.
- Browser checks for full workflows, layout regressions, actor-based access, and
  visual trust.

The next frontend test slice should add Vitest and Testing Library, then cover:

- `WorkspaceStatusBadge`,
- `useCurrentUrl`,
- workspace switcher rendering,
- auth layout variant selection.

Do not build a broad snapshot suite. It will slow the team down and catch the
wrong failures.

Current UI test setup:

- Test runner: Vitest.
- DOM environment: jsdom.
- Assertions: `@testing-library/jest-dom/vitest`.
- User-facing queries: Testing Library.
- Stable selectors: `data-test`, configured in `resources/js/test/setup.ts`.

Run:

```powershell
npm run test:ui
```

When adding UI tests, place them next to the implementation file. Keep Pest as
the authority for Laravel behavior and use browser scenarios for responsive
layout and workflow confidence.

## Bundle Performance

Keep the startup bundle small enough that login and public pages do not inherit
unrelated authenticated-workspace code.

Current rule:

- Keep page components lazy-loaded through the Inertia Vite plugin.
- Keep root `resources/js/app.tsx` thin. It should not statically import
  authenticated layouts, settings layouts, sidebar/navigation UI, toasts, or
  tooltip providers.
- Lazy-load default Inertia layouts from `resources/js/app.tsx` so public and
  auth pages do not inherit authenticated shell code.
- Keep auth layout variants lazy inside `resources/js/layouts/auth-layout.tsx`
  so simple password screens do not load the split-panel image.
- Keep authenticated-only providers such as `TooltipProvider` and `Toaster`
  inside `resources/js/layouts/app-layout.tsx`.
- Split stable vendor families through `build.rolldownOptions.output.codeSplitting`
  in `vite.config.ts`.
- Do not raise `chunkSizeWarningLimit` to hide a warning unless a measured,
  intentional large chunk remains after analysis.
- Prefer route/page-level splits and removing unnecessary root imports before
  adding more manual chunk groups.

If the large chunk warning returns, inspect the sourcemap first:

```powershell
npx vite build --sourcemap
```

Then check whether the large module is framework/vendor code, a generated route
map, a shared app shell import, or a page that should be lazy-loaded.

Current target shape:

- `resources/js/app.tsx` should import only the Rolldown runtime, React vendor,
  and Inertia vendor chunks.
- The generated auth image should belong to
  `resources/js/layouts/auth/auth-split-layout.tsx`, not the root app entry.
- `vendor-ui` should be loaded by pages or authenticated layouts that actually
  use Radix/shadcn UI.

Enforced budgets live in `scripts/check-bundle-budget.mjs` and run with:

```powershell
npm run build:check
```

Current limits:

- root app entry: 20 KiB raw, 8 KiB gzip,
- any JavaScript chunk: 250 KiB raw, 90 KiB gzip,
- generated auth image: 120 KiB raw.

## Current Gates

Run these before committing frontend work:

```powershell
npm run format:check
npm run lint:check
npm run types:check
npm run test:ui
npm run build:check
git diff --check
```

Run `composer test` too when UI changes depend on routes, policies, middleware,
controllers, factories, or Inertia props.

## References

- React state guidance: `https://react.dev/learn/managing-state`
- React effect guidance: `https://react.dev/learn/you-might-not-need-an-effect`
- React Compiler: `https://react.dev/learn/react-compiler`
- Inertia forms: `https://inertiajs.com/forms`
- Inertia shared data: `https://inertiajs.com/shared-data`
- Inertia partial reloads: `https://inertiajs.com/partial-reloads`
