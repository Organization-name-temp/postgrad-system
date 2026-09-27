# Phase 7 implementation tracker

Fixed review baseline: `b3a3ae7b71538c36230985270de2b73bc2f92088`.
Branch: `phase-7-academic-reports-bilingual`. Initial worktree contained only the
approved Phase 7 planning directory, committed at the user's request as `c113933`.

## Verification contract

Use PostgreSQL Pest HTTP tests, focused Vitest/RTL interactions and serial
Playwright journeys. Behaviour changes follow red/green slices. Run one consolidated
Standards/Spec review against the fixed baseline after implementation, then fix its
actionable findings and rerun affected checks. No push, merge or external runner.

## Prerequisite frontier

Phase 7 #01 requires Phase 4.6 #08 and #15. The transitive ticket closure is
#01, #02, #03, #04, #05, #06, #08 and #15. Existing Support parser, Arabic
normalizer and OpenSpout writer are executable; shared frontend query/filter/export
controls, chart/table presentation, Analytics composition and Reports are absent.
Owner batch calculations are absent from Grading and AcademicStanding facades.
Verify each remaining acceptance criterion before marking a prerequisite satisfied;
planning merges are not implementation evidence. Other Phase 4.6 tickets are outside scope.

## Phase 7 ticket evidence

| Ticket | State | Evidence |
| --- | --- | --- |
| 01 Standing | Pending prerequisites | No report implementation yet |
| 02 Readiness | Pending 01 | No report implementation yet |
| 03 Termination | Pending 01 | Existing operational queues are not Reports |
| 04 Shell/Catalog/Admissions polish | Pending audit | |
| 05 Enrollment/Grading/progress polish | Audit in progress | B05-01 Arabic Waiver download message fixed; page regression passes EN/AR |
| 06 Records polish | Pending audit | Existing Records print regressions to retain |
| 07 Acceptance | Pending 01–06 | Human demo review remains required before phase acceptance |

## Commands and results

Full package gates remain pending. Starting commit and initial clean worktree verified.

### Shared frontend prerequisites (#02/#03/#05 slices)

- Added URL-source collection criteria, filter controls, pagination and export-error
  feedback. Owners supply filters and authenticated download callbacks; shared UI
  carries no domain names. URL test covers bookmarked criteria and browser Back.
- Added MUI X Charts 9.14.0 (same major as Tree View 9), categorical theme tokens
  from design-system §14, bar/line/pie gallery, named chart sections and exact
  chart-data tables with ordinary keyboard-operable drill-through links.
- Red: missing component/hook imports failed before implementation. Green:
  focused UI/theme run: 5 files, 10 tests. FilterBar clear-selection regression
  also passes. `npm run lint`, `npm run typecheck`, `npm run format` pass.
- `npm run e2e -- e2e/style.spec.ts`: **4 passed, 45.0s**. Existing gallery axe
  checks cover light/LTR, dark/LTR, light/RTL and dark/RTL, with all three chart
  types and exact table values. Screenshot artifacts are in the local Playwright
  report. First run exposed indexed locators shifting after a button label changed;
  fixed by querying each named chart region, then reran all four combinations.
- An early chart import ran while npm was still extracting the package and failed;
  it passed after the verified install session completed. No library workaround.
- Full frontend/backend gates remain pending the complete phase. These slices do
  not mark the screening tracer bullet or whole prerequisite tickets complete.

### Cohort prerequisite (#01)

In progress: added a test-first generated cohort layer with isolated staff and
natural keys, then connected it to `DatabaseSeeder`. Red tests confirmed the
missing seeder and zero generated screening rows. The first implementation run
revealed the shipped Catalog has five Programs; corrected the test's invented
six-Program expectation to require all five.

- Focused tests before main-seeder integration: **2 passed, 15 assertions, 39.62s**.
  Covers 180 Students, five Programs/terms, CGPA diversity/nulls, stable Student
  identifiers and named `r02` facts, plus 40 idempotent screening Applications.
- Uses fixed Faker seed 4607; reuses academic seed helpers and owner GPA/outcome
  calculations. Isolated Advisor/Instructor/Head accounts keep named rosters intact.
  Evidence is fictional private seed content, with a valid PNG Photo.
- `php artisan migrate:fresh --seed --force`: **passed, 65.36s total**; generated
  layer **15.482s** in this run. All five Semester windows remain the existing
  relative-date fixture. No browser journey ran during migration/seeding.
- Pint for affected seed/test files, PHPStan and deptrac: **passed**.
- `php artisan test --filter='GeneratedCohortSeederTest|RoleAccountSeederTest|GradingSeederTest'`:
  **21 passed, 131 assertions, 396.48s** after main-seeder integration. Existing
  named-persona Card/CGPA/roster and historical seeder assertions remain green.
- `npm run e2e -- e2e/dashboard.spec.ts`: **11 passed, 1.4m** against the fresh
  fixture, including presence/absence of Cards for seven accounts and axe across
  all four combinations. No absolute-count assertion needed a change in these
  audited files. Admissions/Grading headline navigation must still be checked
  when paginated APIs arrive; they presently identify their own named rows.
- Full package gates remain pending the phase implementation; #01 is not marked
  complete until the complete required gates and collection assertion audit pass.

### Bilingual defect B05-01

See `bilingual-audit.md`. The actual Waiver download-error page test failed in
Arabic with English fallback, then passed after moving the misplaced Arabic key:
**5 tests passed, 10.19s**. Compared backend EN/AR message key sets: no missing
counterpart keys. This is source coverage, not a runtime/layout acceptance claim.

One frontend static-check rerun hit an ESLint filesystem race while Playwright
was recreating `test-results`. After the browser suite finished, reran sequentially:
`npm run lint && npm run typecheck && npm run format` **passed**. No configuration
workaround was introduced. The interrupted run itself is not counted as a pass.

## Consolidated review

Reserved until all tickets and prerequisite changes are implemented.

### Screening collection/export prerequisite (#02/#03)

- Added owner-scoped screening selection with SQL stored-field filtering/search,
  derived age selection before slicing, stable id ties, capped pagination and a
  sibling XLSX export reusing the exact selection. Dean scope reads live Faculty
  assignments; Student Affairs/admin privileges combine without reducing scope.
- Arabic SQL search normalizes both sides and treats `%` as literal text. Shared
  `SqlCollectionQuery` knows only trusted column names, never domain concepts.
- Canonical actor-readable spreadsheet projection includes readable detail fields
  independently of query allowlists. Localized EN/AR headers/statuses, text IDs,
  RTL sheet direction and audit criteria/count verified with OpenSpout readback.
- Added shared `collection contracts` Pest dataset/HTTP test with screening as its
  first row: unknown input, scope narrowing, private-field search exclusion and
  truthful envelope. Existing Support parser tests remain green.
- Frontend queue uses URL criteria, paging and authenticated generated blob export.
  Focused RTL red tests failed for absent filters/export, then **2 passed** for
  bookmarked criteria, filter page reset, browser Back and download parameters.
  `npm run typecheck` and `npm run lint` passed.
- Backend focused run: **25 passed, 128 assertions, 5.95s**. Pint, PHPStan and
  deptrac passed. OpenAPI and both generated frontend outputs regenerated.
- Serial `npm run e2e -- e2e/admissions.spec.ts`: **1 passed, 1.3m** against
  generated cohort fixture, spanning registration, review, interviews, admission
  and Student login, with existing axe checks. Queue helper now searches its own
  named applicant so first-page assumptions do not hide pagination regressions.
- Full package gates, API drift checks after commit and final consolidated review
  remain pending. No whole prerequisite or Phase 7 ticket is claimed complete yet.

### Owner batch prerequisite (#04), roster/CGPA slice

- Enrollment Public facade now bulk-resolves whole rosters as immutable DTOs in
  bounded 100-Student reads, including empty rosters and non-counting statuses.
- Grading Public facade now returns batch nullable CGPAs and exact rounded-value
  frequencies with an explicit ungraded category. Shared owner tally uses bounded
  roster, current-grade and Semester reads; single and batch reads invoke the same
  existing supersession and integer-hundredths calculator. No storage/cache,
  migration, cross-owner join or new deptrac edge.
- Red tests confirmed missing batch methods. Corrected the new rounding fixture's
  mistaken grade scale to ADR-0021's known 13 CH at 3.30 plus 8 CH at 2.50: raw
  2.995238... rounds once to 3.00. Fall→Spring retake and null-versus-zero cases
  pass, along with the separately named seeded parity gate over all 180 Students.
- `php artisan test --filter='BatchCgpaSeededParityTest|GradingRecordsTest|EnrollmentRecordsTest|StandingObservationTest|GpaCalculatorTest'`:
  **50 passed, 289 assertions, 23.42s**; seeded parity test **16.55s**. Pint,
  PHPStan and deptrac passed. Existing Standing test double updated to the expanded
  Public contract; its read-side-effect assertions remain green.
- Screening commit `db00bbc`: `composer api:check` and `npm run api:check`
  **passed** after committing generated artifacts. Frontend `npm run format` passed.
- #04 remains incomplete: attendance/enrollment aggregates and Admissions pipeline
  aggregate still required. Full package gates and final review remain pending.

### Owner batch prerequisite (#04), remaining aggregates

- Enrollment Public facade adds attendance DTOs and per-Student Enrollment counts,
  with optional Semester selection. Attendance retains null for no recorded marks
  and true zero for recorded absences; the existing calculator owns the percentage
  and Audit at-risk threshold. Extracted the existing non-cancelled-session tally
  so existing register/schedule reads and the new facade use one owner query.
- Admissions Public `ApplicationPipelines` returns typed current-status counts and
  measured per-transition conversion rates within a Cycle, optionally Program/
  degree. Uses the same live actor scope as collections; no transitions, events or
  notifications are created. Rates count each Application once per recorded edge,
  divide by Applications known to have visited its source status, and are null
  for zero denominators. Missing historical transitions are never inferred.
- Red: absent owner methods/facade failed. Green:
  `php artisan test --filter='ApplicationPipelineAggregateTest|EnrollmentRecordsTest|AttendanceTest|AttendanceCalculatorTest'`:
  **53 passed, 181 assertions, 7.54s**. Covers cancelled attendance, no-data vs
  zero, empty counts, current statuses, conversion, Cycle selection, live Dean
  reassignment, multi-role union and denied Applicant.
- Pint, PHPStan and deptrac passed. Together with the prior CGPA parity slice,
  #04's executable aggregate capabilities are now present. Required full backend
  gate and final consolidated review remain pending; no whole-ticket acceptance
  is claimed before those verification results.

### Admissions/Catalog prerequisite (#06), Courses slice

- Courses now filter by Department, level, credit totals/ranges, Research Type and
  active state, search normalized bilingual names/code fragments, and paginate
  stored fields in SQL with a stable id tie. Reference default is 100; work-page
  URL state requests 25. Shared contract dataset extended with the Course endpoint.
- Added canonical localized export/import-template columns, retaining both names,
  the credit split and string reference/code cells. Export shares list selection,
  streams all matches, independently enforces the actor's catalog-view projection,
  localizes Research Type/active state and records criteria/count in Audit Log.
  No importer added (#16 stays outside this goal). `/courses/export` precedes the
  numeric detail route; aggregate tree/history endpoints remain whole.
- Red API tests failed for absent pagination and export route; green focused backend
  run: **25 passed, 152 assertions, 4.93s** including original Course management
  authorization/domain rules and both shared collection dataset rows.
- Frontend red failed for missing controls. Green: **1 focused page test** verifies
  URL criteria, reset to page 1 and authenticated generated blob export. Backend
  OpenAPI and frontend hooks/Zod regenerated. Typecheck, lint, format, Pint,
  PHPStan and deptrac passed.
- Serial `npm run e2e -- e2e/course-collections.spec.ts e2e/headline.spec.ts`:
  **4 passed, 57.4s**. EN/AR bookmarked Course filters survive language switch and
  reload, private-client downloads succeed, both Course pages pass existing
  serious/critical axe threshold, and the unchanged complete tree/detail journey
  passes including RTL switch. Local report includes screenshots and EN/AR XLSX.
- Other Catalog resources, Admissions collections/pickers and #06 full gates remain
  pending. API drift checks after this slice commit and final review remain pending.
