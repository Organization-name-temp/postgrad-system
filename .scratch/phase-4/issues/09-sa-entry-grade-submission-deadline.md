# 09 — Student Affairs entry + grade-submission deadline

**What to build:** Two administrative surfaces around grading.

First, central data entry. Student Affairs holds `grades.enter` in the SRS §3.2 matrix but
**not** `grades.finalize`, and read literally that means exactly one thing: SA can key marks
into any Draft Grade Sheet — the paper-grade-sheet path a registrar's office really needs —
and can never sign one. The Instructor stays the author of their own grades. Every SA write to
someone else's Sheet is audited. No new permission and no override: the matrix is used as
written.

Second, the deadline. A Semester gains its grade-submission deadline alongside its other
period dates, and a manually-run command flags Offerings past it with no finalized Sheet,
notifying the Instructor and the Department Head. It **changes nothing** — the Phase-2
SLA-aging pattern. A hard gate was considered and rejected: an Instructor locked out of
submitting cannot deliver grades at all, which stalls students' records behind an admin ticket,
and no SRS criterion asks for it.

[ADR-0018](../../../docs/adr/0018-calendar-module-semester.md) promised this column in Phase 4.
Exam-period and holiday columns stay deferred — Phase 4 gives them no consumer either, and that
ADR forbids anticipatory schema.

**Blocked by:** 03 (Sign, Department-Head review, finalize).

**Status:** ready-for-agent

### Student Affairs entry

- [ ] A user holding `grades.enter` but not `grades.finalize` can open any Draft Grade Sheet — not only Offerings they teach — and enter or edit its marks, and declare an Incomplete on a line.
- [ ] The same user is refused the sign action with a 403, because signing is gated on `grades.finalize`. A test asserts both halves of this in one place, so the distinction cannot silently erode.
- [ ] Each SA write to a Sheet whose Instructor is someone else records an Identity audit entry. An Instructor writing their own Sheet is **not** audited — the Phase-3 precedent, where the legitimately-scoped party's own action stays clean.
- [ ] SA reaches Sheets through an Offering picker over all Offerings, reusing the Sheet editor rather than a second screen.
- [ ] A Department Head still cannot enter marks — this ticket must not widen write access as a side effect.

### Grade-submission deadline

- [ ] Semester gains exactly **one** new date, the grade-submission deadline, joining the existing order-validated invariant so it must fall on or after the Semester's end. Out-of-order dates are rejected with a bilingual 422, and the existing ordering tests are extended rather than replaced.
- [ ] The field appears on the existing Semester admin screen for Student Affairs, alongside the other period dates.
- [ ] The Semester read facade exposes the new date to its consumers.
- [ ] A manually-run command finds Offerings whose Semester's deadline has passed and which have no Finalized Sheet, and notifies the Instructor and the routed Department Head. It changes no state whatsoever, and a test asserts nothing moved.
- [ ] An Offering with a Finalized Sheet, and one whose Semester deadline has not passed, are both left alone. Tests for each.
- [ ] The command is driven in tests by invoking it and asserting Notification rows through the notifications endpoint — never by reaching into command internals.
- [ ] Bilingual EN + AR for the new notification type and the new field label.
- [ ] Scribe `@urlParam` examples on new model-bound routes; `openapi.yaml` and the Orval client regenerated, both drift checks clean.
- [ ] `composer gate` and `npm run gate` both green; affected screens pass axe with no serious or critical violations.
