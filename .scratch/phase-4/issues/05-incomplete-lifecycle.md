# 05 — Incomplete lifecycle: declare, resolve, remind, convert

**What to build:** The whole of SRS §4.7.3, end to end. An Instructor with a Student who has
done most of the coursework but cannot finish defers the grade instead of inventing one or
leaving the roster blank forever: they mark the line Incomplete, stating the percentage of
coursework completed and a reason. The Student sees the Incomplete on their record with its
resolution deadline, and is reminded as that deadline approaches. If the Student finishes,
the Instructor resolves it by entering the outstanding mark, which lands in the Semester the
course was actually taken. If nobody resolves it, it becomes a Fail Withdrawal.

Three things here are easy to get wrong and are settled decisions:

- The ≥70% rule is an Instructor **attestation**, not a measurement. It is explicitly *not*
  attendance — [ADR-0019](../../../docs/adr/0019-attendance-session-register.md) already ruled
  that §4.7.3.1 is about coursework completion.
- The deadline is **derived and never stored**: the start date of the Semester following the
  Enrollment's, plus 63 days (week 1 begins on the start date, so week 10 begins on day 63).
  Consistent with [ADR-0018](../../../docs/adr/0018-calendar-module-semester.md)'s rule that
  period state is always derived.
- Resolution is a **follow-up Grade Sheet round**, because a Finalized Sheet is never
  reopened ([ADR-0023](../../../docs/adr/0023-grade-sheet-submission-rounds.md)). It therefore
  goes through the same signature and Department-Head review as any other grade — no grade in
  the system enters the record on one signature.

An Incomplete becoming a Fail Withdrawal is the **only** way an Enrollment becomes one: there
is no administrative late-withdrawal path and no Instructor-assigned FW.

This is the largest slice in the phase. A sensible internal order is declare → resolve →
the two commands.

**Blocked by:** 03 (Sign, Department-Head review, finalize), 04 (GPA/CGPA engine + My Grades).

**Status:** ready-for-agent

### Declaring

- [ ] An Instructor can mark a Sheet line Incomplete instead of entering a mark, supplying a completion percentage and a reason; both persist and are visible to the Department Head reviewing the Sheet.
- [ ] A completion figure below the configured threshold (`70`, in the new grading config alongside the other Appendix-C tunables) is refused with a bilingual 422. The threshold is config-driven, not a literal.
- [ ] On finalization, the Enrollment's Course Status becomes Incomplete via the `Enrollment\Public` command, in the same transaction. Enrollment **privately** remembers the status the Enrollment held before, so it is never exposed and `CourseStatus` stays internal to Enrollment.
- [ ] The Student is notified that an Incomplete was declared, with the reason and the deadline.
- [ ] While Incomplete, the Enrollment counts toward neither the CGPA nor earned credit but does count as attended — falling out of the existing counting matrix, asserted against the ticket-04 engine.

### The derived deadline

- [ ] The deadline is computed from the Calendar, never stored: next Semester's start plus 63 days. A unit test covers the arithmetic directly, including a Spring→Fall and a Fall→Spring pair.
- [ ] When the next Semester does not exist in the Calendar yet, the Incomplete simply has no deadline. Declaring one is **not** blocked for an admin's omission.
- [ ] Correcting a Semester's start date moves every outstanding deadline with it, because nothing was frozen. A test asserts this.

### Resolving

- [ ] An Instructor has a list of their outstanding Incompletes, each with its deadline (or a clear "no deadline yet" state).
- [ ] Resolving opens an `IncompleteResolution` Sheet round containing only those lines; entering the mark, signing, and Department-Head finalization all behave exactly as ticket 03 established.
- [ ] The resolving Grade attaches to the **original** Enrollment, so it counts in that Enrollment's original Semester and that Semester's GPA changes retroactively. A test asserts the past Semester's GPA moves.
- [ ] Resolution restores the Course Status the Enrollment held before it was Incomplete — a Retake comes back a Retake, not a Regular.

### The two commands

- [ ] A manually-run reminder command notifies the Student when the days remaining equal exactly one of the configured offsets (`28, 14, 7, 1`), so running it daily produces precisely §4.7.3.4's four reminders. It carries **no sent-marker** — the Phase-2 precedent that a hand-run reminder needs none — and a test asserts it stays silent on a non-offset day.
- [ ] A manually-run conversion command sets Fail Withdrawal on any Incomplete past its deadline, via the `Enrollment\Public` command, and notifies the Student. Tests cover the day before, the day of, and the day after.
- [ ] An Incomplete with no deadline (no next Semester) is **skipped and reported** by the conversion command — never converted, never a crash. Its own test.
- [ ] Both commands are driven in tests by invoking the command and asserting the resulting Notification rows through the notifications endpoint and the resulting state through the API — never by reaching into command internals. Prior art: the two Admissions commands.

### Across the ticket

- [ ] Frontend: the Instructor's outstanding-Incompletes list and the resolution flow; the Student's record shows an Incomplete with its deadline and, after conversion, the Fail Withdrawal, both as `<StatusChip>`s with a colocated map.
- [ ] Scribe `@urlParam` examples on new model-bound routes; `openapi.yaml` and the Orval client regenerated, both drift checks clean.
- [ ] `composer gate` and `npm run gate` both green; new screens pass axe with no serious or critical violations.
