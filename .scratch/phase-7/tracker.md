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
#01, #02, #03, #04, #05, #06, #08 and #15. At the fixed baseline, the Support parser, Arabic
normalizer and OpenSpout writer were executable; shared frontend query/filter/export
controls, chart/table presentation, Analytics composition and Reports were absent.
Owner batch calculations were absent from Grading and AcademicStanding facades.
The evidence below records the executable capabilities added since that baseline.
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

### Admissions/Catalog prerequisite (#06), reference collections

- Programs, Departments and Faculties now return the same always-paginated shape,
  reference default 100, stable id ties, normalized name/code search, active filters
  and parent/degree criteria. Program Faculty filtering stays within Catalog's own
  Department relation. Parent aggregate tree/curriculum/history endpoints unchanged.
- Canonical actor-readable reference exports include both names, string identifiers,
  localized degree/active values and derived Program total via its existing owner
  accessor. Each file covers the full matching selection and records criteria/count.
- Red HTTP tests demonstrated missing envelopes and export routes. Green whole
  affected Catalog/shared-contract run: **131 passed, 724 assertions, 17.69s**.
  All four Catalog collection endpoints now have shared dataset rows. Original
  mutations, authorization, Catalog tree, curriculum/version and facade tests pass.
- Focused page tests: **4 passed** across Courses and the three reference pages,
  proving bookmarked criteria, page reset and private-client blob exports.
  Generated hook signature changes exposed two old admin-picker callers; corrected
  options argument positions and reran their existing tests: **16 passed, 10.43s**.
- Typecheck, lint, format, Pint, PHPStan and deptrac passed. OpenAPI and generated
  client/Zod artifacts regenerated. Courses commit `43a9949` passed both API drift
  checks before the reference migration; repeat for the reference commit pending.
- Serial `npm run e2e -- e2e/reference-collections.spec.ts`: **2 passed, 45.4s**,
  each traversing all three collections with EN/AR filters, successful workbook
  downloads and serious/critical axe checks. Workbook artifacts in the local report.
- Admissions collections and type-ahead picker adoption remain pending for #06;
  full package gates and the one consolidated final review are still reserved.

Reference collection commit `487964d`: `composer api:check` and
`npm run api:check` **passed** after commit; generation produces no drift.

### Admissions/Catalog prerequisite (#06), Dean review collection

- Dean review now uses the collection envelope, scoped query and all-row XLSX
  selection. Its new explicit Dean-Faculty scope preserves the operational rule:
  administrators oversee all Faculties, while a Dean who also holds Student Affairs
  still sees only assigned Faculties. Screening and Pipeline keep their existing
  Student Affairs oversight semantics. The query never widens this base scope.
- Red HTTP regression demonstrated ignored paging; export regression demonstrated
  the absent route. Green focused backend run: **38 passed, 239 assertions, 9.55s**
  including original review decisions/authorization, screening/export/Pipeline tests
  and the shared collection contract dataset extended with Dean review.
- Canonical export uses the full filtered selection independent of pagination,
  bounded Program resolution, existing bilingual labels/RTL and audited resource
  `dean/applications`, criteria and count. Multirole cross-Faculty exclusion is
  verified by reading the workbook back.
- Frontend red showed absent URL controls and export. **2 focused RTL tests passed**:
  bookmark restoration, search reset, browser Back and authenticated blob criteria.
  Reuses bilingual filters/toolbar and isolates National ID direction in RTL.
- Pint, PHPStan, deptrac, typecheck, lint and full frontend format check passed.
  OpenAPI/client/Zod regenerated from controller docs, including nullable derived age.
- Serial browser run: **3 passed, 1.9m** — complete admissions headline and EN/AR Dean
  bookmark reload, downloads and serious/critical axe checks. The headline helper
  subsequently gained Dean search to keep its new Applicant visible beyond page 1;
  rerun of that final helper is pending below.
- Decision worklist, other Admissions collections, type-ahead pickers, full package
  gates and the consolidated final review remain pending. This is a prerequisite
  slice, not completion of #06 or any Phase 7 Report ticket.

Dean review commit `dd10e42`: both `composer api:check` and `npm run api:check`
**passed**. Final headline helper rerun **1 passed, 1.6m**, completing the full
Admissions journey with URL search on the newly paginated Dean review queue.

Decision-worklist continuation in progress (not marked complete): red HTTP tests
showed ignored decision-state pagination and missing `/dean/decisions/export`.
The in-progress backend now shares the awaiting-decision status definition with the
Dashboard Card and uses Dean-Faculty collection/export scope. Initial queue/Card/
final-decision regression run **38 passed, 165 assertions, 9.04s**; subsequent focused
queue/export run **6 passed, 24 assertions, 3.41s**. Controller query/response docs,
generated API artifacts, UI controls, dataset registration and static/browser
verification for this next slice remain pending.

### Admissions/Catalog prerequisite (#06), Dean decision worklist

- Decision-state/degree/search/age criteria now narrow the operational Dean-Faculty
  base before stable paging. Student Affairs on the same actor never widens that
  Dean worklist. The unparameterized Dashboard Card retains complete counts and
  shares the same awaiting-decision statuses with the pageable controller.
- `/dean/decisions/export` reuses the complete scoped selection, canonical readable
  projection and bilingual XLSX behavior. Workbook assertions prove filtering to
  Waitlisted, exclusion of other Faculties, page-independent rows and resource/count
  audit metadata. No change to decision actions or acceptance mint.
- Red queue/export regressions confirmed missing paging/filtering and missing route;
  red frontend tests confirmed absent controls/export. Final focused backend run:
  **70 passed, 317 assertions, 15.21s**, including original decisions, Cards,
  administrative overrides and the shared dataset extended with decision worklist.
- **4 focused Vitest/RTL tests passed, 9.20s** across both Dean queue pages. Decision
  tests verify named state control, bookmark/API criteria, reset, Back and blob
  export. A failed named-label assertion found new dictionary keys in the wrong
  namespace; corrected both EN/AR placement and reran successfully.
- Documented all query parameters and paginated/null-age response; regenerated
  OpenAPI/client/Zod. Pint, PHPStan, deptrac, frontend typecheck, lint and full format
  check passed. Final serial browser run and post-commit drift checks pending below.
- Remaining Admissions collections/pickers and direct Faculty criterion adoption
  still pending for #06. No whole-ticket/full-package gate or final review claimed.

Decision browser run: both EN/AR collection journeys **passed** (17.3s/22.3s),
each traversing review and decisions with bookmark reload, workbook downloads and
serious/critical axe checks. The shared admissions headline **failed** before final
decisions: repeated earlier runs had exhausted all seeded capacity-one interview
slots, and the scheduling dialog correctly displayed no open slots. The headline
now creates and selects a unique slot assigned to its named interviewer through the
normal authorized UI, preserving demo slots and repeatability. Its rerun is active;
no passing headline or full frontend gate claimed for this slice yet.
The first isolated-slot headline rerun failed on the test's exact `Location` label
locator because the required-field marker is part of that label. The rendered form
had the correct date and named interviewer. Corrected the locator to the scoped
label match used by the other required inputs; the next headline rerun is active.

Decision commit `bd30df4`: `composer api:check` then `npm run api:check`
**passed**, generated artifacts have no drift. Corrected isolated-slot headline
rerun **1 passed, 1.4m** — complete application/SA/Dean/interview/admission journey,
including paginated decisions and existing accessibility checks. Final modified
browser files pass Prettier. ESLint intentionally ignores `e2e/`; the targeted
invocation emitted ignored-file warnings and is not claimed as browser-file lint
coverage. The earlier configured package lint/typecheck passed.

Next dependency frontier: Admission Cycle management/public lookup, awaiting/booked/
assigned interviews and slot pool, advisor/interviewer/applicant pickers with
contract/type-ahead adoption, and Dean Faculty criterion. Then the Pipeline Report
and print prerequisite unlock Phase 7 academic Reports. All seven Phase 7 ticket
acceptance, full package gates and consolidated review remain outstanding.

### Admissions/Catalog prerequisite (#06), Admission Cycles

- Management and public currently-open lookup now always use the collection
  envelope, stable multi-sort and normalized bilingual name search. Management
  defaults to 25, public reference lookup to 100, with the shared hard cap. Year/
  term/active/window criteria narrow selection; derived `is_open` filtering reuses
  Admissions' existing `currentlyOpen` scope rather than a stored state.
- Public lookup applies the current-open base before all criteria. Closed/future
  Cycle ids and `is_open=false` return empty matches; the public export sibling is
  absent (404). Both endpoints added to the shared dataset, whose actor fixture now
  explicitly supports unauthenticated public collection checks.
- Added authenticated `/admission-cycles/export` before numeric detail. Independent
  canonical readable projection includes string Cycle ids, both names, ISO window
  timestamps and localized term/active/open labels. All filtered rows export,
  regardless of page size, with RTL and audit criteria/count verified in workbook.
- Red backend tests demonstrated ignored query/paging, missing public metadata and
  absent export route. Green focused run: **38 passed, 249 assertions, 7.45s**,
  including original CRUD/authorization/open-window and registration behavior plus
  all shared contract rows. Pint, PHPStan and deptrac passed.
- Management UI has URL filters/paging and generated authenticated blob export;
  red absent-control tests became green. Public registration uses a server-backed
  type-ahead with a 100-row request. Unfiltered metadata controls availability;
  filtered matches never silently auto-select a lone result or close admissions.
- Registration red regression used 102 open Cycles with only one on the lookup page;
  green tests select a searched Cycle outside that page and submit its opaque id.
  Empty searches keep the form available with a disabled submit until selection.
  **8 focused frontend tests passed, 13.04s** across registration and management.
  Typecheck, lint and full format check passed. OpenAPI/hooks/Zod regenerated.
- Final serial EN/AR collection + admissions headline browser run, post-commit API
  drift checks and whole #06 acceptance remain pending; full gates/review reserved.

Cycle serial browser verification: **3 passed, 1.9m** — complete admissions headline
and EN/AR management bookmark reload, successful authenticated XLSX downloads and
serious/critical axe checks. No live Cycle windows or named personas modified by
the new collection checks. Workbook artifacts captured in the local Playwright
report. The type-ahead's beyond-page and empty-search behavior is proven by focused
RTL tests; the browser headline exercises the preserved single-open-Cycle path.

Cycle commit `407ea30`: `composer api:check` and then `npm run api:check`
**passed**, with no generated drift. Remaining #06 frontier is the interview
worklists/slot pool and staff/applicant pickers, Catalog picker adoption and direct
Dean Faculty criterion. Existing interview scheduling is university-wide for its
privileged actors; migration must preserve that operational scope independently
of the faculty-scoped Dean review/decision queues.

### Admissions/Catalog prerequisite (#06), awaiting-interview worklist

- The unbooked Dean-approved population now shares Application collection parsing,
  stored/derived selection and pagination. Its explicit Awaiting-Interview scope
  retains university-wide scheduling for SA, Deans and administrators, independent
  of the faculty-scoped Dean review/decision workflows. Existing booked applicants
  remain excluded before any filter, sort, count, page or export.
- Red HTTP tests demonstrated ignored query/page criteria and absent export route.
  New `/applications/awaiting-interview/export` uses full scoped selection and
  existing canonical bilingual projection, bounded Program reads and audit resource
  `applications/awaiting-interview`. Workbook readback proves page-independent
  selection, booked exclusion and counts; unauthorized Applicants are denied.
- Focused backend regression run **59 passed, 337 assertions, 10.38s**, including
  original scheduling/mutations, screening, both Dean queues, Pipeline owner scope
  and the shared dataset extended with this worklist. Pint/PHPStan/deptrac passed.
- Scheduling retains both existing worklists. Shared URL hook now supports an
  optional collection namespace: browser keys such as `awaiting_q` are stripped to
  ordinary flat `q` for the API, and changing one worklist preserves the other's
  criteria/page. Single-collection callers retain existing behavior. Red shared
  hook regression proved absent isolation; green tests cover both contexts and Back.
- Awaiting section is a named accessible region with URL controls, paging and
  authenticated blob export. Focused frontend red absent-control tests became
  green. **6 files / 12 tests passed, 23.67s**, including the hook and previous
  single-collection pages. Existing bilingual labels and numeric direction reused.
- OpenAPI/client/Zod regenerated from documented controller parameters and the
  always-paginated nullable-age response. Frontend typecheck/lint/full format passed.
- Final EN/AR collection and complete admissions headline browser run is active;
  headline now searches its own Applicant inside the paginated awaiting region.
  Booked/assigned interviews, slots/pickers and remaining #06 acceptance are pending;
  this slice does not claim full prerequisite or Phase 7 ticket completion.

Awaiting-interview serial browser verification: **3 passed, 2.0m** — full admissions
headline with Applicant search in the paginated worklist, plus EN/AR bookmarked
criteria reload, authenticated workbook downloads and serious/critical axe checks.
The existing slot creation/evaluation/completion/acceptance journey remains intact.

Awaiting-interview commit `1dea732`: `composer api:check` followed by
`npm run api:check` **passed**, with no generated drift. Next #06 frontier:
booked and assigned Interview collections (preserving the assigned-interviewer/
administrator scope), slot pool and applicant reschedule lookup, staff pickers,
Catalog type-ahead adoption and direct Dean Faculty criterion. Pipeline/print
prerequisites and all seven Phase 7 acceptance/gates/final review remain pending.

### Booked and assigned Interview collections — backend and assigned UI in progress

- Continued the executable #06 dependency closure on the fixed Phase 7 branch.
  Booked list remains gated by `interview.schedule`; assigned list remains scoped
  to the live slot interviewer, with the existing administrator exception.
- Red HTTP tests demonstrated ignored pagination and missing sibling exports.
  Added one Admissions-owned selector joining only Admissions tables, normalized
  bilingual applicant search, confirmation/mode/opaque references, scheduled and
  score bounds, stable sorting and SQL pagination. Foreign staff resolution uses
  Identity Public Directory DTOs in bounded batches.
- Canonical exports mirror the complete filtered row scope across pages, use text
  identifiers and bilingual RTL workbooks, and record criteria/count audit evidence.
  Tests cover live reassignment, outside interviewer criteria, unauthorized booked
  reads/exports, and empty assigned scope for an Applicant.
- Focused backend regression: **48 passed, 177 assertions, 10.56s** including
  scheduling, evaluation and Admissions/Enrollment administrator overrides.
  Pint, PHPStan and deptrac passed. No full package gate claimed.
- Assigned Interview page HTTP-boundary tests were red for missing URL controls and
  export. URL filters/pagination/authenticated export now implemented. A subsequent
  red assertion caught a generated text response; controller binary annotations
  were corrected and backend OpenAPI/client/Zod regenerated. Verification is still
  in progress; booked UI migration and serial EN/AR/browser evidence remain pending.
- All seven Phase 7 acceptance items, remaining prerequisite closure, final package
  gates, print/layout audit and consolidated code review remain pending.

Assigned UI focused verification: **2 files / 4 tests passed, 12.02s**, including
awaiting regressions. Frontend typecheck, lint and full formatting passed after the
binary response correction. Subsequent generated-contract inspection identified
an inferred string score from the assigned example's null value; explicit integer/
nullable response annotations were added on both collection endpoints, and the
assigned pagination example now uses its actual sibling route. Final client
regeneration and affected typecheck follow. No browser or final gate claimed.

Final regenerated collection score types are `number | null`; affected frontend
typecheck and formatting passed. Backend PHPStan rerun passed after response
annotations. The next slice is booked worklist URL controls plus serial headline
and EN/AR collection verification; this checkpoint remains partial #06 progress.

Checkpoint `fc4b154`: sequential `composer api:check` and `npm run api:check`
passed after commit, with no generated drift. Working tree was clean. This is
implementation progress on #06, not completion of #06 or any full Phase 7 ticket.
