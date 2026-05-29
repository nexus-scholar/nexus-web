# Nexus Scholar Brand Identity

This is the working brand brief for Nexus Scholar. Keep it current as the app
evolves; it should guide visual design, component decisions, copy, screenshots,
and demo scenarios.

## Positioning

Nexus Scholar is a research operations workspace for systematic reviews. It
helps labs and research teams coordinate evidence synthesis, keep decisions
traceable, and preserve a defensible audit trail from project setup through
export.

The product should feel like professional research infrastructure, not a
generic SaaS dashboard.

## Audience

Primary users:

- research teams and labs,
- universities and academic groups,
- public SaaS users running serious evidence-review workflows.

Typical users are managing high-friction, high-accountability work. They need
clarity, confidence, repeatability, and readable screens more than visual
novelty.

## Brand Attributes

- Precise: every label, status, and action should reduce ambiguity.
- Calm: the interface should lower cognitive load during long review sessions.
- Traceable: changes, decisions, imports, and exports should feel accountable.
- Collaborative: team state should be visible without turning the app into a
  social feed.
- Scholarly: the visual language can reference research, evidence, citations,
  protocols, and auditability without becoming decorative.

## Visual Direction

Use the Rhea preset from shadcn as a taste reference, not as a final identity.
Its neutral surfaces, dense dashboard composition, rounded controls, and dark
primary actions fit the product well.

Nexus Scholar should adapt that base with a more research-specific identity:

- neutral application shell,
- restrained brand accent,
- clear semantic status colors,
- dense but breathable layouts,
- strong table and list readability,
- document-like hierarchy for protocols, citations, audits, and exports.

## Color Direction

Working direction:

- Base: warm-neutral or neutral gray surfaces.
- Primary action: near-black in light mode and near-white in dark mode, matching
  shadcn/Rhea's serious product feel.
- Brand accent: research teal or evidence blue, used sparingly for identity,
  navigation emphasis, and selected states.
- Semantic colors:
    - include/accepted: green,
    - exclude/rejected: red or rose,
    - maybe/conflict: violet,
    - audit/system event: amber,
    - import/source state: blue,
    - neutral/pending: gray.

Avoid letting one hue dominate the whole product. Status colors should be
meaningful and consistent, not decorative.

## Typography

The app currently uses Instrument Sans through the Laravel starter kit. It is
acceptable for the MVP. If we switch later, Inter is the most conservative
candidate because it matches the Rhea reference and works well in dense
operational UIs.

Rules:

- keep body text highly readable,
- avoid oversized headings inside workflow screens,
- use compact headings in cards, sidebars, tables, and forms,
- do not use negative letter spacing,
- reserve large display text for the public welcome screen only.

## Logo And Mark

The implemented MVP mark lives in `resources/js/components/app-logo-icon.tsx`.
It combines an evidence card/document shape, an abstract `N`, and small
connection rails to suggest linked review evidence.

The logo should remain simple enough to work as:

- app sidebar mark,
- favicon,
- GitHub/social avatar,
- small mobile header icon,
- document/export watermark later.

Current direction:

- evidence card/document shape,
- abstract `N`,
- connected evidence rails,
- single-color SVG using `currentColor` so it works in the sidebar, welcome
  header, auth shell, and future favicon/app-icon contexts.

Avoid academic cliches such as graduation caps, oversized microscopes, generic
brain icons, or decorative network clouds.

## Auth Visual

The generated auth split-panel image lives in
`resources/js/assets/auth-evidence-workspace.webp`. It shows a calm systematic
review workspace with layered documents, evidence links, decision markers, and
an audit/check motif. It is decorative in the UI and should stay compressed as a
WebP or similarly efficient format.

Prompt used:

```text
Professional systematic review research workspace: layered research documents,
citation cards, connected evidence nodes, reviewer decision markers, audit
trail lines, and a subtle shield/check motif. Premium editorial 3D
illustration, warm neutral background, restrained teal/evidence-blue accents,
small amber/green semantic highlights, no readable text, no logos, no
watermark.
```

## UI Principles

- Use shadcn components as source-owned building blocks.
- Prefer domain-specific wrappers only when they encode repeated product
  meaning, such as `DecisionBadge`, `AuditEventRow`, `WorkspaceStatus`,
  `ReviewerAssignment`, or `ExportPackageCard`.
- Keep authenticated screens operational and dense.
- Use cards for repeated objects or real panels, not page sections inside page
  sections.
- Favor tables, lists, segmented controls, filters, tabs, and side panels over
  decorative dashboard tiles.
- Every visible status should be actionable, auditable, or explanatory.
- Empty states should help the user start the next real workflow step.

## Copy Voice

Address the reader directly and professionally.

Use:

- "Create workspace"
- "Invite reviewer"
- "Record decision"
- "Resolve conflict"
- "Export audit package"

Avoid:

- vague marketing claims,
- playful filler,
- AI-generated-sounding explanations,
- instructional text that describes obvious UI behavior,
- dramatic productivity promises.

## Current App Fit

The current welcome screen is close to the desired tone:

- simple,
- professional,
- clear about systematic review work,
- light on decorative elements,
- grounded in audit and collaboration.

The next UI pass should refine it rather than replace it. The authenticated
dashboard should become more distinct by adopting Nexus-specific status
components and a stronger visual hierarchy for workspaces, roles, audit events,
and workflow progress.

## First Implementation Targets

1. Define final MVP design tokens in `resources/css/app.css`.
2. Create a small Nexus mark and use it consistently in the welcome screen,
   sidebar, favicon, and auth screens.
3. Add domain UI components for workflow status and decisions.
4. Rework the dashboard into a research workspace overview rather than a generic
   account dashboard.
5. Verify the welcome screen and authenticated shell in browser screenshots.

## References

- shadcn Skills: `https://ui.shadcn.com/docs/skills`
- shadcn Rhea preset: `https://ui.shadcn.com/create?preset=b27GcrRo`
- shadcn MCP docs: `https://ui.shadcn.com/docs/mcp`
- shadcn login blocks: `https://ui.shadcn.com/blocks/login`
