# MUI as UI foundation + token-driven E-JUST design system

**Status:** accepted

The frontend was scaffolded on **MUI (Material UI)** in Phase 0, but with no palette,
typography, or design tokens — the *framework* was chosen while the *visual language* was
not. This ADR (a) confirms MUI as the UI foundation over Ant Design and shadcn/ui rather
than reversing a load-bearing decision, and (b) records the design-system shape: a
**neutral-led palette with the E-JUST brand red as a disciplined accent**, **light + dark**
schemes, and **token-driven theming** via a hand-authored `tokens.ts` feeding MUI's
`createTheme`. The full catalogue lives in [../design-system.md](../design-system.md);
this ADR holds only the *why* and the rejected alternatives. It is implemented in
**Phase 0.5** ([../ROADMAP.md](../ROADMAP.md)).

## Considered options

### UI framework

- **MUI, retained (chosen).** MUI already carries the entire bilingual EN↔AR / RTL
  mechanism shipped in Phase 0 — dual Emotion caches + `stylis-plugin-rtl` + `theme.direction`
  in `AppThemeProvider` — which is green in the Phase 0 Playwright smoke. It ships the three
  components the roadmap is built around: a **Tree view** (`@mui/x-tree-view`, MIT) for the
  Phase 1 catalog, a **Stepper** (core, MIT) for the Phase 2 admissions wizard, and a
  **DataGrid** (`@mui/x-data-grid`, MIT free tier) for Phase 7 reports. Its accessibility is
  strong (relevant because WCAG 2.1 AA is a CI gate, NFR-USB-003) and it has native React 19
  support and a mature `createTheme` token model. **Migration cost from where we are is
  zero.** The one accepted cost: MUI X DataGrid's *advanced* features (column pinning, tree
  data, row grouping, aggregation, Excel export) are commercial — we deliberately stay on the
  free tier, which covers the 8 read-only reports.
- **Ant Design (rejected).** Best free-component story (Tree, Steps, Table all free MIT) and
  the simplest RTL switch (`ConfigProvider direction`). But its **accessibility is the
  weakest of the three** — no a11y docs, documented WCAG 2.1 failures, and a11y de-prioritised
  upstream — which collides directly with the WCAG AA CI gate. Switching would also be a full
  re-platform of the Phase 0 auth/i18n/RTL shell for no requirement gain.
- **shadcn/ui + Tailwind (rejected).** Excellent accessibility via Radix primitives and full
  ownership of the code. But it **ships none** of our three key components — the Tree and the
  Stepper would be hand-built (and their a11y hand-audited), the grid assembled from TanStack
  Table — *and* it demands a full re-platform. Most work for a project whose value is the
  domain logic, not the component library.

### Palette strategy

- **Neutral-led, brand red as a disciplined accent (chosen).** The PRS domain is saturated
  with red-*meaning* — Fail, FW, Academic Warning, Termination Pending. If the vivid brand red
  (`#E4002B`, sampled from the logo) were also the everyday primary-button/toolbar colour,
  "brand" and "danger" would be visually indistinguishable across screens full of status
  chips. So neutrals (ink + greys) carry the structural, table-heavy surfaces; brand red is
  reserved for identity moments (app bar, logo, primary CTA, selected states); a slate-blue
  secondary handles non-destructive interactive accents; and the semantic **`error` red is a
  deeper crimson (`#B3261E`), deliberately distinct from the brand red**.
- **Brand-forward (rejected).** `primary` = brand red everywhere. Maximum brand presence, but
  in a form/table-heavy registrar UI it reads as "everything is urgent" and muddies the
  status semantics the domain depends on.

### Tokenising mechanism

- **Hand-authored three-tier `tokens.ts` (chosen).** Primitive → semantic → component tiers
  in one typed file feeding `createTheme({ cssVariables: true, colorSchemes: { light, dark } })`.
  Because CSS variables are enabled, MUI **auto-emits** `--mui-palette-*` custom properties —
  portable CSS tokens for free, and `colorSchemes.dark` gives dark mode without component
  rewrites. No build step, one source of truth.
- **W3C-DTCG JSON + Style Dictionary (rejected, for now).** Framework-neutral and future-proof,
  but a build step, a dependency, and indirection maintained for a *single* consumer (MUI).
  Recorded as a documented future option if a non-MUI consumer (native app, cross-framework
  Storybook) ever appears — callers depend on the token *names*, not on how they're built.

## Consequences

- A new **Phase 0.5 — Design System & Theming** slice sits between the Phase 0 review gate and
  Phase 1: it builds `tokens.ts`, wires the themed `createTheme` (light + dark + RTL),
  self-hosts **IBM Plex Sans + IBM Plex Sans Arabic** via `@fontsource`, re-skins the Phase 0
  screens to tokens, adds a reusable `<StatusChip>` and a dev-only `/style` gallery, and keeps
  the gates green. It is the one legitimately frontend-only slice (no new Pest domain tests).
- Contrast is load-bearing: vivid `#E4002B` on white is ≈ 4:1 and **fails** AA for body text,
  so the system carries a darkened brand-red (`#B00020`) for red text/links. Enforced by the
  WCAG gate.
- **Both light and dark ship in v1**, so every screen is verified across the full matrix
  light/dark × LTR/RTL. Dark-mode *server persistence* (via `PATCH /me`, mirroring locale) is
  deferred; the v1 toggle is client-side (localStorage).
- Staying on the free MUI X DataGrid tier is a standing constraint — if a future report needs
  Pro features, that is a separate, costed decision, not an assumption.
- [tech-stack.md](../tech-stack.md) and [ROADMAP.md](../ROADMAP.md) reference this ADR and
  [design-system.md](../design-system.md); `CONTEXT.md` is unchanged (the status-colour work
  reuses existing domain terms and adds no new ubiquitous language).
