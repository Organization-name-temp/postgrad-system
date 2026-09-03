# Phase 4.5 and 4.6 Review Resolution Design

**Status:** approved in conversation on 2026-09-03

## Purpose

Resolve the Standards and Spec findings recorded on pull requests #7 and #8 without weakening the
accepted module boundaries, fixed RBAC matrix, bilingual contract, or localhost-learning scope.
Phase 4.5 receives executable dashboard corrections first. Phase 4.6 then rebases its design on the
corrected dashboard contract and removes contradictions before implementation begins.

## Governing decisions

Accepted ADRs override the original review where they conflict:

- ADR-0005 deliberately defers ClamAV and other production infrastructure. The Course import must
  not be described as malware-scanned in this learning build; it must validate file type and
  workbook structure and keep the file private.
- ADR-0012's Public-facade module seams prohibit a single SQL statement that joins tables owned by
  several different modules. Phase 4.6 will prefer seam correctness over database-level pagination
  of derived cross-module values.
- ADR-0011 requires one append-only version snapshot per changed Course. A bulk import is one atomic,
  audited operation, but it does not collapse multiple Course histories into one invalid snapshot.

## Phase 4.5 corrections

### Authorization and navigation parity

Persona Card endpoints and their SPA registry entries use the same Role gates as the full surface:

- My Application: Applicant (R01).
- My Registration and My Academic Record: Student (R02).
- Outstanding Incompletes: Instructor (R04).
- Sheets Awaiting Review: Department Head (R06).

The API remains authoritative. Add a small Identity-owned `role` middleware alias accepting one or
more fixed `Role` codes, backed by `User::hasRole()`, and apply it independently to each of the five
persona Card endpoints. Permission-gated Cards keep `can:` middleware. The dashboard registry uses
Role and Permission union types derived from the existing constants, so an invalid gate cannot be
expressed.

### Card data and domain derivations

A Card may project, bound, and label data, but it must not invent a reusable academic rule.

- Move open-window enumeration and next-calendar-deadline selection into Calendar's existing
  `SemesterCalendar`/`SemesterSummary` derivation surface. `SemesterWindowsCardBuilder` only selects
  the relevant Semester through `AcademicCalendar` and serializes those results.
- Add a Calendar-owned current-academic-work lookup: the most recently started Semester whose
  `starts_at` is not in the future. This remains the current grading Semester after teaching ends,
  so overdue grade work stays visible. Grading consumes this Public result instead of deriving
  "latest started Semester I teach" from Course Offerings.
- My Grade Sheets reports the latest actionable round for each Offering. An unfinalized Correction or
  Incomplete Resolution round takes precedence over a finalized Initial round. Counts and row status
  therefore represent all outstanding Sheet work, while Offering count remains distinct.

The phase spec and ADR-0024 will distinguish owner-supplied reusable read derivations from forbidden
Card-only business rules. No new mutation, stored value, or academic outcome is introduced.

### Response contract and counts

Each owning module exposes its Card payload through a module-local API Resource. Every response has
`data` and `meta`; `meta` contains `scope` where meaningful and is otherwise an empty object. Generated
OpenAPI and Orval artifacts remain single-shape and deterministic.

Owner-local scalar counts use SQL aggregates. A count may reuse an already-materialized collection
when the same rows are necessarily loaded for business-day arithmetic, cross-module facade scoping, or
the three-row preview. The Phase 4.5 spec will state this exception explicitly; it will not demand an
extra query or a forbidden cross-module join merely to call `count()` in SQL.

### Shared presentation

`DashboardCard` will follow `docs/design-system.md`:

- radius-lg, the h5 Card-title token, and theme typography instead of literal font sizes;
- an illustrative empty-state icon plus text;
- an inline error message and retry button supplied by each query-owning Card;
- `warning.dark` for normal warning text on light surfaces and the theme's accessible semantic color
  in dark mode;
- the repeated labelled-number `Figure` becomes one shared UI primitive.

All user-facing role labels are translated from the stable Role code. Enrollment terms are translated
from their enum value. Source comments and changed phase prose use the canonical term `Dashboard`, not
`landing page`.

### Tests

Behavior changes follow red-green-refactor. Add failing tests before production changes for:

- each API persona Role gate, including administrators that inherit the Permission;
- registry and route parity for Instructor, Student Affairs, and Department Head;
- an active Correction round overriding a finalized Initial Sheet;
- Calendar-owned window/deadline and grading-work Semester derivations;
- `{data, meta}` on every Card response;
- Card empty/error/retry states, tokens, accessible warning text, typed registry behavior, translated
  roles and translated enrollment terms;
- all ten single-role dashboard compositions, the multi-role union, and administrator exclusion;
- representative Card assertions after the canonical role-account seeder runs.

## Phase 4.6 corrections

### Derived queries across module seams

Batch facade methods replace N+1 reads, but a method may not join another module's tables. Each owner:

1. obtains candidate IDs and immutable DTOs through existing Public facades in bounded batches;
2. queries its own tables in bulk;
3. computes the cross-module derived values in owner-local PHP using the existing calculator;
4. filters and sorts the derived result before slicing the requested page.

This deliberately materializes the scoped candidate set for derived filters in the localhost build.
Stored-column filters still paginate in SQL. ADR-0028 will no longer require a duplicated cross-module
SQL implementation or SQL/PHP parity test; instead, it requires batch/single-result parity over the
seeded cohort. A future stored read model may optimize this behind the same facade.

### Report authorization

Every Report begins from the ordinary-screen scope its viewer already has:

- Application Pipeline: Student Affairs unscoped; Dean limited to assigned Faculties.
- GPA Distribution: Advisor to advisees, Instructor to teaching roster, Department Head to assigned
  Departments, Dean to assigned Faculties, Student Affairs and Council unscoped only where the current
  Student Grades screen already grants that scope, and administrators through the explicit override.
- Enrollment and Course Registration summaries use the equivalent existing student/offering scopes.
- Attendance Analytics is limited to Instructor-owned Offerings, Advisor advisees, and the existing
  R09/R10 administrative override. Department Head, Student Affairs, Dean, and Council access is
  removed because ADR-0019 grants them no ordinary attendance surface.

Each ticket names its base scope, tests in-scope and out-of-scope rows, and ensures filters only narrow.

### List, export, and API contract

- Every filterable collection, including pickers and personal collections, gets a sibling export route
  unless exporting that personal/picker data is explicitly excluded in the endpoint ticket with a
  security reason. The default is inclusion.
- Export is a List capability. Reports export by drilling into the corresponding filtered List.
- The SPA downloads exports through the authenticated Axios client and an in-memory blob, following
  ADR-0016; no bare link is used.
- Tickets require comma-separated multi-column sort, partial University-ID matching, Arabic-normalized
  search, `@queryParam` documentation, preserved model-bound `@urlParam` examples, and regenerated
  OpenAPI/Orval artifacts.
- Academic Standing filters are explicitly deferred to Phase 5 and removed from Phase 4.6 acceptance
  criteria.

### Bulk Course import

The private `.xlsx` upload is validated and previewed, then confirmation applies all valid intended
changes in one database transaction. Each created or updated Course writes its own ADR-0011 version
snapshot. One Identity Audit Log entry records the import identifier, actor, source filename, counts,
and transaction outcome, making the batch one auditable act without corrupting per-Course history.

ADR-0005's malware-scanning deferral is stated in the ticket. File MIME, size, workbook structure,
bilingual names, enum values, and credit-hour arithmetic are still validated before preview. The
private file is deleted after confirmation or expiry.

### Ticket graph

Ticket 01 (seeded cohort) blocks aggregate/parity work. Query-foundation tickets block endpoint
migrations; migrated lists block their exports; migrated/exportable lists block Reports and
drill-through Cards. The specification and all 17 ticket headers will use one consistent dependency
graph.

## Delivery sequence

1. Implement and verify Phase 4.5 on `phase-4.5-dashboards`; update PR #7.
2. Run the two-axis review. Only a clean review may merge PR #7 into `main`.
3. Rebase or retarget `phase-4.6-query-filter-export` onto the merged Phase 4.5 result.
4. Correct Phase 4.6's spec, ADRs, glossary, roadmap, handoff, and tickets; update PR #8.
5. Run the two-axis review. Only a clean review may merge PR #8 into `main`.
6. Run the full backend and frontend gates on merged `main`, then delete both local and remote phase
   branches.

No branch is deleted before its PR is merged and the merged result is verified.
