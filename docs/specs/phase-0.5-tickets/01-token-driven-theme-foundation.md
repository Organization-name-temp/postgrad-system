# 01 — Token-driven theme foundation

**What to build:** The whole app renders on a single, token-driven MUI theme instead of MUI
defaults. A tokens module holds every design value — colour ramps + per-scheme semantic roles,
the type scale, spacing, radius, shadow, and motion — as framework-neutral typed data; a thin
`createAppTheme(direction, lang)` adapter turns it into the MUI theme with CSS variables and both
light + dark colour schemes. Light↔dark becomes an instant attribute flip (no reload, no flash: a
pre-paint init script sets the scheme, and a first-ever visit follows the OS preference), persisted
locally. EN↔AR still flips direction (theme recreation + Emotion cache swap) and the dark choice
survives the switch. A theme toggle sits in the current app bar. Buttons render normal-case, a
visible focus ring is present, and reduced motion is honoured. The existing login and dashboard
keep working.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] Every design value (colour, type, spacing, radius, shadow, motion) is read from the tokens module through the theme; components read semantic/component roles, never primitives directly.
- [ ] Toggling the app-bar control flips light↔dark instantly, with no page reload and no flash on refresh; the choice persists across visits; a first-ever visit follows the OS `prefers-color-scheme`.
- [ ] Switching EN↔AR flips text direction and mirrors layout, and does **not** reset the current light/dark choice.
- [ ] Buttons render in normal case (no ALL-CAPS); every interactive element shows a visible focus ring on keyboard focus; `prefers-reduced-motion` drops non-essential motion.
- [ ] cssVariables mode is on (MUI emits `--mui-palette-*`) and both light + dark schemes live in one theme; native form controls/scrollbars track the scheme.
- [ ] All existing gates stay green (lint + boundaries, format, `tsc`, Vitest + RTL, Playwright).
