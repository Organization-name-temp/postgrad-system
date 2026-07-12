# Design System — Postgraduate Registration System (PRS)

**Egypt-Japan University of Science and Technology (E-JUST)**

| | |
|---|---|
| **Status** | Design system v1.1 — baseline (v1.0) + Phase 0.5 implementation decisions (both from `/grill-with-docs` sessions) |
| **Decision record** | [adr/0009-mui-ui-foundation-design-system.md](./adr/0009-mui-ui-foundation-design-system.md) |
| **Companions** | [tech-stack.md](./tech-stack.md) · [architecture.md](./architecture.md) · [ROADMAP.md](./ROADMAP.md) |
| **Ubiquitous language** | [../CONTEXT.md](../CONTEXT.md) (status names below are canonical from here) |
| **Implemented by** | **Phase 0.5 — Design System & Theming** (see ROADMAP §Phase 0.5) |

This document is the **single source of truth for how the PRS looks and behaves**. It is
the human-readable catalogue; the machine-readable source is `frontend/src/app/theme/tokens.ts`
(built in Phase 0.5), which feeds MUI's `createTheme`. When the two disagree, the token
file is authority for *values* and this document is authority for *rules and intent*.

The UI foundation is **MUI (Material UI) v9** on React 19 — retained, not chosen anew, in
[ADR-0009](./adr/0009-mui-ui-foundation-design-system.md) (Ant Design and shadcn/ui were
weighed and rejected). Everything below is expressed as **MUI theme tokens** so it lands
directly in `createTheme`.

---

## 0. How to use this document

- **Never hard-code a colour, font size, radius, or spacing value in a component.** Read a
  token (`theme.palette.*`, `theme.spacing()`, `theme.shape`, `theme.typography.*`, or a
  CSS variable `--mui-palette-*`). If a value you need has no token, add the token first.
- **Colour is never the only signal.** Every status/meaning conveyed by colour is *also*
  conveyed by text and/or an icon (WCAG 1.4.1). See §10 and §12.
- **Design for both directions and both schemes.** Every screen must be checked in the
  full matrix: **light-LTR · light-RTL · dark-LTR · dark-RTL** (see §11, §12).
- **Token tiers** (see §13): *primitive* (raw ramps) → *semantic* (roles) → *component*
  (only where a component needs something the semantic tier can't express). Components read
  semantic/component tokens, never primitives directly.

---

## 1. Brand foundations

### Assets
- **Mark** — `frontend/public/logo.jpg`: the E-JUST monogram (red disc + black form).
  Use where space is tight or the wordmark is redundant (favicon, collapsed nav, avatars).
- **Lockup** — `frontend/public/name.png`: the mark + trilingual wordmark
  (Arabic / "E-JUST" / Japanese + English tagline). Use in the login screen, document
  headers (transcripts), and the expanded app-bar brand.

### Usage rules
- **Clear space:** keep padding of at least the height of the red disc around the mark on
  all sides. Never crowd the logo with text or controls.
- **Minimum size:** mark ≥ 24px; lockup ≥ 140px wide (below that the tagline is illegible —
  switch to the mark).
- **Placement:** on light surfaces use the asset as-is; on dark surfaces (dark mode app
  bar, dark hero) place the logo on a **neutral/white chip** (`bgcolor: common.white`,
  `radius-md`, small padding) — the black form must never sit on a dark background. The chip
  is the **Phase 0.5 approach** because no transparent/white-knockout logo asset exists yet
  (see cleanups below); a knockout SVG variant is the better long-term option once brand
  vector art is available.
- **Don'ts:** don't recolour the mark, don't stretch/skew, don't add shadows/outlines,
  don't rotate, don't reconstruct it from the brand-red token (the asset is authoritative).
- The logo is **not** mirrored in RTL (see §11).

### Asset cleanups (flagged in Phase 0.5, not blocking it)
- `frontend/public/favicon.svg` is currently a **purple placeholder unrelated to the
  brand** — replace with the real E-JUST mark.
- `frontend/public/icons.svg` is a **leftover social-icon sprite** — remove (iconography is
  `@mui/icons-material`, §7).
- `logo.jpg` is opaque and `name.png` is indexed-palette; replacing them with a
  **transparent SVG/PNG** removes the need for the white chip on dark and any non-white
  surface. A good later win when brand vector art appears.

---

## 2. Colour — primitive ramps

Raw palettes. Components do **not** consume these directly; they consume the semantic
tokens in §3. All ramps run `50` (lightest) → `900` (darkest).

### Brand red — from the sampled logo red `#E4002B`
The E-JUST brand colour. **`500` is the canonical brand red.**

| Step | Hex | Notes |
|------|-----|-------|
| 50 | `#FFF1F3` | tint / hover wash |
| 100 | `#FFDCE1` | selected-row / chip fill |
| 200 | `#FFB3BE` | |
| 300 | `#FF8296` | on-dark text/accents |
| 400 | `#F5476A` | |
| **500** | **`#E4002B`** | **canonical brand red — fills, accents, large text/UI only** |
| 600 | `#C40024` | hover/pressed on brand fills |
| **700** | **`#B00020`** | **accessible brand-red *text*/links on light (≥ 4.5:1)** |
| 800 | `#8A0019` | |
| 900 | `#5E0011` | |

> **Contrast rule (load-bearing):** vivid `#E4002B` on white is ≈ **4.85:1** — it *technically*
> clears WCAG AA (4.5) for normal text, but only just, and it drops below on tinted/off-white
> surfaces. So treat `500` as a **fills / accents / large-text** colour (its comfortable
> ≥ 3:1 zone), and use **`700` (`#B00020`, ≈ 7.3:1)** for any red body text or links on light
> surfaces where you want real headroom. On dark surfaces, use `300 (#FF8296)` for red
> text/links. *(These ratios are enforced by the Phase 0.5 token-contrast gate — a Vitest
> test over `tokens.ts` using a WebAIM-verified contrast util; measured, not assumed.)*

### Ink / neutral — cool-tinted greys toward the logo black
Carries the bulk of the UI (surfaces, borders, text) — the "neutral-led" strategy.

| Step | Hex | Typical role |
|------|-----|--------------|
| 0 | `#FFFFFF` | base surface (light) |
| 50 | `#F7F8FA` | app background (light) |
| 100 | `#EDEFF2` | subtle fill / hover |
| 200 | `#DDE1E7` | borders / dividers (light) |
| 300 | `#C4CAD3` | disabled border |
| 400 | `#9AA2AE` | disabled text / placeholder |
| 500 | `#6B7280` | secondary text (light) |
| 600 | `#4B5560` | |
| 700 | `#333B45` | body text (light) |
| 800 | `#1E242B` | surface (dark) / headings |
| 900 | `#111418` | **brand ink** — app background (dark), high-emphasis text |
| 950 | `#0B0E11` | deepest dark background |

### Slate-blue — secondary
A calm, muted navy for **non-destructive** interactive accents (secondary buttons, links
in dense views, tabs) so interactive colour isn't always red. Distinct from `info` (§3):
slate is a muted structural navy; `info` is a brighter azure reserved for status.

| Step | Hex |
|------|-----|
| 50 | `#EEF3FB` |
| 100 | `#D6E2F3` |
| 200 | `#AEC4E6` |
| 300 | `#7E9FD3` |
| 400 | `#547CBD` |
| **500** | **`#3B6098`** |
| 600 | `#2F4E7D` |
| 700 | `#263F65` |
| 800 | `#1D304D` |
| 900 | `#152238` |

### Semantic hues
Tuned to stay **distinct from brand red** — note `error` is a deeper crimson, never the
bright brand `#E4002B`, so "danger" and "brand" never read identical.

| Hue | light `.main` | light `.light` (fill) | light `.dark` (text) | dark-mode `.main` |
|-----|---------------|------------------------|----------------------|-------------------|
| **success** (green) | `#1E8E52` | `#E7F5ED` | `#14663B` | `#45C97F` |
| **warning** (amber) | `#B26A00` | `#FBF0DC` | `#8A5200` | `#E8A33D` |
| **error** (crimson — ≠ brand) | `#B3261E` | `#FBEAE8` | `#8C1D18` | `#F2B8B5` |
| **info** (azure) | `#1570C4` | `#E6F1FB` | `#0E4E8C` | `#7FB2F0` |

---

## 3. Colour — semantic tokens (light + dark)

Role tokens the app actually consumes, per colour scheme. Wired into
`createTheme({ cssVariables: true, colorSchemes: { light, dark } })`, so MUI emits each as
a CSS variable (`--mui-palette-…`) and flips them on the `data-mui-color-scheme` attribute
with no reload. **Both schemes ship in v1.**

| Semantic token | Light | Dark | Role |
|----------------|-------|------|------|
| `background.default` | `ink.50 #F7F8FA` | `ink.950 #0B0E11` | app canvas |
| `background.paper` / `surface` | `ink.0 #FFFFFF` | `ink.900 #111418` | cards, sheets, app bar |
| `surface.variant` | `ink.100 #EDEFF2` | `ink.800 #1E242B` | subtle raised fills, table header |
| `text.primary` / `onSurface` | `ink.900 #111418` | `ink.50 #F7F8FA` | body text |
| `text.secondary` | `ink.500 #6B7280` | `ink.300 #C4CAD3` | secondary text |
| `text.disabled` | `ink.400 #9AA2AE` | `ink.600 #4B5560` | disabled text |
| `divider` / `border` | `ink.200 #DDE1E7` | `#2A323B` | borders, dividers (elevation-by-border) |
| `primary.main` | `brand.500 #E4002B` | `brand.500 #E4002B` | brand accent, primary CTA, active nav |
| `primary.dark` | `brand.600 #C40024` | `brand.600` | hover/pressed |
| `primary.contrastText` | `#FFFFFF` | `#FFFFFF` | text on brand fills |
| `primary.text` (links/red text) | `brand.700 #B00020` | `brand.300 #FF8296` | accessible brand-red text |
| `secondary.main` | `slate.500 #3B6098` | `slate.300 #7E9FD3` | secondary actions, non-status accents |
| `success.*` / `warning.*` / `error.*` / `info.*` | see §2 semantic table | dark `.main` column | status + validation |
| `focusRing` | `slate.400 #547CBD` @ 2px | `slate.300 #7E9FD3` @ 2px | keyboard focus (see §12) |

**Rules**
- `primary` is used *sparingly* — app bar brand element, active nav indicator, the single
  primary CTA per view, selected states. It does **not** paint whole toolbars or every
  button. Neutrals carry structure.
- `secondary` (slate) is the default for non-primary interactive elements so red stays rare.
- `error` (crimson `#B3261E`) is reserved for destructive/validation only and is visually
  distinct from brand red; destructive actions always add an icon + confirm (see §9).

---

## 3.5 Theming architecture — the two axes

The UI varies along **two independent axes** that use *different* mechanisms and compose
without interfering (settled in the Phase 0.5 planning grill):

- **Colour scheme (light ⇄ dark)** — driven by `cssVariables: true` + `colorSchemes`
  (§3). Toggling flips the `data-mui-color-scheme` attribute on `<html>`; MUI swaps the
  emitted CSS variables with **no theme recreation and no React re-render**. Managed by
  MUI's `useColorScheme` (localStorage-backed, `defaultMode: 'system'` so a first visit
  follows the OS). An `InitColorSchemeScript` in `index.html` sets the attribute before
  first paint, so there is **no flash** of the wrong scheme.
- **Direction + font (EN/LTR ⇄ AR/RTL)** — driven by **recreating the theme** and
  **swapping the Emotion cache** (the Phase-0 mechanism in §11). Neither can be a variable
  flip: `stylis-plugin-rtl` rewrites CSS at injection time, and `fontFamily` is a theme
  value. So the single factory `createAppTheme(direction, lang)` is memoised on
  **language only** and always bakes in *both* colour schemes.

The dark selection **survives a language switch** because it lives in the DOM attribute +
localStorage, not in the theme object. Use `<CssBaseline enableColorScheme />` so native
form controls and scrollbars track the scheme.

---

## 4. Typography

**Families (self-hosted via `@fontsource`, no CDN):**
- **Latin (EN):** `IBM Plex Sans` — `@fontsource/ibm-plex-sans`
- **Arabic (AR):** `IBM Plex Sans Arabic` — `@fontsource/ibm-plex-sans-arabic`
- Same type system across scripts → harmonised weights/metrics. Weights shipped: 400, 500,
  600, 700.
- **Loading (Phase 0.5):** import **only the used subsets** — `ibm-plex-sans` → *latin*,
  `ibm-plex-sans-arabic` → *arabic* — at those four weights (per-subset `@fontsource` CSS,
  not the whole package). `font-display: swap` (fallback shows immediately, no invisible
  text). **Preload** the two hot weights (400 body + 600 heading) of the active language.

**Family switching:** set `theme.typography.fontFamily` by language inside
[frontend/src/app/AppThemeProvider.tsx](../frontend/src/app/AppThemeProvider.tsx) (it
already keys on `i18n.language`). Fallback stacks:
- EN: `'IBM Plex Sans', system-ui, 'Segoe UI', Roboto, Arial, sans-serif`
- AR: `'IBM Plex Sans Arabic', 'Segoe UI', Tahoma, sans-serif`

**Type scale** (px; `size / line-height`, weight). Arabic uses the **same sizes** but the
**larger line-heights** in the AR column (no capitals + taller glyph stacks need more leading):

| Variant | Size / LH (EN) | LH (AR) | Weight | Use |
|---------|----------------|---------|--------|-----|
| h1 | 40 / 48 | 56 | 700 | page hero (rare) |
| h2 | 32 / 40 | 46 | 700 | page title |
| h3 | 28 / 36 | 42 | 600 | section |
| h4 | 24 / 32 | 38 | 600 | sub-section / dialog title |
| h5 | 20 / 28 | 34 | 600 | card title |
| h6 | 16 / 24 | 30 | 600 | dense heading / table caption |
| subtitle1 | 16 / 24 | 28 | 500 | emphasised body |
| subtitle2 | 14 / 20 | 24 | 500 | labels |
| body1 | 16 / 24 | 28 | 400 | default body |
| body2 | 14 / 20 | 24 | 400 | dense body / table cell |
| button | 14 / 24 | 24 | 600 | buttons (**`textTransform: none`** — no ALL-CAPS) |
| caption | 12 / 16 | 20 | 400 | helper/meta text |
| overline | 12 / 16 | 20 | 600 | eyebrow labels (uppercase EN only) |

**Rules**
- **Buttons are not uppercased** (`textTransform: none`) — MUI's default ALL-CAPS breaks
  Arabic and looks shouty; overriding it globally is part of the theme.
- **Tabular figures** (`font-feature-settings: 'tnum' 1`) on all data grids, transcripts,
  GPA/credit-hour tables, and numeric columns so digits align.
- **Numerals stay LTR** even in Arabic text (course codes, IDs, GPA, dates) — see §11.
- Body text minimum 14px; never below 12px for meaningful content.

---

## 5. Spacing & layout

**Base unit: 8px** (`theme.spacing(1) === 8px`). Named scale:

| Token | px | `spacing()` |
|-------|----|-----|
| space-0 | 0 | 0 |
| space-1 | 4 | 0.5 |
| space-2 | 8 | 1 |
| space-3 | 12 | 1.5 |
| space-4 | 16 | 2 |
| space-5 | 24 | 3 |
| space-6 | 32 | 4 |
| space-7 | 48 | 6 |
| space-8 | 64 | 8 |

**Breakpoints (MUI defaults):** `xs 0 · sm 600 · md 900 · lg 1200 · xl 1536`.
**Content max-width:** `1280px`, centred, with `space-5`(24) gutters (`space-4`(16) on `xs`).

**App shell.** Phase 0.5 builds the reusable **shell chrome** — everything backed by data
that already exists via `GET /me`. Two app-bar features that need endpoints not yet built —
**global search** and the **notifications bell** — are **deferred to the phases that
introduce their data** (the bell to the phase that adds notifications; search later). The
shell chrome is consumed by every subsequent phase.

- **Top app bar** (`surface`, 1px bottom border, height 64): logo lockup (start), page
  title/breadcrumbs, then (end) language switcher, theme toggle, user menu. *(Global search
  and the notifications bell, §9, slot in here later — deferred, not built in 0.5.)*
- **Side navigation** (permanent on `lg+`, drawer on smaller): **role-aware** — items
  filtered by the user's effective Permissions (mirrors the API authority, display-only).
  Active item uses the `primary` accent (left border in LTR / right border in RTL). Built
  in 0.5 with the destinations that exist (dashboard, dev-only `/style`); each later phase
  registers its own items.
- **Content region:** page header (title + primary action) → body. Max-width as above.

---

## 6. Shape & elevation

**Radius**

| Token | px | Use |
|-------|----|----|
| radius-sm | 4 | chips, badges, small controls |
| radius-md | 8 | **default** — buttons, inputs, menus, dialogs |
| radius-lg | 12 | cards, sheets, large containers |
| radius-pill | 999 | pills / avatar / toggle |

**Elevation — flat & border-led.** Separate panels with a **1px `divider` border**, not a
shadow. Reserve soft shadows for genuinely floating layers. Shadows are near-invisible in
dark mode, so dark relies on `surface`/`surface.variant` contrast + borders.

| Token | Use | Light shadow |
|-------|-----|--------------|
| elevation-0 | cards, panels (border only) | none + 1px `divider` |
| elevation-1 | menus, dropdowns, popovers | `0 1px 2px rgba(17,20,24,.08), 0 2px 8px rgba(17,20,24,.06)` |
| elevation-2 | dialogs, modals | `0 4px 12px rgba(17,20,24,.12), 0 8px 24px rgba(17,20,24,.10)` |
| elevation-3 | transient (drag) | `0 8px 28px rgba(17,20,24,.18)` |

---

## 7. Iconography

- **Set:** `@mui/icons-material` (Material Symbols), consistent stroke/weight. Add the
  package in Phase 0.5. One icon set only — no mixing.
- **Sizes:** 16 (dense/inline), 20 (default in buttons/inputs), 24 (nav/toolbars).
- **RTL mirroring:** **directional** icons flip in RTL — back/forward arrows, chevrons,
  breadcrumbseparators, list bullets pointing to content, send, undo/redo, next/prev
  steppers. **Non-directional** icons do **not** flip — logos, media (play), clocks,
  checkmarks, search (magnifier), account, download. MUI mirrors many automatically under
  `direction: 'rtl'`; verify each in the RTL matrix (§11).
- Icons that carry meaning need an `aria-label`; decorative icons get `aria-hidden`.

---

## 8. Motion

- **Durations:** `fast 100ms` (hover/press), `base 200ms` (most transitions), `slow 300ms`
  (dialogs, drawers, RTL/theme flip).
- **Easing:** standard `cubic-bezier(0.2, 0, 0, 1)`; decelerate on enter, accelerate on exit.
- **`prefers-reduced-motion`:** honour it — drop non-essential transitions to near-instant
  (≤ 1ms) and disable large movement/parallax. This is a WCAG requirement, not a nicety.

---

## 9. Component specs

Each maps to an MUI component with PRS usage rules. Theme-level overrides live in the
`components` slot of `createTheme` (Phase 0.5).

- **Buttons** — `contained` = the single primary action (uses `primary` brand red);
  `outlined`/`text` (slate `secondary`) for everything else; **destructive** =
  `color="error"` **contained + leading icon + confirmation dialog** (never a bare red
  button). No ALL-CAPS. Min height 40 (comfortable) / 32 (compact/table row actions).
- **Forms & inputs** — MUI `outlined` variant + **React Hook Form + Zod**. Inline,
  real-time validation (FR-ADM-001.3): error state = `error` crimson border + helper text
  **with an error icon** (colour is never the only signal). Every field has a visible
  `<label>` (not placeholder-as-label). Required marked with text, not colour alone.
- **Select / Autocomplete** — Autocomplete for > ~8 options or async (programs, courses,
  advisors). RTL-aware.
- **TreeView** (Phase 1 catalog) — `RichTreeView` from `@mui/x-tree-view` (MIT). Expandable,
  searchable/filterable; University → Faculty → Dept → Program → Course. Row = localised
  name + code (code stays LTR, §11) + optional status chip. Keyboard navigable; expand/
  collapse chevrons mirror in RTL.
- **Stepper** (Phase 2 admissions wizard) — MUI `Stepper` (core, free), linear: Personal
  Info → Email Verify → Documents → Program Selection. Step state = icon + label + colour
  (done = success check, active = primary, error = error). Horizontal on `md+`, vertical on
  small. Next/Back mirror in RTL.
- **DataGrid / Table** (Phase 7 reports, transcripts) — `@mui/x-data-grid` **MIT/free tier**
  (sort, filter, pagination) — advanced Pro features are consciously out of scope
  ([ADR-0009](./adr/0009-mui-ui-foundation-design-system.md)). Density = **compact** rows;
  numeric columns tabular + end-aligned (start-aligned in RTL follows logical alignment).
  Every grid has loading, empty, and error states (below).
- **Cards / panels** — `elevation-0` (border only), `radius-lg`, `space-5` padding.
- **Dialogs** — `elevation-2`, `radius-md`. Destructive confirms name the object and the
  consequence ("Terminate enrollment for …? This cannot be undone."), error-coloured
  confirm button.
- **Snackbar / toast** — transient success/info/error feedback, top-`end` aligned
  (flips in RTL), auto-dismiss 4–6s, never for critical errors (use inline/dialog).
- **Notifications (bell)** — polled in-app list (per ROADMAP; no websockets). Unread count
  badge on the app-bar bell; panel lists localised notifications with relative timestamps.
  **Deferred past Phase 0.5** — built with the phase that introduces notification data; the
  0.5 shell leaves its app-bar slot empty (§5).
- **Tabs / breadcrumbs** — breadcrumb separators are directional (mirror in RTL).
- **Status chips** — see §10; the single reusable `<StatusChip>` component.
- **States (every data view must define all three):**
  - *Loading* — skeletons matching final layout (not spinners) for tables/cards.
  - *Empty* — icon + one-line explanation + primary action where relevant ("No courses
    registered yet — Register courses").
  - *Error* — inline message + retry; never a blank screen.

---

## 10. ★ Domain status-colour system

The PRS is dense with domain statuses (see [../CONTEXT.md](../CONTEXT.md)). Each is rendered
by the **single `<StatusChip>` component** with a fixed colour + **always** a text label,
and an icon where it aids scanning. **Colour never stands alone** (WCAG 1.4.1). Non-counting
statuses (don't affect CGPA) render **outlined/muted**; negative-counting render **filled**.

> **Ownership (Phase 0.5).** `<StatusChip>` is **presentational** and lives in
> `shared/ui`: its props are `tone` (success/warning/error/info/neutral/secondary),
> `variant` (filled / filled-soft / outlined), `icon`, and `label`. It encodes the *rules*
> below (non-counting → outlined, negative → filled) but **knows no status names** — that
> would put domain vocabulary in `shared/` ahead of the domains that own it (frontend seam,
> architecture.md §7). Each later phase colocates its own `statusToChip` map (a `feature` →
> `shared` import). The tables below are the **design catalogue**; in Phase 0.5 the `/style`
> gallery renders them as **static demo data** to prove the design — it ships no
> authoritative domain map.

### Application status  (Draft → … → Accepted/Rejected)
| Status | Token | Style |
|--------|-------|-------|
| Draft | neutral (`ink.500`) | outlined |
| Submitted | `info` | filled-soft |
| Email Verified | `info` | filled-soft |
| Under SA Review / Dean Review / Interview Scheduled | `secondary` (slate) | filled-soft (in-progress) |
| Accepted | `success` | filled |
| Conditionally Accepted | `warning` | filled |
| Waitlisted | `warning` | outlined |
| Rejected | `error` | filled |

### Course Status  (accounting classification of an Enrollment)
| Status | Token | Style | Counts toward CGPA? |
|--------|-------|-------|---------------------|
| Regular | neutral (`ink.600`) | outlined | yes |
| Retake | `warning` | filled-soft | yes |
| Audit (AU) | `info` | outlined | no |
| Withdrawal (W) | neutral (`ink.500`) | outlined | no |
| **Fail-Withdrawal (FW)** | `error` | filled | counted as-is / excluded per Appendix D |
| Incomplete (I) | `warning` | filled-soft | pending |
| CGPA-Improvement | `info` | filled-soft | yes (special) |
| Transfer | `secondary` | outlined | no (Required CH only) |
| Waive | `secondary` | outlined | no (Required CH only) |
| Exempted | `secondary` | outlined | no (reduces Required) |

### Academic Standing
| Standing | Token | Style |
|----------|-------|-------|
| Good Standing | `success` | filled-soft |
| Academic Warning | `warning` | filled-soft |
| Academic Probation | `warning.dark` (deep amber) | filled |
| Termination Pending | `error` | filled + warning icon |

### Grade display  (always shown as the letter; colour is secondary)
| Grade group | Text colour |
|-------------|-------------|
| Earned & counted (A+ … C+, ≥ 2.5) | `text.primary` |
| Counted but sub-earn (below C+, not F) | `warning.dark` |
| F / FL | `error` |
| P (Pass) | `success.dark` |
| AU / W (non-counting) | `text.secondary` (muted) |
| FW | `error` (muted) |
| I | `warning.dark` |

---

## 11. RTL & bilingual rules

- **Mechanism (already built, Phase 0):** dual Emotion caches (`ltr` + `muirtl` with
  `stylis-plugin-rtl`) + `theme.direction` swap in
  [AppThemeProvider.tsx](../frontend/src/app/AppThemeProvider.tsx). Language switch flips
  the whole UI with no reload; also sets `<html dir>` and `lang`.
- **Prefer logical CSS** (`margin-inline-start`, `padding-inline-end`, `inset-inline-*`)
  over physical (`margin-left`, …) in custom styles so they mirror automatically.
- **What mirrors:** layout direction, text alignment, nav/drawer side, directional icons
  (§7), stepper/breadcrumb/pagination order, table column order.
- **What stays LTR even in Arabic context:** the logo (§1); **numerals** (GPA, credit
  hours, dates); **identifiers** (University ID, course codes like `CSE 501`); code/URLs;
  chart axes with numeric scales. Wrap these in a `dir="ltr"` inline span inside Arabic text.
- **Numbers & dates:** format per locale via `Intl` (`i18n.language`). Decide Arabic numeral
  form (Western `0-9` vs Eastern Arabic `٠-٩`) once and apply consistently — **default to
  Western digits** for academic records for cross-locale legibility (revisit if the registrar
  requires Eastern).
- **Never concatenate translated fragments** — use full i18next interpolation so RTL word
  order is correct.

---

## 12. Accessibility — WCAG 2.1 AA (CI gate, NFR-USB-003)

- **Contrast:** text ≥ 4.5:1 (normal) / 3:1 (large ≥ 24px or 18.66px bold); UI components &
  focus indicators ≥ 3:1. The brand-red text rule (§2) exists to satisfy this.
- **Colour independence:** never encode meaning by colour alone — pair with text/icon (§10).
- **Focus visible:** a 2px `focusRing` (slate) offset ring on every interactive element;
  never remove outlines without an equivalent. Logical focus order; visible skip-to-content.
- **Targets:** interactive hit area ≥ 44×44px (compact table actions may shrink visually but
  keep a 44px hit box).
- **Forms:** programmatic `<label>` association; errors announced (`aria-live`), described
  by `aria-describedby`; required state in text.
- **Keyboard:** everything operable without a mouse — tree, stepper, grid, dialogs (focus
  trap + Esc), menus.
- **Reduced motion:** honour `prefers-reduced-motion` (§8).
- **Language:** `<html lang>` tracks the active locale so screen readers switch voice.

**How the gate is made real (Phase 0.5):** two mechanisms, both cross-OS-deterministic.
1. **Token-contrast unit test (Vitest)** — a WebAIM-verified ratio function asserts every
   readable semantic pair (light + dark) meets AA (≥ 4.5 body / ≥ 3 large & UI), and encodes
   the §2 brand-red rule: the text token `#B00020` (≈ 7.3:1) clears AA with real headroom —
   more than the vivid `#E4002B` (≈ 4.85:1), which is why `500` stays a fills/large colour and
   `700` carries red text. **Scope:** it gates text and essential UI indicators (focus ring,
   status fills); **disabled text** (WCAG 1.4.3) and the **decorative elevation dividers** (§6
   — never the sole cue for a boundary) are intentionally exempt, while **interactive/input
   border** contrast (SC 1.4.11) is verified at the component level as those components land.
2. **`@axe-core/playwright`** on the `/style` gallery + the re-skinned login/dashboard
   across all four combos, failing on serious/critical violations (axe reads computed CSS
   colours, not pixels, so it does not suffer the cross-OS flakiness of screenshots).
Also enforced: an **ESLint `no-restricted-syntax`** rule bans raw hex-colour literals
outside `tokens.ts` (§0's "never hard-code a colour").

---

## 13. Token naming convention

Three tiers; components read tiers 2–3 only.

1. **Primitive** — raw, meaningless-until-assigned ramps. `brand.500`, `ink.900`,
   `slate.500`, `green.main` (§2). Never referenced directly by a component.
2. **Semantic** — role tokens per colour scheme. `primary.main`, `surface`, `onSurface`,
   `border`, `text.secondary`, `error.main`, `focusRing`, status colours (§3, §10). This is
   what components use.
3. **Component** — only when a component needs a value the semantic tier can't express
   (e.g. `dataGrid.headerBg`, `appBar.height`). Kept minimal.

Source of truth is `frontend/src/app/theme/tokens.ts`:
```
tokens.primitive.brand[500]        // #E4002B
tokens.semantic.light.primary.main // -> primitive.brand[500]
tokens.semantic.dark.surface       // -> primitive.ink[900]
```
Semantic tiers feed `createTheme({ cssVariables: true, colorSchemes: { light, dark } })`;
MUI then emits `--mui-palette-primary-main` etc. for any raw-CSS consumer. A W3C-DTCG /
Style-Dictionary export is a **documented future option** (only if a non-MUI consumer ever
appears) — not built now ([ADR-0009](./adr/0009-mui-ui-foundation-design-system.md)).

---

## 14. Data-visualisation colours (Phase 7 reports)

For charts (GPA distribution, application pipeline, enrolment summary). Keep chart series
**distinct from the status semantics** so a bar isn't misread as "error".

- **Categorical** (colourblind-aware, ordered): `#3B6098` slate · `#2A9D8F` teal ·
  `#7B5EA7` violet · `#E8912B` amber · `#3AA0C9` cyan · `#C65B7C` rose · `#8A9A3B` olive ·
  `#6B7280` grey. Use in this order; **do not** start a neutral series with brand red
  (implies "bad").
- **Sequential** (single hue, e.g. GPA-distribution intensity): slate `100 → 800`.
- **Pass/fail or standing charts:** use `success`/`warning`/`error` **but always** label the
  axis/legend in text — never rely on the colour alone.
- Chart text/gridlines use `text.secondary` / `divider`; respect dark mode.

*See [artifact/dataviz guidance] for the full method if these need extending; this section
is the PRS-specific default.*

---

*The reasoning behind these choices — and the Ant Design / shadcn alternatives we rejected
— is in [ADR-0009](./adr/0009-mui-ui-foundation-design-system.md). Implementation is
scheduled as **Phase 0.5** in [ROADMAP.md](./ROADMAP.md).*
