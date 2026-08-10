# 03 — Sign, Department-Head review, finalize

**What to build:** The two-party grade loop the SRS §4.7.1 describes in two lines. An
Instructor signs a completed Grade Sheet as one act; the Sheet locks and appears in the
queue of the right Department Head, who either finalizes it — at which point its Grades
become part of the academic record — or returns it with a note so the Instructor can correct
and re-sign. A Department Head can never edit a mark themselves: authorship of a grade stays
with the Instructor who taught the course.

Routing is one rule with no special case: the Sheet's Offering's Course's Department, read
through `Catalog\Public`, matched against ticket 02's assignment — and because
`Course.department_id` is nullable for the shared Preparatory courses, a NULL Course matches
the NULL (university-wide) assignment.

Finalization is also where Grading pushes the `earned` boolean onto each Enrollment
([ADR-0022](../../../docs/adr/0022-grade-outcome-pushed-to-enrollment.md)) — one bit meaning
"this Attempt reached C+ and earned its credit hours" — inside the same transaction. Nothing
consumes it yet; ticket 07 does. `GradeFinalized` fires for side-effects only, never carrying
the write.

State machine and immutability rules:
[ADR-0023](../../../docs/adr/0023-grade-sheet-submission-rounds.md).

**Blocked by:** 01 (Grading module + Grade Sheet + mark entry), 02 (Department-Head assignment).

**Status:** ready-for-agent

- [ ] The Sheet moves `Draft → Submitted → Finalized`, and `Submitted → Returned → Draft`. Any other transition is a **409**, matching the Admissions status machine's precedent.
- [ ] An Instructor cannot sign a Sheet with any blank line; the response says how many remain. The UI shows the outstanding count before the action is attempted.
- [ ] A Submitted Sheet is read-only to everyone, including its Instructor — a mark under review cannot be quietly altered.
- [ ] Signing is gated on `grades.finalize`; the acting user must be the Offering's assigned Instructor. Authority is by assignment, not by a new permission — the §3.2 matrix is untouched.
- [ ] A Department Head sees a queue of Submitted Sheets for their own Department only; another Department's Sheets are absent and 403 on direct access.
- [ ] A Head assigned **university-wide** sees the Sheets of department-less Preparatory Offerings, and a department-scoped Head does not. This case has its own test.
- [ ] Finalizing makes the Grades part of the record; the Sheet is then permanently closed and every transition out of Finalized is a 409.
- [ ] Returning requires a note, which reaches the Instructor; the Sheet becomes editable and re-signable, and re-signing sends it back to the same queue.
- [ ] A Department Head attempting to write a mark gets a 403.
- [ ] Finalization writes `earned` for every graded line through the new `Enrollment\Public` command, in the same transaction as the finalize. Enrollment remains the sole writer of its own column. A facade test covers the command directly.
- [ ] `GradeFinalized` is published on finalize and carries side-effects only. Three notification types land: sheet-submitted to the Department Head, sheet-returned to the Instructor, and grade-finalized to each Student — the last carrying the letter and whether it is below C+, so no second type is needed for the must-retake warning.
- [ ] The Department Head's Sheet view shows the Offering's grade distribution alongside the marks, so an implausible spread is visible at a glance. This is a view, not a report — reporting is Phase 7.
- [ ] Frontend: the Instructor's Sheet list shows each Offering's state across semesters (unstarted, in progress, awaiting review, returned, finalized); the Department Head gets a review queue and a Sheet review screen with finalize and return-with-note.
- [ ] Pest feature tests cover every transition and every 409, the blank-line guard, the read-only Submitted Sheet, both routing cases including the university-wide one, the return-and-resign round trip, the Head's write 403, the `earned` write, and each notification row asserted through the notifications endpoint.
- [ ] Scribe `@urlParam` examples on new model-bound routes; `openapi.yaml` and the Orval client regenerated, both drift checks clean.
- [ ] `composer gate` and `npm run gate` both green; new screens pass axe with no serious or critical violations.
