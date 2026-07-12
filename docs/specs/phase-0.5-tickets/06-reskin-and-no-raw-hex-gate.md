# 06 — Re-skin existing screens + no-raw-hex ESLint gate

**What to build:** The existing login and dashboard screens are moved fully onto the token theme so
the whole app looks coherent and branded, and a lint rule makes "no hard-coded colours" enforceable
rather than aspirational — a raw hex colour literal outside the token source fails linting.

**Blocked by:** 01 — Token-driven theme foundation · 02 — Self-hosted bilingual fonts · 03 —
App-shell chrome (re-skin lands on the final type and inside the shell).

**Status:** ready-for-agent

- [ ] Login and dashboard render entirely from theme tokens — no raw hex or ad-hoc colour values remain in screen/component code.
- [ ] An ESLint rule fails the lint gate on any hex-colour literal outside the tokens module/adapter; a hex added to a component is caught.
- [ ] The re-skinned screens hold up across the full light/dark × LTR/RTL matrix.
- [ ] Gates green, including the new rule.
