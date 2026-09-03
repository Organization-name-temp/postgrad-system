# 05 — Chart primitive + View as table

**What to build:** The first chart this system has ever rendered, and the rules every later one
inherits. A developer opens the `/style` gallery and sees bar, line and pie charts drawn from
`design-system.md` §14's palette, rendering correctly in **light and dark** and in **English and
Arabic with the layout flipped** — and beside each one, a "View as table" control that reveals the
identical series as a real table with scoped headers.

Phase 4.5 rejected charts because "no chart tokens exist, and RTL + dark + axe would all need
inventing for roughly one sparkline." Half of that is already false — §14 has carried a
colourblind-aware categorical palette, a sequential ramp and the never-colour-alone rule since
Phase 0.5, unused. This ticket answers the other half rather than waiving it.

- **`@mui/x-charts`**, the same MUI X family as the installed tree view, so it consumes the
  existing theme and CSS variables directly: light/dark flips for free with no token bridging, and
  RTL rides the same `direction` mechanism the theme provider already flips. It renders SVG, so axe
  can see it. **Verify the version aligns with the installed MUI X major before assuming it.**
- **`<Chart>` and `<ChartDataTable>` live in `shared/ui`** and encode the rules and **no domain
  names** — the `<StatusChip>` and `<DashboardCard>` discipline. Series colours come from §14 in
  §14's order; a neutral series never starts on brand red, because red implies "bad".
- **Accessibility is met in substance, not in form.** A chart is `role="img"` with a concise label,
  and its "View as table" disclosure renders the same series it was handed. An `aria-label`
  paraphrase would pass the automated gate while conveying nothing, and this is the one surface
  where conveying the data *is* the point. The table also serves low-vision and keyboard users who
  want exact values rather than estimates from bars.
- **Vitest asserts the table's cells equal the series the chart was given**, so the two cannot
  drift. That assertion is the reason the affordance stays honest as charts change.
- **Colour is never the only signal** — axis and legend text always present, per §14.

**Blocked by:** None — can start immediately in parallel with ticket 01; ticket 04 follows 01.

**Status:** ready-for-agent

- [ ] `@mui/x-charts` added at a version aligned with the installed MUI X major, verified rather
      than assumed
- [ ] `<Chart>` and `<ChartDataTable>` live in `shared/ui` and contain no domain names
- [ ] Series colours are read from `design-system.md` §14's categorical palette in its documented
      order; no raw hex survives the ESLint hex rule
- [ ] Charts render correctly in light and dark, and in EN and AR with the layout flipped
- [ ] Every chart is `role="img"` with a concise label and a working "View as table" disclosure
- [ ] Vitest asserts the rendered table's cells equal the series passed to the chart
- [ ] Every drillable table row exposes a keyboard-operable link equivalent to its chart segment;
      pointer interaction is never the only drill-through path
- [ ] Axis and legend text are always present, so colour is never the only signal
- [ ] The `/style` gallery renders bar, line and pie across light/dark × EN/AR as living reference
- [ ] `@axe-core/playwright` passes on `/style` in all four combinations
- [ ] `npm run gate` green
