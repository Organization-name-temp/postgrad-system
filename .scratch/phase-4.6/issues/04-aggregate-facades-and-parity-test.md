# 04 — Batch and aggregate facade methods + the SQL↔PHP parity test

**What to build:** Make derived values *expressible*. Today CGPA, attendance percentage and
enrollment counts are computed on read, one record at a time — `GradingRecords::cgpaFor()` takes a
single Student id and crosses two module seams per call. So "the CGPA spread across this Program",
"sort this list by CGPA" and "how many advisees are below 3.0" are not slow; they cannot be
written at all. After this ticket they are single SQL queries behind the same facades that already
own them.

- **Each owning module grows batch and aggregate methods on its existing `Public` facade** —
  `cgpaForMany` and a distribution method on the Grading records facade, attendance and enrollment
  aggregates on the Enrollment records facade, pipeline aggregation in Admissions. Each is **one
  SQL query** using window functions, which is what ROADMAP §1 chose Postgres for. The seam is
  unchanged: consumers still ask the owner (ADR-0012), and this continues an existing pattern —
  `gradesFor(array $enrollmentIds)` is already a batch read.
- **No migration, no cache, no materialised view.** Derived-never-stored survives intact and
  ADR-0005 stands unqualified. Nothing here stores a derived value anywhere.
- **The cost, named: the GPA arithmetic now exists twice** — once in the PHP calculator, once in
  SQL. That cuts against ADR-0020's whole purpose, and it is accepted for a specific reason. A
  cache's failure mode is drift *in time*: a second write path forgets to invalidate and the stored
  value silently disagrees. That mode does not exist here, because both implementations read the
  same authoritative rows at the same instant. They can only disagree *in code* — and a difference
  in code is exactly what a test catches.
- **So the duplication ships under a parity obligation.** A test runs both forms over the seeded
  cohort and asserts equality, covering the three places the arithmetic is subtle: accumulation in
  integer hundredths with half-up rounding to two decimals applied **exactly once** (including a
  case that rounds *across* the 3.0 threshold), supersession among a Course's Attempts by the
  Semester's `starts_at` across a Fall→Spring boundary, and `null`-not-`0.00` for a Student with no
  CGPA-counting graded Enrollment.
- **This test is a gate, not documentation.** If it is ever deleted or skipped, ADR-0028 has been
  reversed silently and grades have become a thing two implementations can disagree about.

Prior art for the seam is direct: the existing `Public`-facade tests under `Feature/Grading` and
`Feature/Enrollment` already exercise these facades without going through HTTP.

**Blocked by:** None — can start immediately, in parallel with 01 and 05.

**Status:** ready-for-agent

- [ ] The Grading records facade gains a batch CGPA method and a distribution method, each one SQL
      query, with no change to the existing single-Student method's behaviour
- [ ] The Enrollment records facade gains the attendance and enrollment aggregates the Reports and
      the derived filters need
- [ ] Admissions gains pipeline aggregation by status over an Admission Cycle
- [ ] No migration is added and no derived value is stored or cached anywhere
- [ ] The parity test asserts batch and single-Student CGPA agree across the whole seeded cohort
- [ ] Parity is asserted specifically for a value that rounds **across** the 3.0 threshold
- [ ] Parity is asserted for a Course with a superseding Attempt spanning a Fall→Spring boundary
- [ ] Parity is asserted for a Student with no CGPA-counting graded Enrollment, where both return
      `null` and neither returns `0.00`
- [ ] The parity test is named and located so that deleting it is conspicuous in review
- [ ] deptrac stays green — no new edge is needed or added
- [ ] `composer gate` green
