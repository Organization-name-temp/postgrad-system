# 07 — Grade-gated registration rules

**What to build:** The three registration rules Phase 3 built as far as it could and then
documented as Phase-4 hooks, now that grades exist.

Today a Student who **passed** a Course can silently re-register for it as a "Retake",
`CourseStatus::CgpaImprovement` is unreachable, and a Student who failed their external
elective is locked out of taking a different one by the lifetime cap — so FR-REG-006.4 does
not work. This ticket closes all of that:

- A second for-credit Attempt at a Course routes to **Retake** if the first earned no credit,
  or **CGPA Improvement** if it did. Two for-credit Attempts is the lifetime cap for any
  Course — FR-REG-006.1's "maximum once" is binding, and §4.4.5's separate "max 1 Retake" and
  "max 1 CGPA Improve" notes describe that same single allowance.
- A Student may not register for a Course whose earlier Attempt is an unresolved Incomplete.
- A **failed** external elective releases the lifetime external-elective allowance, so
  FR-REG-006.4's "register for a different elective" works.
- A finalized grade below C+ on a Preparatory or Core Course raises a persistent
  **must-retake marker** on the Student's schedule and eligible-course list. It is
  **flag-and-notify, never a block** — the obligation is genuinely enforced downstream by
  Phase 5's Remaining CH and the 3.0 graduation CGPA, and this matches the house style
  throughout: audit-at-risk, SLA aging and retake derivation all inform rather than coerce.

All of this stays **inside Enrollment**, reading the `earned` boolean ticket 03 pushes, so the
cart rules remain pure functions with no Grading dependency and no test double
([ADR-0022](../../../docs/adr/0022-grade-outcome-pushed-to-enrollment.md)). Note that
must-retake needs only `earned` and the classification snapshot, both of which Enrollment
already owns.

**Prefactor first:** rename `RetakeDeriver` (and its unit test) to `AttemptDeriver`, matching
the `Attempt` term now in CONTEXT.md and the fact that it will route CGPA Improvement too.
Blast radius is a handful of call sites and one test file — do the rename green, then add the
behaviour.

**Blocked by:** 03 (Sign, Department-Head review, finalize), 05 (Incomplete lifecycle).

**Status:** ready-for-agent

- [ ] `RetakeDeriver` and its unit test are renamed to `AttemptDeriver` as a separate, green step before any behaviour changes.
- [ ] A second for-credit Attempt derives `Retake` when the prior Attempt did not earn its credit, and `CgpaImprovement` when it did. Unit-tested directly over the pure deriver, with no HTTP stack or database.
- [ ] `CgpaImprovement` consumes the same single allowance as a Retake, so a **third** for-credit Attempt at any Course is refused whatever the statuses involved. Its own test.
- [ ] Audit and Withdrawal attempts still consume nothing — a Student who audited or withdrew re-registers as a fresh Regular, exactly as Phase 3 shipped. A regression test keeps this true.
- [ ] Registering for a Course whose earlier Attempt is an unresolved Incomplete is refused with a clear bilingual message; once resolved, registration behaves per the rules above.
- [ ] The lifetime external-elective cap counts only External Elective Enrollments that did not fail, so a Student who failed theirs can register a different elective. A test walks the FR-REG-006.4 scenario end to end and asserts both grades count in the CGPA.
- [ ] A finalized grade below C+ on a Preparatory or Core Enrollment produces a persistent must-retake marker on the Student's schedule and eligible-course list, and a notification. A grade below C+ on an elective produces no marker — the SRS obligation is prep and core only.
- [ ] Registration is **never** blocked for an outstanding must-retake obligation, and a test asserts a cart omitting one still submits successfully.
- [ ] The marker is derived from data Enrollment already owns; no read of `Grading\Public` is introduced, and deptrac gains **no** `Enrollment → Grading` edge. A reviewer should treat such an edge as a regression of ADR-0022.
- [ ] The existing cart-rule and course-status unit tests are extended rather than duplicated, and all Phase-3 registration tests still pass.
- [ ] Frontend: the must-retake marker appears on My Schedule and in the eligible-course list as a `<StatusChip>` with a colocated map, bilingual, colour never the only signal.
- [ ] `openapi.yaml` and the Orval client regenerated if any response shape changed, both drift checks clean.
- [ ] `composer gate` and `npm run gate` both green; affected screens pass axe with no serious or critical violations.
