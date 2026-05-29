# UI System And shadcn

This document records the current Nexus Scholar UI baseline and the shadcn
verification workflow. Update it before large UI changes.

## Current Local Snapshot

Checked on 2026-05-29.

- shadcn CLI: `4.8.2`
- Rhea reference preset: `b27GcrRo`
- Framework detected by shadcn: `Laravel`
- Style: `new-york`
- Base: `radix`
- TypeScript: enabled
- Tailwind: v4
- Icon library: `lucide`
- Import alias: `@`
- shadcn config: `components.json`
- UI path: `resources/js/components/ui`
- CSS path: `resources/css/app.css`

Installed shadcn components reported by `npx shadcn@latest info --json`:

```text
alert, avatar, badge, breadcrumb, button, card, checkbox, collapsible,
dialog, dropdown-menu, input-otp, input, label, navigation-menu, select,
separator, sheet, sidebar, skeleton, sonner, spinner, toggle-group, toggle,
tooltip
```

## Codex MCP Configuration

The shadcn MCP server is configured in `C:\Users\mouadh\.codex\config.toml`:

```toml
[mcp_servers.shadcn]
command = "npx"
args = ["shadcn@latest", "mcp"]
```

Codex Desktop must be restarted before this MCP server appears as a callable
tool in new sessions.

The official init command was tested:

```powershell
npx shadcn@latest mcp init --client codex
```

On this machine it failed because it attempted `pnpm add -D "shadcn@latest"`
and `pnpm` is not installed. The manual Codex config above is the working
setup path.

## CLI Checks That Worked

Use these commands before UI work when the MCP tool is not available in the
current session:

```powershell
npx shadcn@latest --version
npx shadcn@latest info --json
npx shadcn@latest docs button --json
npx shadcn@latest docs card --json
npx shadcn@latest search "@shadcn" -q form -l 5
```

The `search` command accepts `@shadcn` only when quoted in PowerShell.

## Current Documentation Findings

Official docs checked on 2026-05-29:

- MCP docs: `https://ui.shadcn.com/docs/mcp`
- Registry MCP docs: `https://ui.shadcn.com/docs/registry/mcp`
- `components.json` docs: `https://ui.shadcn.com/docs/components-json`
- Changelog: `https://ui.shadcn.com/docs/changelog`
- Blocks docs: `https://ui.shadcn.com/docs/blocks`
- Login blocks: `https://ui.shadcn.com/blocks/login`
- Rhea changelog: `https://ui.shadcn.com/docs/changelog/2026-05-rhea`

Relevant takeaways:

- shadcn is source-owned UI, not a dependency-only component library. Keep local
  components intentional and review diffs before accepting registry updates.
- MCP is intended for IDE/agent access to component docs, examples, registry
  search, and install guidance.
- Nexus web already uses Tailwind v4, React, TypeScript, lucide icons, and the
  `new-york` shadcn style, so new components should follow that baseline.
- Rhea preset `b27GcrRo` decodes to `style=rhea`, `baseColor=neutral`,
  `theme=neutral`, `font=inter`, `iconLibrary=lucide`, `radius=default`,
  `menuAccent=subtle`, and `menuColor=default`.
- New registry examples may use dependency patterns that differ from this app's
  current installed Radix packages. Do not run broad migrations during feature
  work unless the migration itself is the task.
- Login block `login-02` is the reference for the two-column login page: form
  column first, visual panel second, with the visual panel hidden below desktop
  width. In this Laravel/Inertia app, adapt the pattern inside the existing auth
  layout instead of installing a Next.js block wholesale.
- Login blocks `login-03` and `login-05` remain useful references for compact
  centered auth screens such as registration and password reset.

Note: `npx shadcn@latest info --json` can fail when local DNS cannot resolve
`ui.shadcn.com`. If that happens, use the official docs above and retry the CLI
before making registry changes.

## UI Work Protocol

Before changing product UI:

1. Inspect the existing local component and layout patterns.
2. Refresh shadcn context with MCP or the CLI docs commands above.
3. Prefer existing components from `resources/js/components/ui`.
4. Use `lucide-react` icons when an icon helps recognition.
5. Keep workflow screens dense, calm, and operational. Nexus Scholar is a
   research workspace, not a marketing site.
6. Avoid nested cards, oversized hero patterns inside authenticated workflows,
   and one-hue visual themes.
7. Verify with browser screenshots on desktop. Add mobile checks only for
   screens intended to be mobile-friendly now.

After changing UI:

```powershell
npm run format:check
npm run lint:check
npm run types:check
npm run build
git diff --check
```

Use Browser MCP screenshots for visible behavior and layout quality.

## Brand Direction

Use `docs/brand-identity.md` as the source of truth for product identity,
visual direction, color semantics, copy voice, and first implementation targets.
Use `docs/design-tokens.md` as the implementation-ready token plan for global
CSS variables and Nexus-specific semantic colors.
Use `docs/frontend-quality.md` for React state, hook, and frontend test
standards.

Short version:

- Use the Rhea preset as a taste reference, not a copied identity.
- Keep the app calm, dense, traceable, and research-specific.
- Use neutral surfaces with a restrained research accent and meaningful status
  colors.
- Make domain components for repeated research concepts instead of styling each
  workflow screen from scratch.
