# Grading pushes the earned outcome to Enrollment; the dependency stays one-way

**Status:** accepted

Once Grades exist, three of Enrollment's **registration-time** rules stop being decidable
inside Enrollment. Whether a second Attempt at a Course is a `Retake` or a
`CgpaImprovement` depends on whether the first one passed; whether a failed external
elective releases the lifetime external allowance (FR-REG-006.4) depends on the same fact;
and both are enforced in `CartValidator` and the attempt deriver, which are pure functions
over Enrollment's own rows. Meanwhile `Grading` already reads `Enrollment\Public` for
rosters and enrollments ([ADR-0020](0020-grading-owns-gpa-read-model.md),
[ADR-0017](0017-offering-based-registration.md)). So the naïve fix — have Enrollment read
`Grading\Public` back — makes the two modules **mutually dependent**.

**Decision: Grading pushes, Enrollment never pulls.** When a Grade Sheet is Finalized,
Grading calls an `Enrollment\Public` command with a single nullable boolean per Enrollment —
`earned`, meaning "this Attempt reached C+ and earned its credit hours" — in the same
transaction as the finalize. Enrollment stores it beside the `classification` and
`credit_hours` it already snapshots. The dependency graph stays a DAG: `Grading →
Enrollment`, never the reverse.

**This is a deliberate duplication, and it is the one in the design.** The pass/fail fact
lives in Grading as the Mark and Letter Grade, and again in Enrollment as one bit. That
cuts against this project's own "derive, don't duplicate" principle
([architecture.md](../architecture.md) §2, and the reason the Credit-Hour Summary, the
attendance percentage, semester openness and retake supersession are all computed rather
than stored). It is accepted here for a specific reason: those derived read models are read
*by their owner*, whereas this fact is read by a **different module's pure validators**.
Keeping them pure — no HTTP stack, no database, no Grading test double — is what makes
Phase 3's rules engine testable at all, and that is worth one byte of duplication.

**Why the duplication cannot drift.** `earned` is written only through the facade command,
only from a Sheet finalize, inside the finalize's transaction. A Correction round rewrites
it the same way. There is no other writer, so it cannot disagree with the Grade that
produced it — the usual failure mode of a cached flag (a second write path that forgets to
update it) does not exist.

**What is deliberately *not* pushed.** Not the Mark, not the Letter Grade, not the GPA
points, not the semester GPA. Enrollment learns exactly the one bit its own rules turn on
and nothing it might be tempted to display or recompute. Grading remains the sole authority
on grades, and every grade-shaped read in the UI goes to `Grading\Public`.

**Related but distinct:** Grading also drives Enrollment's `course_status` for Incomplete
and Fail-Withdrawal through the same command surface, following
[ADR-0013](0013-acceptance-handoff-student-anchor.md)'s synchronous-hand-off shape.
Enrollment stays the sole writer of its own column, and privately remembers the
pre-Incomplete status so resolution restores it — which is why `CourseStatus` can stay
`Enrollment\Internal` and needs no promotion to `Public`. The `GradeFinalized` domain event
carries **side-effects only** (notifications now, Phase-5 standing later); it never carries
a status write, because that write must be atomic with the finalize.

## Considered options

- **Grading pushes `earned` (chosen).** One-way dependency, pure validators, no cycle for
  deptrac to permit. Costs one duplicated bit and one new inbound command on
  `Enrollment\Public`.
- **Enrollment reads `Grading\Public` (rejected).** Nothing is duplicated and a corrected
  Grade is reflected instantly in every gate. Rejected because it creates a `Public↔Public`
  cycle between the two modules, every cart-rule unit test would need a Grading double, and
  a cycle is the standard signal that two modules are really one — which these are not.
- **Move the grade-dependent gates into Grading (rejected).** Also keeps the dependency
  one-way, and puts every grade-derived rule beside the grades. Rejected because it splits
  the registration rule set across two modules: `CartValidator` is deliberately the single
  place every registration rule is enforced, and half of it would no longer live where the
  cart does.

## Consequences

- `enrollments` gains a nullable `earned` boolean — `null` until graded, which is also the
  permanent value for Audit, Withdrawal and Fail-Withdrawal Enrollments, and the transient
  value for an outstanding Incomplete.
- `Enrollment\Public` ships with a **command** surface, not only reads: record outcome, mark
  incomplete, resolve incomplete, convert to Fail-Withdrawal. It is the second module after
  Identity to accept inbound writes across a seam.
- The external-elective cap counts only External Elective Enrollments whose `earned` is not
  `false`, so FR-REG-006.4's "failed an elective, take a different one" works without
  Enrollment knowing what a grade is.
- deptrac gains `Grading_Internal → Enrollment_Public` and **no** reverse edge; a future
  `Enrollment_Internal → Grading_Public` edge should be treated as a regression of this
  decision, not a convenience.
- If a Grade is ever corrected outside a Sheet finalize — a path deliberately not built —
  `earned` becomes the thing that must be updated with it. That is the single maintenance
  obligation this decision creates.
- Phase 5 uses the same pushed `earned` outcome to enforce availability-aware registration
  priority for outstanding Preparatory/Core Must-Retake Obligations
  ([ADR-0042](0042-must-retake-courses-have-registration-priority.md)). This adds a gate
  without adding an `Enrollment → Grading` dependency. The finalized Grade event also
  drives idempotent rejection of an in-review Transfer/Waiver request for the newly failed
  Course; the event remains for side effects rather than the atomic outcome write.
