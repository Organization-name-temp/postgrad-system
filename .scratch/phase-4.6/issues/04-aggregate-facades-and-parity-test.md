# 04 — Batched owner-facade derivation + result parity

**What to build:** Make derived values *expressible*. Today CGPA, attendance percentage and
enrollment counts are computed on read, one record at a time — `GradingRecords::cgpaFor()` takes a
single Student id and crosses two module seams per call. So "the CGPA spread across this Program",
"sort this list by CGPA" and "how many advisees are below 3.0" are not slow; they cannot be
written without N+1 reads. After this ticket they are batched reads behind the same facades that
already own them.

- **Each owning module grows batch methods on its existing `Public` facade.** Candidate IDs and
  immutable DTOs cross module seams in bounded batches; the owner bulk-reads only its own tables and
  invokes the existing owner-local PHP calculator. Consumers never join another module's tables.
- **No migration, no cache, no materialised view.** Derived-never-stored survives intact and
  ADR-0005 stands unqualified. Nothing here stores a derived value anywhere.
- **Derived filtering remains truthful.** The complete authorized candidate result is calculated,
  filtered, and sorted before page slicing in memory. Stored-column requests continue to filter,
  sort, and paginate in SQL.
- **Batch/single result parity is the obligation.** A test runs both facade forms over the seeded
  cohort and asserts equality, covering the three places the shared calculator is subtle: accumulation in
  integer hundredths with half-up rounding to two decimals applied **exactly once** (including a
  case that rounds *across* the 3.0 threshold), supersession among a Course's Attempts by the
  Semester's `starts_at` across a Fall→Spring boundary, and `null`-not-`0.00` for a Student with no
  CGPA-counting graded Enrollment.
- **This test is a gate, not documentation.** It also asserts totals, ordering, and page boundaries
  after a derived filter, so in-memory slicing cannot make pagination lie.

Prior art for the seam is direct: the existing `Public`-facade tests under `Feature/Grading` and
`Feature/Enrollment` already exercise these facades without going through HTTP.

**Blocked by:** 01 — Seeded cohort + assertion audit.

**Status:** ready-for-agent

- [ ] The Grading records facade gains a batch CGPA method and distribution method using bounded
      candidate DTO batches, owner-local bulk reads, and the existing calculator
- [ ] The Enrollment records facade gains the attendance and enrollment aggregates the Reports and
      the derived filters need
- [ ] Admissions gains pipeline aggregation by status over an Admission Cycle
- [ ] No migration is added and no derived value is stored or cached anywhere
- [ ] No query joins tables owned by another module and no academic arithmetic is duplicated in SQL
- [ ] The parity test asserts batch and single-Student CGPA agree across the whole seeded cohort
- [ ] Parity is asserted specifically for a value that rounds **across** the 3.0 threshold
- [ ] Parity is asserted for a Course with a superseding Attempt spanning a Fall→Spring boundary
- [ ] Parity is asserted for a Student with no CGPA-counting graded Enrollment, where both return
      `null` and neither returns `0.00`
- [ ] The parity test is named and located so that deleting it is conspicuous in review
- [ ] Derived filtering and sorting happen before in-memory slicing; tests assert truthful total,
      order, and page boundaries, while stored fields continue to paginate in SQL
- [ ] deptrac stays green — no new edge is needed or added
- [ ] `composer gate` green
