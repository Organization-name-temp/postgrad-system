# Derived values become batch and aggregate methods on their owner's `Public` facade, and the arithmetic is duplicated into SQL under a parity test

**Status:** accepted

"Derived, never stored" is this project's house rule, and it is load-bearing: period state,
attendance percentage, Incomplete deadlines, grade supersession and GPA/CGPA are all recomputed on
read, nothing is cached ([ADR-0005](0005-localhost-learning-build.md) — no Redis), and so nothing
can drift. [ADR-0020](0020-grading-owns-gpa-read-model.md) put the GPA arithmetic in exactly one
place, `Grading`'s `GpaCalculator`, behind `GradingRecords`.

Phase 4.6 asks three things of those derived values that the current shape cannot give:

- **GPA Distribution** needs every Student's CGPA in a Program at once. `GradingRecords::cgpaFor()`
  takes **one** student id and crosses two module seams per call.
- **Filtering and sorting** on CGPA, attendance percentage, queue age or open seats needs the value
  available to `ORDER BY` and `WHERE`. You cannot paginate on a number that only exists after a PHP
  loop — the page you return would be a page of the wrong set.
- **Cards** want figures like "advisees below 3.0", which is the same problem in miniature.

**Decision: each owning module grows batch and aggregate methods on the `Public` facade it already
publishes — `cgpaForMany(array $studentIds)`, `cgpaDistributionFor(...)`, attendance and enrollment
equivalents — each implemented as a single SQL query using the window functions this project chose
Postgres for** (ROADMAP §1: "so the GPA/standing window-function math stays honest"). No migration,
no cache, no materialised view, and the seam is unchanged: consumers still ask the owner, exactly as
[ADR-0012](0012-cross-module-reads-by-id-and-dto.md) requires. It continues a pattern already set —
`GradingRecords::gradesFor(array $enrollmentIds)` is already a batch read.

## The cost, named

**The GPA arithmetic now exists twice**: once in PHP, in `GpaCalculator`, and once in SQL. That
cuts against ADR-0020's whole purpose, so it is worth being precise about why it is acceptable here
when a cache would not be.

A cache's failure mode is **drift in time**: a second write path forgets to invalidate, and the
stored value silently disagrees with the truth for as long as nobody looks. That failure mode does
not exist here. Both implementations read the same authoritative rows at the same instant and store
nothing, so they cannot disagree about *when*. They can only disagree about *what* — a difference in
code — and a difference in code is exactly what a test catches.

**So the duplication is accepted under a parity obligation.** A test runs both implementations over
the seeded cohort and asserts they agree, covering the three places the arithmetic is subtle:

- accumulation in **integer hundredths** and rounding **half-up to two decimals exactly once**,
  including a case that rounds *across* the 3.0 threshold ([ADR-0021](0021-grading-scale-uniform-bands.md));
- **supersession** — among a Course's Attempts only the chronologically latest by the Semester's
  `starts_at` counts, across a Fall→Spring boundary;
- the **`null` vs `0.00`** distinction for a Student with no CGPA-counting graded Enrollment.

This is the same shape of argument [ADR-0022](0022-grade-outcome-pushed-to-enrollment.md) made for
the duplicated `earned` bit: a deliberate duplication, accepted for a stated reason, with the
mechanism that stops it drifting spelled out.

## Considered options

- **Batch and aggregate facade methods (chosen).** Keeps derived-never-stored, keeps ADR-0005's
  no-cache, keeps ADR-0012's seam, needs no migration, and makes derived values first-class in
  `WHERE` and `ORDER BY`. Costs one duplicated arithmetic under a parity test.
- **Materialise a read model (rejected).** A `student_metrics` table or Postgres materialised view
  refreshed on `GradeFinalized` would make every derived value an ordinary indexed column — by far
  the best performance and the simplest queries. Rejected because it reintroduces exactly the drift
  ADR-0005 removed, needs a migration and a refresh command in a build with no scheduler, and
  ROADMAP §1 already records caching as a **documented later optimization** rather than a thing to
  reach for at the first inconvenience.
- **Derive in PHP and filter in memory (rejected).** One arithmetic, correct by construction.
  Rejected because pagination becomes a lie: you load the whole candidate set to page it, so `meta`
  describes a set you already paid for in full.
- **Refuse derived filters (rejected).** Filters restricted to stored columns is cheap and
  impossible to get wrong — and removes GPA Distribution, "students below 3.0" and every attendance
  filter, which is most of the analytical value Phase 4.6 exists to add.

## Consequences

- **Every new derived filter needs its SQL twin and a parity case.** That is the standing tax of
  this decision, and it is the thing to weigh before adding one.
- **The parity test is a gate, not documentation.** If it is ever deleted or skipped, this ADR has
  been reversed silently.
- **Prefer extending the facade over reimplementing.** A consumer needing a different shape of the
  same figure adds a method to the owner; deriving GPA outside `Grading` is a regression of
  ADR-0020, and the fact that the SQL now exists makes that temptation stronger, not weaker.
- Phase 5 inherits this: Academic Standing and the Credit-Hour Summary must consume these facade
  methods rather than re-deriving, and if they need their own aggregates they add them to their own
  owner under the same parity obligation.
- ADR-0005's no-cache stands unqualified. Nothing in this decision stores a derived value anywhere.
