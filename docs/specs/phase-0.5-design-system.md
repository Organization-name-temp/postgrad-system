# Spec — Phase 0.5 · Design System & Theming

**Egypt-Japan University of Science and Technology (E-JUST) — Postgraduate Registration System (PRS)**

| | |
|---|---|
| **Type** | Phase spec / PRD (from a `/to-spec` session) |
| **Status** | Ready for agent — awaiting `/to-issues` |
| **Sources of truth** | [design-system.md](../design-system.md) (v1.1) · [adr/0009-mui-ui-foundation-design-system.md](../adr/0009-mui-ui-foundation-design-system.md) · [ROADMAP.md](../ROADMAP.md) §Phase 0.5 |
| **Ubiquitous language** | [../../CONTEXT.md](../../CONTEXT.md) |

> This document is also the body published to the issue tracker (label `ready-for-agent`).
> It is self-contained — an agent picking it up needs no prior conversation.

---

## Problem Statement

The PRS frontend was scaffolded in Phase 0 on MUI, but with **no visual language**: the theme
provider builds a bare `createTheme({ direction })` — no palette, typography, spacing scale, or
dark mode. Every screen relies on MUI defaults (ALL-CAPS buttons, generic blue, Roboto, no
brand). There is no reusable status indicator, no theme toggle, and no reference surface where a
developer can see the intended look.

As the later phases (Catalog, Admissions, Enrollment, Grading, …) begin building screens, each
would reinvent colours, spacing, and status styling ad hoc — producing an inconsistent,
inaccessible, un-branded UI and a mountain of rework. The domain is dense with red-*meaning*
statuses (Fail, FW, Academic Warning, Termination Pending); without a disciplined system,
"brand" and "danger" would be visually indistinguishable across screens full of status chips.

- **Developer's view:** "I have nowhere to pull colours, type, spacing, or a status chip from,
  and no way to see what 'correct' looks like — so every phase I build drifts."
- **End-user's view:** "The app looks like an unstyled prototype, ignores my OS dark-mode
  preference, and in Arabic the type feels wrong."

## Solution

Phase 0.5 turns the agreed design language (`docs/design-system.md` v1.1, ADR-0009) into a
themed, tokenised MUI setup that **every later phase consumes**. It is a deliberately
**frontend-only** slice — **no new backend domain logic, no new endpoints, no migrations, no
Pest tests**.

What ships:

- **Token-driven theme** — one MUI-agnostic tokens module holding *all* design values, feeding a
  thin `createAppTheme(direction, lang)` adapter → `createTheme` with `cssVariables: true` and
  `colorSchemes: { light, dark }`.
- **Light + dark both ship**, defaulting to the OS preference, toggled from the app bar with **no
  flash and no reload**, persisted to `localStorage`.
- **IBM Plex Sans / IBM Plex Sans Arabic** self-hosted (no CDN), switched by language, with the
  Arabic leading bump and no ALL-CAPS buttons.
- The **E-JUST brand applied with discipline** — neutral-led surfaces, brand red `#E4002B`
  reserved for identity/primary actions, semantic status colours kept distinct from brand red,
  and the accessible `#B00020` for red text.
- A reusable **app-shell chrome** (top app bar + role-aware side nav) and a reusable
  **`<StatusChip>`** presentational component.
- A **dev-only `/style` gallery** rendering the whole system across light/dark × EN/AR.
- **Accessibility as a real CI gate** — contrast asserted in a unit test, `axe` run on the
  rendered gallery.

## User Stories

Actors: **Developer** (primary consumer, building later phases), **Student / Applicant / Advisor
/ Instructor / Staff** (end users experiencing the theme), **Arabic-speaking user**, **low-vision
/ assistive-tech user**, **Maintainer**.

### Tokens & theme
1. As a developer, I want all colours, type, spacing, radius, shadow, and motion values as named
   tokens in one place, so that I never hard-code a value and every screen stays consistent.
2. As a developer, I want tokens split into primitive → semantic → component tiers, so that
   components read role tokens (e.g. `surface`, `primary`) and I can retune a ramp without
   touching components.
3. As a developer, I want the tokens module to be MUI-agnostic data with a thin adapter to
   `createTheme`, so that the values remain the single source of truth and could feed a non-MUI
   consumer later.
4. As a developer, I want the theme wired with `cssVariables` + `colorSchemes`, so that MUI emits
   `--mui-palette-*` custom properties I can read from raw CSS when needed.
5. As a developer, I want a single `createAppTheme(direction, lang)` factory, so that there is one
   place that assembles the theme and one mental model for how it's built.

### Dark mode
6. As a Student, I want the app to follow my operating-system light/dark preference on first
   visit, so that it matches my environment without any configuration.
7. As a Student, I want a theme toggle in the app bar, so that I can override to light or dark
   whenever I want.
8. As a Student, I want my light/dark choice remembered across visits, so that I don't re-set it
   each time.
9. As a Student, I want no flash of the wrong theme on load, so that the app feels polished.
10. As a developer, I want dark mode to be a CSS-variable attribute flip with no theme recreation
    or React re-render, so that toggling is instant and cheap.
11. As a user, I want native form controls and scrollbars to match the active scheme, so that the
    whole surface feels dark (or light), not just the app chrome.

### RTL & bilingual typography
12. As an Arabic-speaking user, I want the entire UI to render in Arabic with proper RTL layout,
    so that it reads naturally.
13. As an Arabic-speaking user, I want Arabic text set in IBM Plex Sans Arabic with comfortable
    line-height, so that it is legible and not cramped.
14. As an Arabic-speaking user, I want numerals, University IDs, and course codes (e.g. `CSE 501`)
    to stay left-to-right inside Arabic text, so that identifiers and GPA read correctly.
15. As a user switching language, I want the font family and text direction to switch together
    with no reload, so that the transition is seamless.
16. As a user on a slow connection, I want fonts to load with a visible fallback (no invisible
    text), so that I can read content immediately.
17. As a developer, I want the dark selection to survive a language switch, so that flipping
    EN↔AR never resets a user's light/dark choice.

### Typography & brand
18. As a developer, I want a defined type scale (h1 … caption) with fixed sizes and weights, so
    that headings and body text are consistent everywhere.
19. As a user, I want buttons in normal case (not ALL-CAPS), so that the UI reads calmly and
    Arabic isn't mangled by uppercasing.
20. As a user reading tables and transcripts, I want tabular figures, so that digits align in
    numeric columns.
21. As a user, I want the E-JUST brand present (logo lockup, brand-red accents), so that the app
    feels official.
22. As a user in dark mode, I want the logo to remain legible on a light chip, so that the black
    form never disappears into a dark app bar.

### Spacing, shape & elevation
23. As a developer, I want an 8px spacing scale and named radii, so that layout rhythm is
    consistent across every screen.
24. As a developer, I want flat, border-led elevation, so that panels separate cleanly in both
    light and dark (where shadows are near-invisible).

### App-shell chrome
25. As a user, I want a consistent top app bar (brand, page title, language switcher, theme
    toggle, user menu), so that navigation and identity are always where I expect them.
26. As a Student / Advisor / staff member, I want a side navigation that shows only the sections
    my effective Permissions allow, so that I'm not offered things I can't use.
27. As a developer, I want the app-shell chrome built once and reused, so that each later phase
    drops its screens in without rebuilding the layout.
28. As a user, I want the side nav to collapse to a drawer on small screens, so that the app works
    on a laptop or tablet.
29. As a user, I want the active nav item clearly indicated (brand accent, correct side in RTL),
    so that I always know where I am.
30. As a user, I want a user menu with my identity and a log-out action, so that I can see who I'm
    signed in as and sign out from anywhere.

### StatusChip
31. As a developer, I want a single `<StatusChip>` component taking tone / variant / icon / label,
    so that every status across the app looks and behaves consistently.
32. As a low-vision user, I want every status to carry a text label (and often an icon), never
    colour alone, so that I can tell statuses apart.
33. As a developer, I want the chip to encode the fill/outline *rules* (non-counting → outlined,
    negative → filled) but **not** the domain status names, so that domain vocabulary stays in the
    owning feature and the frontend seam stays clean.
34. As a developer building a later phase, I want to supply my own status→chip map inside my
    feature, so that I control my phase's statuses without editing shared code.

### `/style` gallery
35. As a developer, I want a `/style` page showing the palette, type scale, and key components, so
    that I have a living reference for what "correct" looks like.
36. As a developer, I want the gallery to render across all four combos (light/dark × EN/AR), so
    that I can verify the system holds everywhere at a glance.
37. As a maintainer, I want `/style` stripped from production builds, so that it never ships to
    real users.

### Accessibility
38. As a low-vision user, I want text and UI to meet WCAG 2.1 AA contrast, so that I can read the
    app.
39. As a developer, I want a token-contrast test in CI, so that a bad colour pairing fails the
    build instead of a user's eyes.
40. As a keyboard user, I want a visible focus ring on every interactive element, so that I can
    navigate without a mouse.
41. As a motion-sensitive user, I want `prefers-reduced-motion` honoured, so that animations don't
    make me unwell.
42. As a maintainer, I want `axe` run on the rendered gallery in CI, so that accessibility
    regressions are caught automatically.

### Re-skin & enforcement
43. As a user, I want the existing login and dashboard re-skinned onto the new theme, so that the
    whole app looks coherent from day one.
44. As a maintainer, I want a lint rule that fails on raw hex colours, so that "no hard-coded
    colours" is enforced, not merely hoped for.
45. As a maintainer, I want all existing gates to stay green (lint + boundaries, format, `tsc`,
    Vitest + RTL, Playwright), so that the design system doesn't regress the Phase 0 foundation.

## Implementation Decisions

**Slice shape.** Frontend-only. No backend/domain change, no new API contract, no schema/
migration, no Pest tests. `PATCH /api/v1/me` is **not** extended (dark-mode persistence is
deferred — see Out of Scope). Respects ADR-0009 (MUI retained; neutral-led palette; hand-authored
tokens; both schemes v1) and `docs/design-system.md` v1.1.

**Modules built / modified** (frontend seams per `architecture.md` §7 — `app` may compose
anything; `feature` may use `shared`; `shared` may use only `shared`):

- **`app` — theme layer (new):** a tokens module (all design values, MUI-agnostic typed data); a
  `createAppTheme(direction, lang)` adapter; a rewritten theme provider implementing the two-axes
  architecture; an init-color-scheme script in the HTML entry.
- **`app` — shell:** the authenticated layout extended from a single top bar into the reusable
  **app-bar + role-aware side-nav chrome** (user menu; theme-toggle control; the existing language
  switcher reused). Side-nav items filtered by the user's **effective Permissions** from
  `GET /api/v1/me` (display-only mirror of API authority).
- **`shared/ui` (new):** the `<StatusChip>` presentational component and a `contrastRatio`
  utility.
- **`app` — routing:** a dev-only `/style` gallery route, registered only under
  `import.meta.env.DEV`.
- **Tooling:** an ESLint `no-restricted-syntax` hex-colour rule; `@axe-core/playwright` wired into
  the e2e run.
- **New dependencies:** `@mui/icons-material`, `@fontsource/ibm-plex-sans`,
  `@fontsource/ibm-plex-sans-arabic`, `@axe-core/playwright`. **Not** `@mui/x-tree-view` (Phase 1)
  or `@mui/x-data-grid` (Phase 7).

**Theming architecture — the two axes** (this is the load-bearing decision):

- **Colour scheme (light ⇄ dark)** — `cssVariables: true` + `colorSchemes`; toggling flips the
  `data-mui-color-scheme` attribute on `<html>`, a CSS-variable swap with **no theme recreation
  and no React re-render**. Managed by MUI's `useColorScheme` with `defaultMode: 'system'`
  (first visit follows the OS), persisted to `localStorage`. An `InitColorSchemeScript` in the
  HTML entry sets the attribute before first paint → no flash.
- **Direction + font (EN/LTR ⇄ AR/RTL)** — recreate the theme + swap the Emotion cache (the
  Phase-0 dual-cache + `stylis-plugin-rtl` mechanism), because `stylis-plugin-rtl` rewrites CSS at
  injection time and `fontFamily` is a theme value — neither can be a variable flip. The factory
  is memoised on **language only** and always bakes in *both* colour schemes; dark selection
  survives a language switch because it lives in the DOM attribute + `localStorage`, not the theme
  object.
- Use `<CssBaseline enableColorScheme />`.

**Tokens.** All design values live as MUI-agnostic typed data in three tiers — *primitive* (raw
ramps: brand red 50–900, ink neutrals, slate secondary, semantic hues; plus raw type/space/radius/
shadow/motion values) → *semantic* (per-scheme role tokens: `surface`, `onSurface`, `primary`,
`secondary`, `error`, `focusRing`, status colours) → *component* (minimal, only where a component
needs what semantic can't express). Components read tiers 2–3 only. Palette values per
design-system.md §2/§3.

**StatusChip contract.** Presentational, in `shared/ui`; encodes the fill/outline rules, **not**
status names. Props (a decision, not a prototype):

```
tone: 'success' | 'warning' | 'error' | 'info' | 'neutral' | 'secondary'
variant: 'filled' | 'filled-soft' | 'outlined'
label: string      // always rendered — colour is never the only signal
icon?: ReactNode   // optional leading icon
```

Each later phase colocates its own `status → { tone, variant, icon, label }` map (a `feature` →
`shared` import). The `/style` gallery renders the full design-system.md §10 catalogue as **static
demo data**; it ships no authoritative domain map.

**App-shell scope.** Chrome built now (backed by data that already exists via `GET /me`). **Global
search and the notifications bell are deferred** to the phases that add their endpoints (they need
data that does not exist; the DoD forbids new backend logic). The side nav is built with the
destinations that exist today (dashboard, dev-only `/style`); each later phase registers its own
items.

**Fonts.** Import only the used subsets (`ibm-plex-sans` → latin, `ibm-plex-sans-arabic` →
arabic) at weights 400/500/600/700 via per-subset `@fontsource` CSS; `font-display: swap`;
**preload** the two hot weights (400 body + 600 heading) of the active language.

**Logo on dark.** White-chip approach, **no new assets** — render the existing logo inside a light
rounded chip so the black form never touches a dark surface. (Asset cleanups flagged as
non-blocking: the current `favicon.svg` is a purple non-brand placeholder; `icons.svg` is a
leftover social-icon sprite; a transparent/knockout logo is a later win.)

## Testing Decisions

**What makes a good test here:** assert **external behaviour**, never implementation detail. For
components, assert what the user sees — visible text, ARIA roles/labels, DOM attributes — not
internal class names, token wiring, or MUI internals (mirror the existing `RequireAuth.test.tsx`).
For the theme, assert **observable outcomes** (a computed colour, an attribute flip), not that a
particular token fed a particular slot. For contrast, assert the ratio **math on token values**.

Three seams, each at the highest useful level for what it verifies (confirmed with the developer):

1. **Playwright e2e — `/style` gallery + the re-skinned login/dashboard (primary, highest seam).**
   One spec drives all four combos (light/dark × EN/AR) in a real browser and asserts:
   `data-mui-color-scheme` flips on the dark toggle; `<html dir>` and `lang` flip on the language
   switch; a token-driven **computed colour** is correct (e.g. the primary button background is
   `rgb(228, 0, 43)` in light); StatusChip variants and the gallery sections render; **zero
   console errors**; **`@axe-core/playwright`** reports no serious/critical violations (axe reads
   computed CSS colours, so it is cross-OS-deterministic). It also **captures a per-combo
   screenshot as a CI artifact** for human eyeballing — **not** pixel-asserted (dev is Windows,
   CI is Linux; screenshot baselines would flake). *Prior art:* `e2e/headline.spec.ts` (drives the
   app through the Vite proxy, asserts `html[dir]` flips + localized text).

2. **Vitest unit — the contrast utility + token pairs.** A pure `contrastRatio(fg, bg)` function
   and assertions that every critical semantic pair (light **and** dark) meets AA (≥ 4.5 body /
   ≥ 3 large & UI), including the load-bearing rule that **`#E4002B` fails as body text (≈ 4:1) and
   `#B00020` passes** — the assertion that documents *why* the darkened brand-red text token
   exists. This tests the design **tokens as data**, which the browser seam cannot assert (axe
   only checks what is rendered, and cannot assert that a colour *must fail*). *Prior art:* none
   exactly — this is the first pure-logic frontend unit; Vitest is already configured (jsdom,
   `src/test/setup.ts`).

3. **Vitest + RTL component — `<StatusChip>`.** The one non-trivial new component (the DoD
   requires a Vitest test for it). Assert external behaviour: the **label text always renders**
   (colour is never the only signal), the requested tone/variant is applied to an observable
   attribute/role, and a provided icon is present. Do not assert internal class names. *Prior
   art:* `RequireAuth.test.tsx`.

No Pest / backend / API feature tests — there is no backend change. The ESLint hex-colour rule
participates in `npm run gate` and effectively acts as a fourth, static gate.

## Out of Scope

- **Any backend or domain logic** — no new endpoints, migrations, seeders, or Pest tests.
- **Dark-mode server persistence** via `PATCH /api/v1/me` — v1 is client-side `localStorage` only
  (mirrors how locale is persisted, but that wiring is deferred).
- **Global search** and the **notifications bell** — deferred to the phases that introduce their
  data.
- **`@mui/x-tree-view`** (arrives with Phase 1 catalog) and **`@mui/x-data-grid`** (Phase 7
  reports) — not installed now.
- **New brand assets** — a transparent / white-knockout logo and a real E-JUST favicon are flagged
  as cleanups, not produced here (no vector source available).
- **Screenshot pixel-regression** — the visual smoke captures artifacts only; it does not assert
  pixels (cross-OS flake).
- **Data-visualisation palette** implementation — Phase 7; design-system.md §14 documents it only.
- **Eastern-Arabic numerals** — Western digits are the chosen default for academic records.
- A **fully-populated role-aware nav** — only the destinations that exist today (dashboard,
  `/style`) are wired; each later phase adds its own items.

## Further Notes

- **Domain language unchanged.** `CONTEXT.md` gets no new terms — StatusChip, tokens, and shell
  are UI/implementation concepts, not PRS ubiquitous language (consistent with ADR-0009). Existing
  domain vocabulary (Student, Applicant, Permission, Course Status, Academic Standing, etc.) is
  used throughout where the design references statuses.
- **Reference docs:** `docs/design-system.md` v1.1 (esp. **§3.5 two-axes architecture**, §5 shell,
  §10 StatusChip ownership, §12 the a11y gate) and **ADR-0009**. No new ADR — the theming
  architecture decision was folded into design-system.md by choice.
- **Dependency:** sits after the Phase 0 manual review gate and before Phase 1 (whose catalog
  TreeView is the first thing built on this system).
- **Demo (definition of done):** toggle light↔dark and EN↔AR on the re-skinned dashboard; open
  `/style` and see the whole system render correctly in all four combinations, with gates green
  and WCAG AA holding across the full light/dark × LTR/RTL matrix.
