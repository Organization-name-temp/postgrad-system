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

**Status:** ready-for-human

- [x] The Grading records facade gains a batch CGPA method and distribution method using bounded
      candidate DTO batches, owner-local bulk reads, and the existing calculator
- [x] The Enrollment records facade gains the attendance and enrollment aggregates the Reports and
      the derived filters need
- [x] Admissions gains pipeline aggregation by status over an Admission Cycle
- [x] No migration is added and no derived value is stored or cached anywhere
- [x] No query joins tables owned by another module and no academic arithmetic is duplicated in SQL
- [x] The parity test asserts batch and single-Student CGPA agree across the whole seeded cohort
- [x] Parity is asserted specifically for a value that rounds **across** the 3.0 threshold
- [x] Parity is asserted for a Course with a superseding Attempt spanning a Fall→Spring boundary
- [x] Parity is asserted for a Student with no CGPA-counting graded Enrollment, where both return
      `null` and neither returns `0.00`
- [x] The parity test is named and located so that deleting it is conspicuous in review
- [x] Derived filtering and sorting happen before in-memory slicing; tests assert truthful total,
      order, and page boundaries, while stored fields continue to paginate in SQL
- [x] deptrac stays green — no new edge is needed or added
- [x] `composer gate` green

## Phase 7 prerequisite disposition

This ticket is in the authorized executable closure for Phase 7; its implementation
is on `phase-7-academic-reports-bilingual`. Existing capabilities were verified
before extending them. See [the Phase 7 tracker](../../phase-7/tracker.md) for
focused tests, runtime evidence and the fixed review baseline, and
[the consolidated review](../../phase-7/review.md) for resolved Standards/Spec
findings. Both complete package gates passed: backend 1,334 tests / 8,458 assertions;
frontend 684 unit tests and 179 serial browser checks. The additional CI timing
regression and two affected workflows passed (3/3, 12.4m); see tracker for the
180-test discovery versus 179-test full-gate distinction. Any by-hand acceptance remains pending. Other Phase 4.6 tickets are
outside this goal and are not marked complete by this disposition.
