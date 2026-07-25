# A Grade Sheet is a submission round per Offering; Finalized is immutable, so resolutions and corrections are new rounds

**Status:** accepted — the correction path is a deliberate extension of SRS §4.7

SRS §4.7.1 gives the grading workflow in two lines: the Instructor "SHALL be able to
sign/finalize grades", and "after instructor signing, grades SHALL be forwarded to the
Department Head for review". It names no entity, no states, and — critically — **no way to
change a grade once it is final**. Three questions had to be settled: **what unit is signed
and reviewed**, **how an Incomplete is resolved months later**, and **what happens when a
mark was simply mistyped**.

**The unit is a Grade Sheet: one submission round for one Course Offering.** The state
machine lives on the Sheet, not on individual Grades: `Draft → Submitted → Finalized`, or
`Submitted → Returned → Draft` when the Department Head sends it back with a note. An
illegal transition is a **409**, as the Admissions status machine already does. This mirrors
Phase 3's `RegistrationRequest` + lines directly, and it matches "forwarded to the
Department Head" as the single act the SRS describes. Only gradable Enrollments become
lines — Audit and Withdrawal Enrollments appear on the roster but are never graded. Sheets
are created **lazily**, when an Instructor or Student Affairs first opens one.

**A Sheet is a *round*, not a row per Offering.** This is the load-bearing part. An Offering
accumulates Sheets: one `Initial` round covering every gradable Enrollment, then a
follow-up round for each batch of `IncompleteResolution`s or `Correction`s, each containing
only the affected lines. That single generalisation answers both hard questions at once and
adds no new state machine — only a kind, and a non-unique Offering reference.

**Finalized is immutable.** Nothing reopens a Finalized Sheet. The alternative — sending it
back to `Draft` — would un-finalize every grade on it, so a whole class's grades would
vanish from their records while one was being fixed, and "finalized" would stop meaning
anything. Rounds exist precisely so that later changes never disturb settled grades.

**Corrections are an extension, made on purpose.** The SRS has no correction and no appeal
process, so building one is inventing scope. It is built anyway because the alternative is
worse: with a single immutable round and no correction path, a mistyped mark is permanent.
The extension is held tight — an Instructor opens the round (they authored the mark), the
Department Head finalizes it (two-party control is preserved), the write is audited, and
there is **no student-initiated appeal surface**. The new Grade supersedes the old on the
same Enrollment while the old one stays visible as history, so the record shows what changed
rather than pretending it never happened.

**Supersession is derived, never stored.** The current Grade for an Enrollment is the one on
its latest Finalized Sheet. This keeps the project's derive-don't-duplicate rule and is
pleasingly symmetric with the CGPA's other supersession rule — the latest Attempt per
Course — so the read model applies the same "latest wins" shape twice.

**Every grade is reviewed, including the late ones.** Because a resolution or correction is
an ordinary round, it travels the same signature and Department-Head review as an initial
grade. Nothing enters the academic record on one signature. `grades.finalize` — which the
§3.2 matrix gives to **both** Instructor and Department Head — is read as exactly that
two-party control, and the matrix stays untouched: Instructor authority over a Sheet comes
from the Offering assignment, Department-Head authority from the Department assignment,
continuing the authorize-by-assignment discipline of ADR-0019 and Phase 2's interviewers.

## Considered options

- **Sheet per Offering as a submission round (chosen).** One state machine serves initial
  grades, Incomplete resolutions and corrections; two-party control holds for every grade;
  Finalized stays meaningful. Costs a non-unique Offering reference and a `kind`.
- **Per-Grade state machine (rejected).** Each Grade carries its own states and the
  Department Head works a queue of individual grades. Partial entry is natural and one slow
  student never blocks a course. Rejected because the Head then reviews N times per course
  with no view of its distribution, "sign the course" becomes a loop the API must express
  anyway, and it diverges from the Phase-3 batch precedent.
- **Batch submit with per-line review decisions (rejected).** The Head finalizes most lines
  and returns specific ones, mirroring the Phase-3 advisor's line-item trim. Most faithful
  to how a Head would really work one suspicious grade, but it needs a two-level state
  machine with partial-finalization states — materially the most complex option available.
- **Reopen the Finalized Sheet for resolutions and corrections (rejected).** One Sheet per
  Offering, trivially queryable, one transition. Rejected because it makes a Finalized Sheet
  mutable and puts a whole class's settled grades back into an editable document months
  later.
- **Direct Incomplete resolution with no second review (rejected).** Fewest steps, and
  defensible since the Sheet was already reviewed and the Incomplete already justified.
  Rejected because it leaves one class of grade escaping Department-Head review entirely —
  the exact hole a mistaken or rogue post-facto mark would go through.
- **No correction path at all (rejected).** The SRS-faithful answer, and it keeps the phase
  smaller. Rejected because a mistyped mark would be permanent.

## Consequences

- `grade_sheets` carries an **opaque** `course_offering_id` with no foreign key (ADR-0012 —
  Offerings are Enrollment-owned), a kind, a status, signer and reviewer with timestamps,
  and the review note. It is deliberately **not** unique per Offering.
- No `superseded` column exists on `grades`; a Grade's currency is a function of its Sheet's
  status and recency. Any future query that wants "the current grade" must go through the
  same accessor the read model uses.
- A resolution or correction lands the Grade on the **original** Enrollment, so it counts in
  that Enrollment's original Semester and a past semester's GPA changes retroactively. This
  is correct, and it is only safe because GPA is computed on demand
  ([ADR-0020](0020-grading-owns-gpa-read-model.md)) — a cached GPA would need explicit
  invalidation here.
- Because Sheets route to a Department Head via the Offering's Course's Department, and
  `Course.department_id` is nullable ([ADR-0010](0010-catalog-curriculum-model.md) — NULL is
  the shared university Preparatory courses), the Department-Head assignment's department
  reference is nullable too. A NULL Course matches the NULL assignment, so Preparatory
  Sheets route with no special case.
- Student Affairs holds `grades.enter` but not `grades.finalize`, which falls out of this
  design as exactly the right surface: SA may key marks into any Draft Sheet (audited) and
  can never sign one.
- Phase 6's transcript renders the grade column from the current Grade, or from the Course
  Status when there is no Grade — an Audit, Withdrawal, Incomplete or Fail-Withdrawal
  Enrollment has no Grade row at all.
