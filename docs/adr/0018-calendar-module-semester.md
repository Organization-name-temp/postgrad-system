# A supporting Calendar module owns the Semester and its period deadlines

**Status:** accepted

Phase 3 needs the academic term for the first time: Offerings belong to a semester,
registration/add-drop/late-add/withdrawal are semester-dated windows (FR-REG-004,
FR-CAL-001), and no Term/Semester/Calendar model existed anywhere — the closest things
were Admissions' `AdmissionCycle` and the free-string `students.enrollment_term`
("Fall 2026"). Two questions had to be settled: **which module owns the Semester**, and
**how its periods are modeled.**

**Ownership — a new supporting `Calendar` module.** `Calendar` owns `Semester` (a `Term`
enum {Fall, Spring} + year, unique together, never hard-deleted) and exposes a
`Calendar\Public` read surface (Semester DTO, "whose registration window is open now").
This follows architecture.md §4.2, which planned Calendar as a supporting module
"consumed by everyone" — and everyone is coming: Phase 3 (offerings, registration
windows), Phase 4 (grade-submission deadline, Incomplete conversion), Phase 5 (standing
per semester), Phase 6 (transcript grouping). Housing the Semester in Enrollment would
force every later phase to read calendar dates through a registration-workflow module
forever, or pay for a move later. CRUD is guarded by the existing
`ManageAcademicCalendar` permission — the same guard AdmissionCycle already uses.

**The Cycle/Semester duality stays.** `AdmissionCycle` remains in Admissions untouched:
it is the *application* window Admissions runs; the Semester is the *academic* term the
accepted Applicant enrolls into. They share the Term+year naming and are linked by
natural key only (no FK, ADR-0012); `students.enrollment_term` resolves to a Semester the
same way. Admissions' internal `IntakeTerm` enum is left as-is; migrating it onto
`Calendar\Public\Term` is an optional later cleanup, not Phase 3 work.

**Periods — five typed, order-validated date columns.** `starts_at`/`ends_at`, the
registration window (`registration_opens_at`/`registration_closes_at`),
`add_drop_ends_at`, `late_add_ends_at`, `withdrawal_deadline_at`, with a validation
invariant enforcing their ordering (registration opens before the semester starts;
add/drop < late-add < withdrawal deadline ≤ semester end). Admins enter explicit dates —
the SRS's "Week 1–2 / Week 3" language is seed data, not computed weeks. "Registration
is open" / "current semester" are always **derived from the dates**, never stored flags
(the `AdmissionCycle::isOpen()` precedent). The remaining FR-CAL-001 period types are
deferred to their consumers: grade-submission deadline and final-exam period arrive as
columns in Phase 4; holiday/break periods stay unbuilt (session cancellation covers the
Phase 3 need — see ADR-0019).

## Considered options

- **New supporting Calendar module (chosen).** Matches the planned architecture; a pure
  date-authority module with no reason to couple to registration workflow.
- **Enrollment owns the Semester (rejected).** One less module now, but Grading, Records,
  and Reporting would read the academic calendar through `Enrollment\Public` forever —
  or a later move remodels exactly what a structural seed should have placed correctly.
- **Config-only periods, no entity (rejected).** Period dates differ per semester, need
  the admin UI the permission matrix already promises, and anchor Offerings — they are
  data, not configuration.
- **Generic `periods(type, start, end)` table (rejected).** The Phase 3 periods are a
  fixed, *ordered* pipeline the registration state machine reads; the ordering invariant
  is trivial across columns and awkward across rows. Holidays — the one many-per-semester
  type — get their own child table if a consumer ever appears.
- **Merge Cycle and Semester into one entity (rejected).** Different lifecycles, owners,
  and windows: a Cycle closes before its Semester begins, is Admissions workflow, and
  already shipped in Phase 2.

## Consequences

- New deptrac layers `Calendar_Public`/`Calendar_Internal`; edges:
  `Enrollment_Internal → Calendar\Public`, `App → Calendar\Public`. Nothing depends on
  Calendar's internals; Calendar depends on no other domain module.
- The admin "Calendar" area grows from Cycles to Cycles + Semesters (+ per-semester
  Offerings, which live in Enrollment but are managed under the same guard — see
  ADR-0017 and the Phase 3 roadmap).
- Phase 4 adds its deadline columns by migration; no Phase 3 schema anticipates them.
- Every period check in Enrollment (submission window, free add/drop, late-add,
  withdrawal) reads the same `Calendar\Public` answers — one authority, no drift.
- Phase 5 also uses those answers to select a post-Exemption-Revocation Remediation
  Semester: the current main Semester is a real opportunity only while registration,
  add/drop, or late-add remains open; afterward the search moves to a later eligible
  Offering ([ADR-0037](0037-preparatory-termination-checkpoint.md)).
- Calendar publishes a minimal Public `SemesterChanged` event after a relevant create or
  date update. It carries the Semester id, not duplicated dates; Enrollment reads the
  authoritative Public DTO and idempotently reconciles Remediation Obligations. An active
  assigned remediation checkpoint follows Grade-Submission Deadline extensions until it
  is evaluated but never shortens after an earlier edit; Enrollment records every edit's
  official and effective dates and sends the required renewed notification. An evaluated
  checkpoint is historical and no longer changes. A recovery
  command covers post-commit handler failure
  ([ADR-0043](0043-enrollment-owns-remediation-obligations.md)).
