# 05 — Contrast gate (util + token-contrast unit test)

**What to build:** The design system's "contrast is load-bearing" promise becomes a real, automated
check. A small contrast-ratio utility plus a unit test assert that every critical semantic colour
pairing (in both light and dark) meets WCAG AA, and that the vivid brand red fails as body text
while the darkened brand-red text token passes — encoding exactly why that token exists.

**Blocked by:** 01 — Token-driven theme foundation (the test reads the tokens).

**Status:** ready-for-agent

- [ ] A unit test asserts each critical semantic pair (text-on-surface, on-primary, borders, status colours, focus ring) meets AA (≥ 4.5 body / ≥ 3 large & UI) in both light and dark.
- [ ] The test asserts `#E4002B` on white **fails** AA for body text (≈ 4:1), and the brand-red text token `#B00020` **passes**.
- [ ] A deliberately bad token pairing makes the test fail (the gate has teeth).
- [ ] The test runs inside the existing Vitest gate.
