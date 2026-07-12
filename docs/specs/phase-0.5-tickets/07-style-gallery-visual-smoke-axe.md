# 07 — /style gallery + hybrid visual smoke + axe

**What to build:** A dev-only reference page renders the whole system — palette, type scale, the
full status-chip catalogue (as static demo data), and key components — across all four light/dark ×
EN/AR combinations, and it never ships to production. The phase's headline verification is a
Playwright smoke that drives the gallery (and the re-skinned screens) through the four combos,
asserting the system actually wired up, running axe for accessibility, and capturing per-combo
screenshots as artifacts for human review (not pixel-asserted, since dev is Windows and CI is
Linux).

**Blocked by:** 01 — Token-driven theme foundation · 02 — Self-hosted bilingual fonts · 03 —
App-shell chrome · 04 — StatusChip · 06 — Re-skin + no-raw-hex gate.

**Status:** ready-for-agent

- [ ] A `/style` route renders palette, type scale, the §10 status-chip catalogue, and key components; it exists only in dev builds (`import.meta.env.DEV`) and is absent from production.
- [ ] A Playwright smoke drives all four combos and asserts: the colour-scheme attribute flips on the toggle, `html` dir/lang flip on language switch, a token-driven computed colour is correct, and the status-chip variants render — with zero console errors.
- [ ] `@axe-core/playwright` runs on `/style` and the re-skinned login/dashboard and reports no serious/critical violations across the combos.
- [ ] A per-combo screenshot is captured as a CI artifact (not pixel-asserted).
- [ ] Gates green.
