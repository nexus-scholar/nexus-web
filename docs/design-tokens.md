# Nexus Scholar Design Tokens

This document records the MVP token system for Nexus Scholar. The first
implementation landed in `resources/css/app.css` on 2026-05-29.

## Goals

- Keep the professional shadcn/Rhea baseline.
- Give Nexus Scholar a distinct research identity.
- Preserve readable long-session workflows in light and dark mode.
- Add semantic tokens for research-specific status, not one-off color classes.
- Avoid broad shadcn style migration during feature work.

## Token Strategy

Keep the existing shadcn token contract:

- `--background`
- `--foreground`
- `--card`
- `--popover`
- `--primary`
- `--secondary`
- `--muted`
- `--accent`
- `--destructive`
- `--border`
- `--input`
- `--ring`
- `--chart-*`
- `--sidebar-*`

Add Nexus-specific tokens for product meaning:

- `--brand`
- `--brand-foreground`
- `--brand-muted`
- `--brand-muted-foreground`
- `--status-include`
- `--status-include-bg`
- `--status-exclude`
- `--status-exclude-bg`
- `--status-conflict`
- `--status-conflict-bg`
- `--status-audit`
- `--status-audit-bg`
- `--status-import`
- `--status-import-bg`
- `--status-pending`
- `--status-pending-bg`

Expose these through Tailwind v4 `@theme` as `--color-brand`,
`--color-status-include`, and related color utilities when implemented.

## Light Mode Proposal

```css
:root {
    --background: oklch(0.99 0.003 247);
    --foreground: oklch(0.16 0.014 250);
    --card: oklch(1 0 0);
    --card-foreground: oklch(0.16 0.014 250);
    --popover: oklch(1 0 0);
    --popover-foreground: oklch(0.16 0.014 250);

    --primary: oklch(0.19 0.018 250);
    --primary-foreground: oklch(0.985 0.003 247);
    --secondary: oklch(0.96 0.006 247);
    --secondary-foreground: oklch(0.22 0.014 250);
    --muted: oklch(0.96 0.006 247);
    --muted-foreground: oklch(0.47 0.018 250);
    --accent: oklch(0.94 0.025 205);
    --accent-foreground: oklch(0.24 0.05 210);

    --destructive: oklch(0.56 0.18 25);
    --destructive-foreground: oklch(0.985 0.003 247);
    --border: oklch(0.9 0.008 247);
    --input: oklch(0.9 0.008 247);
    --ring: oklch(0.56 0.1 205);

    --brand: oklch(0.5 0.115 205);
    --brand-foreground: oklch(0.985 0.003 247);
    --brand-muted: oklch(0.94 0.03 205);
    --brand-muted-foreground: oklch(0.34 0.07 205);
}
```

## Dark Mode Proposal

```css
.dark {
    --background: oklch(0.15 0.012 250);
    --foreground: oklch(0.985 0.003 247);
    --card: oklch(0.19 0.012 250);
    --card-foreground: oklch(0.985 0.003 247);
    --popover: oklch(0.19 0.012 250);
    --popover-foreground: oklch(0.985 0.003 247);

    --primary: oklch(0.97 0.003 247);
    --primary-foreground: oklch(0.19 0.018 250);
    --secondary: oklch(0.25 0.012 250);
    --secondary-foreground: oklch(0.96 0.003 247);
    --muted: oklch(0.25 0.012 250);
    --muted-foreground: oklch(0.72 0.015 250);
    --accent: oklch(0.27 0.055 205);
    --accent-foreground: oklch(0.93 0.035 205);

    --destructive: oklch(0.7 0.16 25);
    --destructive-foreground: oklch(0.15 0.012 250);
    --border: oklch(1 0 0 / 10%);
    --input: oklch(1 0 0 / 15%);
    --ring: oklch(0.63 0.1 205);

    --brand: oklch(0.72 0.11 205);
    --brand-foreground: oklch(0.14 0.012 250);
    --brand-muted: oklch(0.25 0.05 205);
    --brand-muted-foreground: oklch(0.84 0.05 205);
}
```

## Semantic Status Tokens

Use these in domain components such as `DecisionBadge`, `AuditEventRow`,
`ImportStatus`, and `ConflictBadge`.

```css
:root {
    --status-include: oklch(0.54 0.13 151);
    --status-include-bg: oklch(0.96 0.035 151);
    --status-exclude: oklch(0.56 0.18 25);
    --status-exclude-bg: oklch(0.96 0.04 25);
    --status-conflict: oklch(0.5 0.14 290);
    --status-conflict-bg: oklch(0.95 0.035 290);
    --status-audit: oklch(0.62 0.14 75);
    --status-audit-bg: oklch(0.96 0.04 75);
    --status-import: oklch(0.54 0.13 230);
    --status-import-bg: oklch(0.95 0.035 230);
    --status-pending: oklch(0.46 0.015 247);
    --status-pending-bg: oklch(0.95 0.006 247);
}

.dark {
    --status-include: oklch(0.82 0.12 151);
    --status-include-bg: oklch(0.25 0.06 151);
    --status-exclude: oklch(0.82 0.12 25);
    --status-exclude-bg: oklch(0.25 0.07 25);
    --status-conflict: oklch(0.83 0.1 290);
    --status-conflict-bg: oklch(0.26 0.07 290);
    --status-audit: oklch(0.86 0.12 75);
    --status-audit-bg: oklch(0.26 0.07 75);
    --status-import: oklch(0.83 0.11 230);
    --status-import-bg: oklch(0.25 0.07 230);
    --status-pending: oklch(0.74 0.02 247);
    --status-pending-bg: oklch(0.26 0.012 247);
}
```

## Chart Tokens

Charts should not use random palette values. Use ordered research semantics:

- `--chart-1`: brand/import blue
- `--chart-2`: include green
- `--chart-3`: conflict violet
- `--chart-4`: audit amber
- `--chart-5`: exclude red

This keeps PRISMA counts, screening progress, imports, and audit dashboards
consistent across the app.

## Sidebar Tokens

The sidebar should remain neutral and quiet.

Recommended mapping:

- `--sidebar`: slightly darker or lighter than `--background`,
- `--sidebar-primary`: `--brand` only for the logo mark or selected workspace
  identity,
- `--sidebar-accent`: subtle neutral hover state,
- `--sidebar-border`: same family as `--border`.

Do not make the full sidebar teal or blue.

## Radius And Surfaces

Keep `--radius: 0.5rem` for now. It moves the app closer to the compact Rhea
reference while staying inside the familiar shadcn default-radius range.

Use:

- small radius for badges and compact controls,
- default radius for cards and panels,
- no extra-large decorative cards in authenticated workflows.

## Typography

Use Inter for MVP. It matches the decoded Rhea preset and improves the dense
dashboard feel without introducing a custom display typeface. Instrument Sans
remains in the fallback stack.

Rules:

- page titles: `text-xl` or `text-2xl` inside authenticated screens,
- card titles: compact, usually `text-base`,
- metadata: `text-xs` or `text-sm` with muted foreground,
- public welcome headline: can remain larger.

## Implementation Status

Implemented:

- Nexus token exports in the Tailwind v4 `@theme` block.
- Light and dark shadcn token values.
- Semantic status token values.
- Inter as the primary sans font through the Laravel Vite font pipeline.
- Compact card radius and spacing closer to Rhea.
- Welcome screen token usage for brand, import, include, and audit colors.
- `WorkspaceStatusBadge` as the first token-backed domain component.
- Dashboard usage of `WorkspaceStatusBadge`.
- Shared `PageShell`, `PageHeader`, and `MetricCard` primitives for access
  pages.
- Shared `SettingsSection` panels for account and security surfaces.
- Sidebar navigation with static workspace switching and inline account actions,
  without Laravel starter repository/documentation links.

Next:

1. Add `DecisionBadge` when the review workflow lands.
2. Add `AuditEventRow` when audit history becomes visible in the product UI.
3. Add `DataTable` or table wrappers once imports, records, and screening lists
   land.
4. Replace remaining generic shell visuals only after the brand mark is final.

## Acceptance Check

The token implementation is acceptable when:

- light mode reads as clean research software, not a plain starter kit,
- dark mode remains first-class,
- the product does not become a single-color theme,
- status colors are consistent with review semantics,
- existing shadcn components still look native,
- welcome, dashboard, sidebar, and forms all remain readable.
