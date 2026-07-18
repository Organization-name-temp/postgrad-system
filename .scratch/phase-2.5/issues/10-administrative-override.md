# 10 — Administrative override (unscope queues + evaluation override)

**What to build:** A System/Super Admin (R09/R10) can oversee the whole pipeline through the existing screens. They reach every application across all faculties, cycles, and statuses (bypassing the Dean faculty-scope), and may record or override any interview's evaluation (bypassing the assigned-interviewer row-level check). Admin writes are attributed to the admin and recorded in the Audit Log; ordinary reviewers stay scoped. Per [ADR-0015](../../../docs/adr/0015-administrative-override-of-scope-guards.md).

**Blocked by:** Phase 2 review gate merged (tickets 01–04). *(Workstream 2; no enhancement-ticket blocker — frontier once the gate merges.)*

**Status:** ready-for-agent

- [ ] A single `isAdmin` (R09/R10) bypass is applied **consistently** at every faculty-scope and row-level guard in Admissions; a scoped Dean still gets 403 out-of-faculty.
- [ ] An admin lists and opens applications outside any Dean's faculty (200 where a scoped Dean gets 403).
- [ ] An admin records / overrides an evaluation for an interview not assigned to them (200 + an Audit Log entry with the admin as actor); the assigned interviewer's own scoring is unchanged and stays un-audited.
- [ ] Admins reach the evaluation write-path through the (now unscoped) interview screens.
- [ ] Pest feature tests cover the scope bypass (200 vs 403), the evaluation override + audit row, and that ordinary reviewers stay scoped. Gates green.
