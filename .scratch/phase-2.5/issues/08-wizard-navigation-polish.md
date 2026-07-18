# 08 — Wizard navigation polish

**What to build:** The applicant can jump directly between completed wizard steps via the stepper, and can edit any section from the Review step via per-section **Edit** links — the polished navigation deferred from the bug fixes (tickets 03/04).

**Blocked by:** 05 — React Hook Form + Zod foundation.

**Status:** ready-for-agent

- [ ] The stepper is clickable for reachable / completed steps, skipping the already-verified email step.
- [ ] The Review step has a per-section Edit action that jumps to that step and returns.
- [ ] Navigating away and back preserves entered values.
- [ ] Vitest covers stepper navigation and a Review Edit jump. Gates green.
