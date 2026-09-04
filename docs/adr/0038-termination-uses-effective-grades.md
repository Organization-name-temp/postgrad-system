# The two-course termination trigger uses effective Grades

**Status:** accepted

The termination rule activates at Grade D or below in two or more Courses, while the
grading model allows one later Retake or CGPA Improvement to supersede an earlier Attempt.
Counting every historical failure forever would ignore successful remediation; counting
Attempts rather than Courses would let one Course satisfy a criterion explicitly written
in terms of two Courses.

**Decision: count distinct Courses whose current effective, non-superseded Grade is D or F.**
D+ does not qualify because it is above D. When a later effective Attempt reaches C+ or
higher, that Course leaves the trigger set. AcademicStanding obtains effective Grades from
`Grading\Public` and does not reimplement supersession.

Passing a different elective can satisfy a curriculum slot but does not supersede the
failed Course. Its D/F therefore remains in the trigger set; only a successful later
Attempt in that same Course clears it.

## Considered options

- **Effective Grades in distinct Courses (chosen).** Aligns termination with the current
  academic record and recognizes remediation. The derived trigger may clear while a
  Termination case is being reviewed.
- **Any historical D/F in distinct Courses (rejected).** Produces a stable historical
  trigger but makes successful Retakes irrelevant to termination eligibility.
- **Count Grade occurrences (rejected).** Treats repeated failure seriously but contradicts
  “two or more Courses” by allowing two Attempts of one Course to trigger termination.

## Consequences

- The trigger is derived and reversible. If every trigger clears before the Council's final
  decision, the open Case appends `Trigger Cleared` and closes automatically; a later
  trigger opens a new Case. A final Council termination is not reversed automatically.
- Tests cover D+ exclusion, D and F inclusion, two Attempts of one Course, two distinct
  Courses, a successful later Attempt clearing one Course, and a different passed elective
  satisfying progress without clearing the original Course's trigger evidence.
