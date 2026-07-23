# 10 — Administrative override across scope guards + audit

**What to build:** [ADR-0015](../../../docs/adr/0015-administrative-override-of-scope-guards.md) applied to every scope guard Phase 3 introduced. A System / Super Administrator (R09/R10) can read all queues, schedules, and registers without being an advisor or the assigned instructor, and can perform scope-guarded writes — but those writes leave an Audit Log entry, so override is possible and accountable. Cross-cutting hardening, done once every guard exists (the Phase 2.5 override precedent).

**Blocked by:** 06, 07, 08, 09 (every scope guard to harden must exist first).

**Status:** ready-for-agent

- [ ] Reuse `Identity\Public::isAdministrator(userId)` behind an Enrollment-side concern (the Phase 2.5 `IdentifiesAdministrator` pattern), applied consistently at every Phase 3 scope guard: the advisor request queue + decision (advisee scope), register-for-student (advisee scope), My Schedule / withdrawal (student-self scope), and the attendance register (instructor-assignment scope).
- [ ] Admin **reads** are unscoped and un-audited (queues, requests, any student's schedule, any offering's register).
- [ ] Admin **writes** at a scope guard succeed and write an **Audit Log** row (actor = admin, a descriptive action) — deciding a request on behalf, recording/correcting attendance on an offering they don't teach. A legitimately-scoped actor (the real advisor / assigned instructor) stays **un-audited**. No API shape change beyond what override needs.
- [ ] Nav: administrators reach the oversight surfaces (the Phase 2.5 role-gated nav approach — admins get the relevant enrollment/instructor items).
- [ ] **Tests:** Pest API-feature (a dedicated override suite, self-contained helpers so single-file runs work) — an admin reads across scopes without a row, an admin write at each guard succeeds **and** writes the expected Audit Log row, the scoped actor's equivalent write writes **no** audit row, and a non-admin out-of-scope write is still 403. Gates green.
