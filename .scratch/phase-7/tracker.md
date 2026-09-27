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
| 01 Standing | Implemented; final acceptance pending | Owner summary/List/export, protected EN/AR UI and four native journeys verified below; final gates/review pending |
| 02 Readiness | Implemented; final acceptance pending | Bounded owner parity, scoped summary/List/export, EN/AR UI and four native journeys verified below; final gates/review pending |
| 03 Termination | Implemented; final acceptance pending | Scoped minimal Case summary/List/export, authorized review links, worked seed evidence, EN/AR UI and four native journeys verified below |
| 04 Shell/Catalog/Admissions polish | Implemented; final verification pending | Concrete label, count, contrast and responsive defects fixed test-first; core/detail matrices and expanded Admissions journey passed; final gates/review pending |
| 05 Enrollment/Grading/progress polish | Implemented; final verification pending | Paired Waiver/loading/identifier/count corrections verified; expanded owner journeys and final gate/review pending |
| 06 Records polish | Implemented; final verification pending | Paired loading corrections, service journey, four-state read/print and invalid public verifier passed; expanded action-state checks and final gate/review pending |
| 07 Acceptance | Implemented; final verification pending | Exact-population/export headline passed; reproducible demo and audit recorded; complete gates/consolidated review pending, human acceptance required |

## Commands and results

Complete backend gate passed (1,329 tests / 8,421 assertions; details below).
Complete frontend gate and consolidated review remain pending. Starting commit and
initial clean worktree verified.

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

### Booked worklist URL controls and shared collection registry

- A red frontend HTTP-boundary test proved booked criteria were ignored and controls
  absent. Booked worklist now consumes its own `booked_*` URL state, preserving
  awaiting criteria independently; supports applicant search, confirmation/mode,
  scheduled/score bounds, sort, paging and authenticated full-selection export.
- Existing awaiting tests now scope repeated labels/export controls to their named
  region. New test covers booked criteria, Back, filtered blob download and preserving
  `awaiting_page`. **2 files / 5 tests passed, 11.86s**.
- Both Interview endpoints now have shared collection-contract dataset rows, proving
  unknown-key rejection, outside filters, private phone search exclusion and truthful
  pagination within their original actor scope. Focused backend regression:
  **36 passed, 240 assertions, 8.61s**. Pint passed.
- Frontend typecheck, lint and formatting passed. Headline journey now searches its
  unique Applicant in assigned and booked paginated worklists. Serial browser run
  includes headline, awaiting EN/AR and booked/assigned EN/AR criteria/download/axe;
  results pending. No full package gates or consolidated review claimed.

Serial Interview browser verification: **7 passed, 3.4m** — complete admissions
headline plus awaiting/booked/assigned EN/AR bookmarked criteria, reload, authentic
workbook downloads and serious/critical axe checks. This closes the Interview
collection slice; slot pool/pickers, Catalog type-ahead, direct Faculty criterion,
Pipeline/print prerequisites and all seven Phase 7 acceptance items remain pending.

### Shared bookmark display fidelity

- Test-first reproduction proved preset dropdowns rendered valid bookmarked
  comma-separated filters and compound sorts blank (MUI out-of-range warning),
  while the backend still applied them. FilterBar now includes the current value
  when it is outside presets, displaying constituent owner labels without changing
  the URL or API criteria. Ordinary preset selection/clearing remains unchanged.
- Focused verification: **4 files / 9 tests passed, 16.87s**, including shared
  filter/hook and both Interview worklists. EN/AR Interview browser checks were
  expanded to use multiple confirmation/mode values and compound sorts; pending.

Expanded browser first run failed: the sort assertion still expected the old
single-field value although the UI correctly retained `-scheduled_at,id`; an
assigned login also timed out without a rendered error snapshot. Stopped that run,
corrected the outdated expected value and reran the serial four-test selection.
Frontend typecheck/lint/full format passed for the shared fix. Final browser result
is pending; the failed/interrupted run is not counted as passing evidence.

Final expanded serial browser rerun: **4 passed, 1.0m**. Both booked and assigned
worklists visibly preserve multiple confirmation values and compound sort bookmarks
in EN/AR, download authenticated workbooks and pass serious/critical axe checks.
The earlier login timeout did not recur. Shared fix static and focused tests remain
green. Full Phase 7 gates, print/layout verification and final consolidated review
are still pending behind the remaining approved implementation frontier.

### Interview slot pool / personal picker backend query contract

- Continued executable prerequisite #06. Red HTTP tests proved the pool ignored
  search/availability/page criteria, personal picker had no page metadata, and
  manager export was absent. Both collections now use Admissions-owned selection,
  normalized bilingual location search, opaque IDs, mode, scheduled/duration bounds,
  derived availability filtering and stable SQL pagination. Availability reuses the
  existing owner `open` scope and current booking relation; no stored capacity flag.
- Pool defaults to 25; personal lookup defaults to 100 and always retains its base
  open-only constraint, so `is_open=false` or a booked slot ID gives an empty page.
  Applicant export remains 404. Manager export independently enforces scheduling
  permission, mirrors full selection across pages, resolves staff through bounded
  Public Directory DTOs, localizes canonical labels/availability and RTL, keeps IDs
  strings and records effective criteria/count audit evidence. Meeting links are not
  export columns or search fields.
- Shared collection dataset now includes both slot endpoints, covering unknown-key
  rejection, private link search exclusion, outside filters and truthful pagination.
  Focused backend **36 passed, 237 assertions, 6.58s**, including existing pool
  mutations and Applicant confirmation/rescheduling. Pint/PHPStan/deptrac passed
  after correcting a redundant null-safe call in the authorized export audit path.
- Controllers document all parameters and paginated shapes; OpenAPI/client/Zod
  regenerated. A red ScheduleDialog HTTP test caught the old generated-hook options
  being sent as query criteria. Scheduling now explicitly requests 100 open slots;
  personal picker options moved to the new argument position. Focused frontend
  **3 files / 6 tests passed, 17.21s**. Static and serial headline verification active.
- Slot-pool URL controls/export UI and both slot-picker type-aheads are still pending,
  together with staff/Catalog pickers and direct Faculty criterion. This checkpoint
  does not complete #06 or any full Phase 7 acceptance/gate/review item.

Slot API checkpoint frontend typecheck/lint/full format **passed**. Serial complete
admissions headline **1 passed, 1.5m**, retaining slot creation, open-only scheduling,
interviewer evaluation, completion and acceptance. Slot-pool EN/AR controls/export
browser evidence will be gathered when that UI slice is implemented. Full Phase 7
completion/gates/print audit/consolidated review remain pending.

Commit `8a2e0bf`: sequential `composer api:check` and `npm run api:check`
**passed**, with no generated drift. Next frontier remains slot-pool URL controls,
slot-pickers type-ahead (including selections beyond the first 100), staff/Catalog
pickers and direct Dean Faculty criteria; then Pipeline/print and Phase 7 Reports.

### Slot-pool URL controls and authenticated export UI

- Red HTTP-boundary UI tests demonstrated absent slot search and export controls.
  Slot pool now consumes owner-generated hooks with URL location search, opaque
  interviewer IDs, mode, derived availability, scheduled/duration bounds, compound
  sorting and truthful pagination. Numeric bounds are sent as numbers, including
  export criteria. Changing filters resets page; browser Back restores criteria.
- Added owner EN/AR labels together and reused shared controls/design tokens. Export
  fetches a Blob through the authenticated generated client and names the workbook
  `interview-slots.xlsx`. Existing create/edit/delete actions retain their API guards.
- Focused frontend regression: **4 files / 8 tests passed, 25.12s**, covering slot
  pool, scheduling picker, independent worklists and compound bookmarks. Typecheck,
  lint and full frontend formatting passed. No backend or generated contract change
  in this slice; previous slot API verification remains recorded above.
- Serial headline plus EN/AR slot bookmark/reload/download/axe verification active.
  Slot type-aheads and staff/Catalog picker adoption/direct Faculty criterion remain
  pending prerequisites. Full seven-ticket Phase 7 implementation/gates/print audit
  and the single consolidated final code review remain pending.

Slot-pool serial browser verification **3 passed, 2.2m**: full admissions headline
plus EN/AR bookmarked multi-value filters and compound sort, numeric duration
criterion reload, authenticated workbook downloads and serious/critical axe scans.
No final package gate or manual print/layout acceptance is claimed by this result.

### Scheduling and Applicant slot type-aheads

- Red HTTP-boundary tests reproduced the non-searchable selects in both staff
  scheduling and Applicant rescheduling. Both now use owner-server Autocomplete
  queries with a 100-row reference page, retain an explicitly selected slot DTO,
  and disable local filtering so Arabic owner-normalized matches remain available.
  Scheduling always requests open-only slots; Applicant lookup retains its existing
  server base constraint. Selecting does not auto-select the first result.
- Selection beyond the first page is covered by a 101-row metadata scenario where
  Arabic lookup returns slot 207; both actions submit that owner ID. Both EN and AR
  cases pass. Clearing restores the baseline query; stable slot IDs key options.
  Search/load/error and no-matching messages remain distinct from no available slots.
  Added the shared Admissions no-matching message in English and Arabic together.
- Focused frontend regression **4 files / 10 tests passed, 24.39s**, including both
  type-aheads, independent scheduling collections and slot-pool URL/export controls.
  Frontend typecheck/lint/format passed; affected test typecheck/format rerun active
  after adding bilingual test cases. No backend or generated API change in this slice.
- Headline now owns two capacity-one slots, searches the scheduling reference by its
  unique location, and the new Applicant searches/reschedules into the second slot,
  exhausts their reschedule budget and confirms attendance before the existing
  evaluation/completion/acceptance journey. Serial browser verification active.
- Staff directory pickers, Catalog type-aheads and direct Faculty filter remain #06
  frontier; Pipeline/print prerequisites and all seven Phase 7 acceptance/final gates
  and the one consolidated final review remain pending.

Type-ahead follow-up TDD: choosing a slot and then typing a new search left the old
choice submit-enabled. EN/AR tests reproduced this in both pickers. Separate visible
input state now retains typed search while invalidating the previous choice; actions
remain disabled until a new owner result is explicitly selected. The selected DTO
still survives ordinary query refreshes. Focused rerun **4 files / 10 tests passed,
19.31s**. Affected static and serial browser checks rerun after this fix; the earlier
expanded headline/browser run passed **3 tests, 2.4m**, before this final adjustment.

Final slot type-ahead verification after the stale-selection fix: frontend
**typecheck/lint/full format passed**. Serial expanded admissions headline plus
EN/AR slot-pool bookmarks/downloads/axe **3 passed, 2.3m**. Applicant search,
reschedule-budget exhaustion, confirmation and subsequent staff completion/acceptance
remain executable. #06 still needs staff/Catalog type-aheads and direct Faculty
criteria; all seven Phase 7 Report/polish acceptance and final gates/review remain
pending. No API artifacts changed in this UI-only slice.

### Staff reference collection contract

- Interviewer and advisor references now use role-constrained Identity Public
  pagination DTOs, with normalized name/email search, ID criteria, stable multi-sort
  and a 100-row default/cap. Admissions never reads Identity tables directly.
  The explicit Identity Public-to-Support edge permits the shared query value DTO.
- HTTP tests first reproduced ignored criteria and missing pagination. Both endpoints
  now preserve their backend gates and role base constraints, reject unknown fields,
  keep private tokens outside search and expose no export route. Shared contract
  registry covers tied adjacent pages and cross-role ID probes.
- Advisor suggestion remains the owner's global least-loaded result, independently
  of filtered choices. Its documented nullable type exposed an existing UI bug:
  a null suggestion enabled acceptance. A focused failing test preceded the explicit
  null guard; absence now leaves acceptance disabled.
- Focused backend rerun: **55 tests / 376 assertions passed, 12.40s**. Focused frontend:
  **3 files / 6 tests passed, 17.37s**. Frontend typecheck/lint/full format passed.
  Backend Pint/PHPStan/deptrac passed (zero boundary violations). Serial headline
  rerun is pending at this checkpoint.
- OpenAPI and Orval client regenerated from controllers/resources. Slot-form caller
  now supplies lookup parameters in the regenerated hook's first argument; the
  HTTP-boundary test first failed on the misplaced query options before the fix.
  Post-commit generated drift verification remains pending.
- Staff type-ahead UI, Catalog parent references and direct Faculty criterion still
  remain #06 work. This slice does not complete #06 or any Phase 7 ticket; Pipeline,
  print, all seven ticket acceptance checks, final gates and consolidated review
  remain required.

Staff collection contract checkpoint committed as `fe9e1e3`. Post-commit backend
`composer api:check` and frontend `npm run api:check` passed. Serial admissions
headline after the nullable-advisor fix **1 passed, 1.7m**.

### Interviewer type-ahead

- New EN/AR tests first failed because the slot-form select could not accept search
  text. Its Autocomplete now sends normalized search to the owner's reference API,
  disables local filtering and retains the explicitly selected owner DTO/ID.
  Typing a new query invalidates the prior choice. Existing assignments hydrate by
  ID through the same scoped owner API, including assignments beyond page one.
- Focused regression **3 files / 8 tests passed, 21.27s**. The additional existing
  assignment hydration check passed with the full slot-form file **4 tests, 11.83s**.
  Loading/no-match text added in EN and AR together. Headline now searches for its
  named interviewer before creating both run-owned slots.
- Static and serial browser reruns pending. Advisor type-ahead, Catalog references
  and Faculty criterion remain prerequisite work; no final ticket/gate/review
  completion is claimed.

Interviewer final verification: enhanced EN/AR tests prove a new query disables Save
until another explicit selection, and the submitted mutation carries owner ID 207.
Slot-form file **4 tests passed, 18.71s**. Frontend typecheck/lint/full format passed;
serial expanded admissions headline **1 passed, 2.0m**, exercising staff interviewer
search, both slot creations, Applicant reschedule/confirmation and final admission.
No API changes in this UI slice. Advisor/Catalog/Faculty prerequisites, Pipeline,
print and all Phase 7 acceptance/final gate/review requirements remain pending.

### Advisor type-ahead

- EN/AR HTTP-boundary tests first reproduced the blank out-of-range suggestion
  when the least-loaded advisor lies beyond the first 100 rows. The form now
  hydrates that suggestion through the scoped owner reference ID query and uses
  server search without local filtering. The baseline response supplies global
  availability; an empty search does not disable the field or mean no advisors.
- Explicit search/clear invalidates selection and disables acceptance. A one-way
  edited flag prevents async suggestion refresh from overwriting the Dean's search
  or override. Selected DTOs remain available independently of search results.
- EN/AR tests cover suggestion 207, empty search, Arabic-normalized advisor lookup,
  explicit override and mutation advisor 309. Existing no-advisor and reason tests
  remain green: **5 tests passed, 12.09s**.
- Paired loading and no-match messages added; API contract unchanged. Frontend static
  and serial headline reruns pending. Remaining prerequisite and final Phase 7
  gates/review requirements are unchanged.

Advisor final verification: frontend **typecheck/lint/full format passed**; serial
expanded admissions headline **1 passed, 1.8m**, including owner advisor preselection
and final acceptance. No generated artifacts changed. Faculty criterion work has
started test-first; remaining Catalog references/Pipeline/print and Phase 7
acceptance/final gates/consolidated review still remain.

### Application Faculty criterion

- HTTP test first reproduced `faculty_id` returning 422. The shared Application
  selection now validates Faculty ID tokens, resolves only scoped candidate Program
  IDs through bounded Catalog Public reads and narrows the result. No Catalog joins,
  cross-owner foreign keys or widening of any actor base scope were introduced.
  Both assigned Faculties combine with CSV criteria; an outside Faculty returns
  an empty page, including when the Dean also holds Student Affairs.
- Workbook read-back verifies the Faculty-filtered selection and audit criteria/row
  count. Focused backend **36 tests / 178 assertions passed, 8.09s**. Backend
  PHPStan/deptrac passed; affected Pint check passed after documentation updates.
- Source controllers document the criterion for screening, Dean review/decisions
  and awaiting-interview collections/exports. OpenAPI and Orval client regenerated.
  Four URL filter bars expose the paired EN/AR label; test first failed on the
  absent control, then passed with filter/reset-page/export forwarding coverage.
  Focused frontend **4 files / 10 tests passed, 21.83s**.
- Frontend static and EN/AR Dean browser bookmark/download/axe reruns pending.
  Post-commit API drift checks remain required. Catalog type-aheads, Pipeline and
  print prerequisites, all Phase 7 ticket acceptance and final gates/review remain.

Faculty final focused verification: frontend **typecheck/lint/full format passed**.
EN/AR Dean review and decisions bookmarks retain comma-separated Faculty criteria
through reload and authenticated workbook downloads; serious/critical axe scans
passed: **2 browser tests, 53.7s**. Final package gates and consolidated review have
not run. Post-commit generated drift checks are next.

Faculty criterion checkpoint `9514dda`: backend `composer api:check` and frontend
`npm run api:check` passed after commit. Advisor type-ahead checkpoint `6107ff7`
also remains committed. No final gate/review completion is claimed.

### Catalog Faculty type-ahead

- Department form EN/AR HTTP tests first failed on its non-searchable Faculty
  select. The owner feature now supplies a reusable Faculty picker requesting
  server-normalized search with a 100-row reference page and no client filtering.
  Selection submits Faculty 207 after lookup beyond the first page.
- Existing Faculty ownership hydrates through the already-authorized Faculty
  detail route. Typecheck exposed that the collection has no `id` criterion; a
  red existing-owner test preceded using its generated detail hook. No generated
  contract was weakened or hand-edited to bypass the unsupported criterion.
- Search/create plus existing-owner tests **3 passed, 10.62s**. Frontend
  typecheck/lint/full format passed. Enhanced EN/AR tests additionally cover
  invalidating the prior Faculty and disabling Save during a new search; rerun
  pending. EN browser criteria/download/new-Department Faculty search/axe passed;
  full EN/AR browser run pending.
- This migrates only the Department form. Other Catalog parent and curriculum
  references remain #06 work; Pipeline/print prerequisites and all Phase 7 ticket
  acceptance/final gates/consolidated review are still required.

Faculty picker follow-up: enhanced EN/AR tests reproduced MUI reset wiping a new
typed query after a selection. Input state now changes on explicit input/clear;
selected owner labels hydrate separately. Both languages retain the new query and
disable Save until another explicit choice. Focused **3 tests passed, 13.80s**.
Added paired visible/accessible loading, no-match, error, clear/open/close copy.
The earlier browser run passed **2 tests, 1.1m** before this adjustment; affected
static and serial browser reruns remain pending after the fix.

Faculty picker final verification after the reset fix: frontend
**typecheck/lint/full format passed**; EN/AR Catalog criteria/downloads and
new-Department Faculty lookup/selection with serious/critical axe checks
**2 browser tests passed, 1.1m**. API artifacts unchanged. Other Catalog pickers
and the remaining prerequisite/ticket/final gate/review requirements remain pending.

### Program Department type-ahead

- EN/AR tests first reproduced the blank out-of-range existing Department and
  non-searchable Program form select. The owner feature's Department picker now
  uses the existing detail route for ownership hydration and the normalized
  collection search with a bounded 100-row reference page. Local filtering stays
  disabled; bilingual display and accessible controls reuse Catalog message keys.
- Tests hydrate Department 207, retain a new Arabic search while invalidating its
  old selection, disable Save until explicit selection and PATCH owner ID 309.
  Program and Department form regression **2 files / 5 tests passed, 16.12s**.
  Frontend **typecheck/lint/full format passed**.
- Expanded serial Catalog browser exercises new Program Department search and
  choice in EN/AR alongside the existing Faculty form, criteria/download and
  serious/critical axe checks: **2 passed, 1.1m**. No API contract change.
- Course creation/edit and curriculum references remain next #06 work; Pipeline,
  print and all Phase 7 ticket/final gate/consolidated review requirements remain.

### Course Department type-ahead

- EN/AR HTTP tests first reproduced the Course form's blank existing Department
  beyond page one. It now uses the same owner detail/search seam as the Program
  form. Search invalidates the prior choice and disables Save; no search text is
  mistaken for a Department or auto-selects a result.
- University-wide ownership remains explicit and distinct from an unresolved
  search. A labelled local option represents the existing nullable owner value;
  selecting it submits the existing payload with Department omitted. Its internal
  option key never goes to the server. A callback lets the Course form keep Save
  disabled while searching even though its Department is optional.
- Focused Catalog form regression **3 files / 7 tests passed, 21.27s**. Expanded
  Course EN/AR Department and University-wide submission checks **4 tests passed,
  13.61s**. Frontend **typecheck/lint/full format passed**. No API changes.
- Serial EN/AR Course and Catalog reference browser rerun pending after the optional
  owner extension; curriculum references and remaining prerequisite/ticket/final
  gate/consolidated review requirements remain outstanding.

Course final browser verification after the optional-owner extension: serial
EN/AR Course and Catalog reference tests **4 passed, 1.9m**. Course dialogs exercise
Department server search and explicit University-wide selection with serious/
critical axe checks; existing Faculty/Program choices, bookmarks and authenticated
downloads remain executable. Curriculum reference migration is next. No API or
final gate/review completion is claimed.

### Curriculum Course type-ahead

- EN/AR generated-HTTP tests first reproduced Course search being confined to the
  first collection page. Each Category now owns independent URL-free reference
  search state with a 100-row owner query, preserving whole Curriculum reads and
  existing Preparatory Role/duplicate-exclusion semantics. No aggregate export or
  duplicate curriculum business rule was introduced.
- Selected DTOs remain explicit; typing invalidates the previous Course and
  disables Add. Server Arabic normalization is preserved by disabling client
  filtering. Paired Catalog loading/error/no-match/accessibility messages apply.
- Focused existing curriculum, new reference and Catalog form regression
  **4 files / 16 tests passed, 26.33s**. Frontend **typecheck/lint/full format passed**.
- Expanded browser initially failed on a mistaken code-link locator before it
  reached the curriculum. The existing row-level Edit link is now used; EN/AR
  browser rerun is pending. No browser pass is claimed for curriculum yet.
- Remaining reference name resolution, Pipeline/print and Phase 7 acceptance/final
  gates/consolidated review remain required.

Curriculum final browser rerun after correcting the existing Edit-link locator:
**2 EN/AR tests passed, 1.2m**. Owner Course lookup and selection enable Add; a
new search invalidates the choice and disables Add. Criteria/downloads and
serious/critical axe scans remain green. A test-first follow-up now reproduces
Course detail silently losing its owning Department name beyond the first page;
that read-only lookup migration is next. No API contract changed in this slice.

### Course detail owner lookup

- EN/AR HTTP tests first reproduced the owning Department name being omitted when
  it lies beyond the first reference page. Course detail now fetches its exact
  opaque owner ID through the existing authorized Department detail hook; nullable
  University-wide ownership keeps its existing meaning. It no longer scans an
  unrelated reference page for the header label.
- Course detail/form and curriculum regression **4 files / 16 tests passed,
  25.38s**. Frontend **typecheck/lint/full format passed**. No API changes.
- EN Course browser passed, including live owner header and serious/critical axe
  checks; full EN/AR browser rerun pending. Paginated grid parent-name resolution
  remains a separate follow-up before prerequisite #06 is treated as complete.
  Pipeline/print, Phase 7 ticket acceptance and final gates/review remain pending.

Course detail final browser verification: EN/AR live owner headers, Course dialog
reference choices, retained query criteria, downloads and serious/critical axe scans
**2 tests passed, 55.8s**. No API artifacts changed. Parent labels on paginated
Catalog grids remain the next prerequisite follow-up; no final gate/review claim.

### Catalog grid parent references

- Exact-ID HTTP tests first reproduced unsupported reference IDs (422). Catalog's
  own Faculty/Department/Program collections now accept validated positive ID CSV
  filters; their common stored selection, totals and export share that criterion.
  Existing `catalog.view` authorization stays intact, including Applicants under
  the established Permission matrix. No cross-owner tables or joins were added.
- Six EN/AR page tests first reproduced missing grid parent names beyond page one.
  The grids keep the initial reference choices and fetch only missing parent IDs
  from their visible rows in a bounded 100-row request. Ordinary pages whose
  parents are already present need no extra request. Filter choices are preserved.
- Backend **44 tests / 241 assertions passed, 7.84s**, including reference-ID
  validation, empty unknown IDs, read roles, workbook read-back and effective ID
  audit criteria. Backend Pint/PHPStan/deptrac passed. Frontend focused **3 files /
  10 tests passed, 15.85s**; typecheck/lint/full format passed.
- Controllers document the ID criterion; OpenAPI and Orval regenerated. Serial
  Course/Catalog EN/AR dialogs, lookup choices, criteria/downloads, owner headers,
  curriculum reference selection and serious/critical axe checks **4 passed, 2.2m**.
  Post-commit API drift checks remain next. Final package gates/review are pending.
- Pipeline Report HTTP tracer now fails with 404, proving the aggregate facade alone
  does not deliver prerequisite #08. Its owner API, exact all-status drill-through,
  Analytics composition and subsequent print work remain required before Phase 7
  Report delivery; all seven ticket acceptance/final gates/review remain pending.

### Pipeline owner HTTP and ordinary stage drill-through

- Catalog parent-reference post-commit backend/frontend API drift checks passed.
- The Pipeline HTTP tracer first failed with 404. Admissions now exposes its
  existing owner aggregate through an `application.screen`-gated Report endpoint.
  Counts include all zero stages and conversion rates retain null denominators.
  Scope follows live Dean assignments and the Student Affairs union; parameters
  narrow that scope and do not cap the aggregate by pagination. Unknown criteria
  are rejected, and the payload contains domain values rather than display copy.
- The next HTTP tracer exposed accepted-stage drill-through returning zero rows.
  Explicit status selection now reaches every Application stage in the ordinary
  actor-scoped list and its canonical export. With no status selection, the
  default remains Under SA Review. Workbook read-back verifies the exact selected
  ID. Mixed-stage derived filtering and stable pagination also passed; no sorting
  implementation change was needed after verification.
- Focused backend **29 tests / 212 assertions passed, 6.42s**; Pint, PHPStan and
  deptrac passed. OpenAPI and Orval regenerated. Scribe's inferred GET body was
  removed by validating query input explicitly and marking that validation with
  Scribe's query-parameter annotation. The first generated client failed typecheck
  on its inferred unused body argument; regeneration removed that argument.
  Final backend focused **7 tests / 80 assertions passed, 3.93s** and PHPStan/Pint
  rechecks passed. Frontend generated-client typecheck passed.
- Prerequisite #08 remains incomplete: Analytics composition, the Report parameter
  bar/chart/table and EN/AR browser drill-through are still required. Print #15,
  all seven Phase 7 acceptance, full gates and consolidated final review remain
  pending. No final gate or review has been claimed.

### Pipeline initial Analytics page

- Pipeline backend/frontend post-commit API drift checks passed for `ff0838e`.
- The first frontend tracer failed on the absent owner Report page. Admissions
  now renders owner status counts through the shared Chart, with identical
  accessible table drill URLs and a separate measured-conversion table. EN/AR
  labels, numeric LTR isolation, null-denominator copy and API error/retry are
  present; Cycle, Program and degree criteria use the shared URL FilterBar.
- The navigation tracer failed on the absent Analytics section. App composition
  now supplies a permission-filtered owner Report item and a matching protected
  route; no feature-to-feature import or Reporting module was introduced.
- Focused frontend **2 files / 16 tests passed, 9.24s**, including EN/AR bookmarked
  numeric criteria, chart/table values and exact keyboard-operable stage URLs,
  plus real navigation visibility. Typecheck, lint and full format passed.
- This is an initial vertical slice, not prerequisite #08 completion. Human-readable
  parameter choices, language-switch/Back coverage and live EN/AR browser
  drill/export/accessibility verification remain next. Print and Phase 7 ticket
  delivery/full gates/consolidated review remain pending.

### Pipeline named parameter choices and live stage journey

- EN/AR tests first reproduced missing names for bookmarked Cycle/Program IDs
  beyond the first reference page. The Report now searches the owning collections
  in bounded 100-row requests and resolves selected opaque IDs separately. Shared
  FilterBar accepts owner-supplied reference choices, selection, query state and
  localized accessibility copy; it has no Admissions/Catalog rules or local
  filtering. Owner-normalized Arabic queries can select English-labelled results.
- URL/history coverage exposed MUI clearing the effective Program criterion while
  only its lookup text was being cleared. Typing now leaves the Report selection
  intact; choosing a result or the explicit clear button changes the URL. An empty
  search is distinct from displaying a selected label. EN/AR language switches and
  Back restore selection without losing Cycle/Program/degree criteria.
- Ordinary drilled Applications now show their selected status criterion and each
  row's owner status. Copy describes matching Applications rather than claiming
  every stage is waiting for screening. Missing status-entry age is unavailable,
  not fabricated as zero days. Focused tests first reproduced these omissions.
- Frontend **3 files / 9 tests passed, 15.96s**; typecheck/lint/full format passed.
  The browser harness initially failed because direct session HTTP lacked Sanctum's
  required frontend Referer; matching the SPA's stateful request fixed the harness.
  Live serial Student Affairs/Dean EN/AR **4 tests passed, 1.5m**, proving all 13 table
  stages, exact keyboard drill URL, API/list count parity, authenticated workbook,
  Back, owner parameter names and serious/critical axe checks. Screenshots inspected.
- The chart's axis skips some long stage labels at desktop density. A focused
  layout follow-up is next before print. Prerequisite #08 full gates and #15 second
  Report print verification remain pending alongside actual Phase 7 acceptance.

### Pipeline horizontal layout and executable Report printing

- Native browser checks first reproduced an omitted long status label and clipped
  Arabic numeric-axis zero. Horizontal bars now retain all 13 stage labels and
  reserve enough numeric-axis height in RTL. Chart/table data and drill URLs remain
  identical; both keyboard table links and actual mouse bars reach the scoped queue.
- Shared ReportDocument prints title and effective named criteria, chart and table
  with A4 margins, readable monochrome tokens and sensible block breaks. Printed
  criteria use selected owner IDs, never unfinished lookup text. Tests reproduced
  SVG screen-color leakage and dark paper margins; print-specific SVG colors and
  the document light color scheme resolve both without changing screen themes.
- Focused frontend **4 files / 10 tests passed, 21.60s**. Typecheck, lint and full
  format passed after the component type correction; subsequent print-only CSS
  refinements passed focused ESLint and Prettier. Latest serial native Pipeline
  journeys **4 passed, 1.5m**, covering Student Affairs/Dean, EN/AR, light/dark,
  exact scoped drill/list/export, serious/critical axe and print assertions.
- Generated actual A4 PDFs were inspected with native Codex image tools: title and
  complete chart on page 1, complete stage table on page 2, conversions on page 3.
  Final dark Arabic paper is white with legible RTL text and isolated numeric values.
  These are automated/Codex inspections, not human signoff. Artifacts regenerate
  under ignored Playwright test-results; no product PDF dependency was introduced.
- Prerequisite #08 executable journey is verified; final package gates remain for
  verification pass 2. Prerequisite #15 has executable shared printing verified on
  Pipeline; its required second Report verification will accompany the first
  Academic Standing Report. No planning merge is being counted as implementation.
  All seven Phase 7 acceptance, full gates and consolidated review remain pending.

### Academic Standing scope union tracer

- Phase 7 #01's first access tracer reproduced HTTP 403 for a Dean/Advisor's
  Faculty Student outside their advisee set. The existing individual Standing scope
  returned early on an unsuccessful Advisor match. It now continues to the other
  eligible scopes, preserving the role union and existing self-read behavior.
- HTTP checks cover both union branches, an outside Student, removed Dean assignment
  and reassigned advisee. Focused Standing tests **3 passed / 22 assertions, 3.20s**;
  Pint, PHPStan and deptrac passed. No API shape changed.
- This is supporting access behavior, not completion of #01: cohort APIs, bounded
  owner Standing batches, UI/export and second Report printing remain next.

### Owner effective-Attempt batches for current Standing

- The public Grading tracer first failed on the missing batch method. Grading now
  exposes effectiveAttemptsForStudents through its existing bounded StudentGpaTally;
  single reads delegate to the same selection. AcademicStanding will consume DTOs,
  not reinterpret Grade supersession or earned-credit rules.
- Worked Retake/draft/empty/duplicate-ID checks passed with existing Standing tests:
  **15 tests / 48 assertions, 4.19s**. Seeded 180-Student CGPA and effective-Attempt
  batch/single parity **1 test / 364 assertions, 18.57s**. Pint, PHPStan and deptrac
  passed. This is an internal Public contract addition, with no HTTP API change.
- Current Standing batch implementation is next; #01 cohort delivery remains pending.

### Current Standing owner batches

- The batch Public tracer first failed on the absent currentForStudents method
  after correcting its required Observation fingerprint fixture. StandingRecords
  now acquires bounded owner CGPA/effective-Attempt DTOs and read-only open Cases,
  then reuses the existing StandingCalculator and summary/explanation mapping.
  Single-Student calls retain their existing owner contract calls, preserving
  operational callers and enabling independent batch/single comparisons.
- Worked grades exercise all five Standing bands, null versus zero, effective D
  warning at CGPA 3.00 and open-Case precedence. Stale GoodStanding Observations
  and dismissed Cases do not override current calculation. Seeded 180-Student
  parity crosses batch boundaries without creating Observations or Cases.
- Existing Council termination regression initially exposed changed mock call
  expectations; preserving the single-read calls resolved it. Focused operational
  tests **11 passed / 82 assertions, 3.99s**. Final owner batch tests **3 passed /
  208 assertions, 18.41s**. Pint and PHPStan passed; deptrac passed with zero
  violations. No HTTP payload or generated API change in this slice.
- #01 cohort selection/API, scoped derived pagination, canonical audited workbook
  and UI remain required. These owner batches do not mark the ticket complete.

### Identity-owned cohort candidates and live Department Head scope

- Public facade tests first failed on absent studentIdsMatching and
  departmentsForHead methods. Identity now applies its stored Program/lifecycle
  filters and normalized bilingual name/University-ID search in SQL, retaining
  stable order without applying pagination before derived selection. Active is
  the default; explicit Terminated criteria permit history. The candidate method
  is unscoped by contract; AcademicStanding must authorize before any calculation
  or serialization. Private identity snapshots are not Report payloads.
- Live Head assignments return academic Department IDs; university-wide null
  Preparatory scope remains disjoint and cannot widen cohort access. Assignment
  removal is reflected on the next call. Focused **8 tests / 12 assertions passed,
  3.00s**; Pint passed. Final PHPStan/deptrac results recorded below before commit.
- PHPStan and deptrac passed (zero violations). Phase 7 Report HTTP tracer is next;
  no cohort endpoint or ticket completion is claimed by this prerequisite slice.

### Academic Standing Report summary HTTP tracer

- The Report HTTP tests first failed with 404. AcademicStanding now owns a current
  Standing summary endpoint behind standing.monitor plus eligible cohort Role
  authorization. A reusable owner cohort selection acquires Identity/Catalog DTOs
  in 100-ID batches, applies live Advisor/Dean/Head union or global oversight,
  then derives Standing only for authorized candidates. No foreign Internal SQL,
  stored standing balance or Reporting module was added.
- Active default and explicit Terminated history remain separate from the five
  Standing bands. Faculty/Department/Program/degree/search only narrow scope.
  Zero bands are retained; summary totals ignore page size. Tests cover all global
  oversight Roles, Advisor/Dean union and reassignment, academic Head versus null
  Preparatory scope, bilingual normalization, cross-scope empty results, denied
  Student/Instructor Roles and unknown/invalid criteria.
- Focused HTTP **8 tests / 41 assertions passed, 3.63s**; Pint, PHPStan and deptrac
  passed. OpenAPI and Orval regenerated; generated diff contains only the new query
  endpoint/types, with no inferred GET body. Frontend typecheck is pending before
  commit. Pageable List, derived ordering, canonical audited export and owner UI
  remain next; this summary slice does not complete #01.
- Frontend generated-client typecheck passed. Student List HTTP tracer is written
  separately; it will be implemented in the next vertical slice.

### Current Standing Student List and canonical workbook

- List and export HTTP tracers first failed with 404. Summary, pageable List and
  full-selection export now share one AcademicStanding-owned selection. Derived
  Standing filtering and CGPA/severity ordering precede slicing; nullable values
  remain last in either direction and stable Student IDs break ties. Stored-only
  ordering remains Identity SQL order through bounded DTO resolution.
- Safe rows contain names, University ID, Program, lifecycle, nullable CGPA,
  authoritative Standing/explanation and explicit missing-owner reference codes.
  They never serialize the private Student identity/admission snapshot. Worked
  owner Grades prove derived pagination and stale Observation protection; a
  120-Student HTTP fixture verifies complete totals, the 100-row cap and stable
  page boundaries. Repeated reads preserve an overdue Incomplete and create no
  Grade conversions, Cases, Observations, notifications or lifecycle changes.
- EN/AR workbook read-back proves all filtered authorized rows despite per_page=1,
  localized headings/Standing, string Student/University IDs, hidden National IDs,
  and Arabic sheet direction. Export audit records actor, effective criteria
  including default Active lifecycle, and row count. The initial XML assertion
  expected boolean spelling 1; inspecting the workbook showed valid true, so the
  test now checks either legal XML boolean representation.
- Permission tests explicitly allow standing.monitor at the permission seam for
  Student/Instructor-only personas and still obtain 403 from the cohort Role guard.
  Focused HTTP **12 tests / 122 assertions passed, 5.81s**; Pint, PHPStan and deptrac
  passed. Generated API regeneration exposed the local documentation type override
  treating array annotations as scalar strings. A truthful missing-name example
  now lets Scribe infer the unavailable string array; nullable names, Program and
  CGPA remain explicitly documented. No generated file was hand-edited.
- OpenAPI and Orval regenerated; final generated-client typecheck is pending before
  commit. Owner UI, named academic filters, chart/table drill-through, browser and
  second Report print verification remain required for #01. Final gates and the
  single consolidated review remain pending across all seven tickets.
- Final regenerated client has unavailable string-array and nullable academic types;
  frontend typecheck passed. Post-commit API drift checks follow this slice.

### Initial owner Academic Standing Report page

- Backend/frontend post-commit API drift checks passed for the Standing List/export
  slice. The first frontend tracer failed on the absent Report page. AcademicStanding
  now renders all five owner counts through the shared horizontal Chart and its
  identical accessible table, within the executable shared ReportDocument.
- Faculty/Department/Program criteria search their owner collections in bounded
  pages and resolve bookmarked opaque IDs separately; labels switch language without
  losing criteria. Degree, lifecycle, Standing and bilingual name/University-ID
  criteria use the URL. Print criteria describe effective selections, including
  default Active lifecycle. Only nonzero bands link to a narrower Student List,
  preventing a zero band excluded by an existing Standing filter from widening it.
- Focused EN/AR tests **2 passed, 13.06s**, covering named selected references beyond
  the first reference page, exact chart/table count and URL, language switch,
  lifecycle selection/Back and actual keyboard Enter. The extended history test
  initially tried to focus a table link removed during loading; reacquiring the
  current table after Back corrected the harness. No product behavior was changed
  to accommodate stale DOM references.
- Frontend typecheck, lint and full format passed on the page implementation;
  focused ESLint/Prettier rechecks follow the final history harness edit. This page
  is not yet composed into the shell: the ordinary Student List and matching
  protected routes/navigation are next, followed by native browser/second Report
  print verification. #01 and all subsequent ticket acceptance remain pending.
- Final focused ESLint/Prettier rechecks passed before the page commit.

### Standing Student List, protected shell routes and native verification

- The ordinary Student List shares the Report's URL criteria, owner references and
  effective lifecycle. It renders nullable CGPA explicitly, localized neutral
  explanations, safe Program data and permission-gated grade-detail links. Sorting
  and pagination stay in the URL; workbook download exports the complete selection.
- Protected Report/List routes and navigation require standing.monitor plus an
  eligible academic Role. Focused tests first failed on the absent List/navigation;
  final Report/List/SideNav run passed **27 tests across 3 files, 26.93s**. Frontend
  typecheck, lint and full format passed. Native test code passed Prettier; the
  repository intentionally excludes e2e files from ESLint.
- Native serial Standing journeys passed **4 tests, 1.6m**: Student Affairs and Dean
  in EN/AR, light/dark themes. They check scoped owner counts, named criteria,
  keyboard table drill-through, matching pageable rows, authorized grade-detail
  access, workbook download, language/history preservation and no serious/critical
  axe findings on Report and List.
- All four real browser PDFs contain one A4 page. Visual inspection of English and
  dark-theme Arabic paper confirms readable criteria, five bands, matching count
  table, white paper and correct direction. Reproducible artifacts are generated
  under ignored frontend/test-results by academic-standing-report.spec.ts.
- Together with the recorded Pipeline print journeys, this supplies the executable
  two-Report EN/AR proof for prerequisite #15. Human paper/design acceptance is not
  claimed. Full package gates, remaining Phase 7 tickets and the single consolidated
  baseline review remain pending.

### Graduation Readiness dependency frontier: bounded owner calculations

- Verified existing capabilities before extending them: Enrollment already bulk
  reads effective Exemptions/Transfers and rosters; CreditHourSummaries.forStudents
  instead performed one complete single-Student read per ID. A public-seam
  regression measured **841 queries for 105 Students**, failing the bounded-read
  criterion before implementation.
- Catalog now bulk reads immutable bound Curriculum requirements/entries in chunks
  of 100, using the same DTO projection as single-version reads. A worked snapshot
  test proves a newer current Curriculum does not replace the admission-bound one.
  The admission recovery test double forwards the new Public methods.
- Enrollment now bulk reads effective Waivers in chunks of 100 through its existing
  Public facade. The established Advisor/Head/Council workflow proves worked 3-CH
  results, duplicate/absent-ID behavior and single/batch parity.
- Credit-Hour Summary composes bounded Student, Curriculum, roster, effective Grade
  and credit-decision DTOs through Public seams and its unchanged allocation
  calculator. All 105 worked summaries agree with independent single reads; the
  batch query count is below 100.
- GraduationProgress now exposes bounded advisory readiness with no proposed
  schedule. Its tracer exposed an existing empty-proposal edge case: remaining CH
  with no exact targets could report Approaching Graduation. The owner now requires
  a nonempty for-credit proposal, implementing #02's approved condition without
  changing registration exception rules. Missing curricula remain omitted/unavailable;
  null CGPA and all overlapping typed blockers are retained.
- Focused owner/admission/Waiver/underload regressions passed **46 tests / 417
  assertions, 13.18s**. Pint, PHPStan and deptrac passed. These are executable
  prerequisites for #02, not completion of its Report/List/export/UI acceptance.

### Graduation Readiness Report HTTP selection and workbook slice

- The owner Report, ordinary pageable Student List and audited full XLSX export
  now reuse #01's live academic cohort authorization before owner calculations.
  Three readiness groups partition the selection; typed blocker counts overlap.
  Missing admission-bound curricula remain explicit unavailable rows in totals.
- Readiness/blocker/remaining-CH/nullable-CGPA/proximity criteria compose before
  derived ordering and pagination. Proximity is explicitly remaining 1–5 CH;
  no completing schedule is supplied. Unknown numeric academic facts never become
  zero. Stable Student ID ordering and nullable values last remain deterministic.
- A real weighted Grade example proves Academic Warning with CGPA 3.25 remains
  ready when curriculum requirements are satisfied. Current open Cases block;
  dismissal and trigger clearance are reflected immediately without reconciliation.
  Historical Terminated academic facts remain readable via explicit lifecycle.
  Repeat reads preserve Case/Observation/notification facts.
- Worked scoped counts, matching drill criteria, derived order-before-page,
  unavailable population and EN/AR workbook read-back passed **9 focused tests /
  214 assertions, 6.98s** including the owner regressions. The live open-Case
  fixture initially used a nonexistent abbreviated state; using the canonical
  AwaitingAdvisor state corrected the fixture without a product workaround.
- Whole seeded-cohort owner verification passed **1 test / 544 assertions, 28.44s**:
  all 180 Credit-Hour and readiness results agree with independent single reads,
  batch query count stays below 200 and no-proposal Approaching Graduation is false.
- Pint, PHPStan, deptrac and generated-client typecheck passed. OpenAPI/Orval
  regenerated with truthful nullable readiness/CGPA, blocker arrays and safe data;
  no generated file hand-edits. UI/browser evidence and remaining worked HTTP
  acceptance scenarios are still required before #02 completion. Full gates and
  the consolidated review remain pending.

### Bilingual Readiness Report/List, shell composition and native journeys

- The owner UI presents three advisory groups and all four overlapping blocker
  bands with identical accessible tables. Common named academic criteria are reused
  without the Standing-only field; readiness, blocker, numeric ranges, CGPA
  availability and the explicitly labelled 1–5 CH proximity selection use the URL.
  Historical selections retain an explicit ineligibility/advisory notice.
- The ordinary Student List renders every typed blocker, nullable readiness/CGPA,
  remaining CH, owner's no-proposal Approaching Graduation, safe Program facts,
  permission-gated existing grade links, URL sorting/pages and canonical download.
  Focused regressions prove real 0.00 versus unavailable evidence and exact drill
  URLs in EN/AR. An added accessible table-name regression caught a copied Standing
  label; it now names advisory readiness in both languages.
- Navigation first failed for seven eligible personas before the new item existed;
  protected Report/List routes require the same permission and academic Roles as
  #01. The final focused Readiness Report/List/SideNav run passed **27 tests /
  3 files, 20.23s**. Existing Standing Report regressions also passed during the
  combined run. Typecheck, lint and full formatting passed.
- Backend and frontend post-commit API drift checks passed for f0baffb. Native
  Readiness journeys passed **4 tests, 2.2m**, serial Student Affairs/Dean in EN/AR
  light/dark: scoped group/table counts, exact full blocker membership, keyboard
  List drill, detail access, workbook download, Back and no serious/critical axe
  findings on Report and List.
- Initial native failures were locator defects: literal parentheses interpreted
  as regex syntax, then duplicated CGPA-unavailable text in the value and blocker.
  Exact row-header and cell role locators corrected the harness without changing
  business behavior. All four real PDFs contain two A4 pages. English and dark
  Arabic paper inspection confirms complete readable criteria, charts/tables,
  overlapping-blocker explanation, correct direction and white paper.
- Artifacts are reproducible through graduation-readiness-report.spec.ts under
  ignored test-results. Human presentation acceptance is not claimed. Remaining
  worked HTTP acceptance scenarios, #03–#07, full gates and the consolidated final
  review remain pending; no per-ticket review was run.

### Termination Report HTTP oversight slice

- AcademicStanding now selects a minimal Case projection after #01's live cohort
  scope. Default lifecycle includes Active and Terminated Students; default Cases
  include three open stages and final Terminated outcomes. Explicit history adds
  Dismissed, TriggerCleared and EvidenceChanged replacements. Case counts and
  distinct Student counts remain separately labelled.
- Owner-local Case reads select only references, state and workflow timestamps;
  evidence, fingerprints and review narratives are not fetched or serialized.
  Inclusive UTC Case-created date boundaries compose with academic/state/history
  criteria before stable Case-ID ordering and pagination (cap 100).
- Review URLs require operational permission, explicit persona, relevant open
  stage, Active lifecycle and live advisee/academic Department access. Oversight
  alone grants no operational link; existing review routes retain their own gates.
- EN/AR canonical workbook read-back proves all 30 scoped Cases despite per_page=1,
  string Case/Student/University IDs, recommendation-stage labels, RTL sheet
  metadata, private-data exclusion and actor/criteria/Case/Student count audits.
- Focused new Report plus existing operational regressions passed **15 tests /
  187 assertions, 6.90s**, including live union/reassignment, inclusive date edges,
  history counts, repeated GETs without workflow writes, Student/Instructor-only
  denial, Advisor/Head/Council link access and 105-Case deterministic pagination.
  The first history fixture attempted several open Cases for one Student; the
  owner database constraint correctly rejected it. Distinct open Students plus
  closed replacements now model legal history without disabling the constraint.
- Pint, PHPStan and deptrac passed. OpenAPI/Orval regenerated with nullable safe
  names/Program, timestamps/review URL and unavailable string array. Generated
  client typecheck is pending before commit. Owner UI, browser/demo fixtures,
  print/a11y, all remaining acceptance gates and the consolidated review remain
  pending. No per-ticket review was run.
- Final generated-client typecheck passed. Post-commit API drift checks follow.

### Termination UI and executable demo fixtures

- Protected Report/List routes and navigation now share academic Report roles and
  monitor permission. UI preserves named academic, state, history and UTC date
  criteria; separately labels Case and distinct Student populations; exposes only
  server-authorized, stage/persona/permission-matched operational links.
- EN/AR Report/List and navigation focused tests passed **28 tests / 3 files,
  20.71s** after seven navigation assertions failed before composition. Typecheck
  and lint passed. Generated API drift checks following the backend commit passed.
- New isolated Phase7AcademicReportSeeder uses existing academic seed helpers,
  two unused graduate Offerings and owner-derived D/F Grades. Public Termination
  reconciliation creates three real Cases; reseeding preserves their reviews and
  evidence. Named operational rosters remain untouched. Test-first missing-seeder
  RED became **1 passing test / 17 assertions, 18.75s**; Pint/PHPStan passed.
- Seeded development fixtures successfully. Native EN/AR × oversight persona
  browser/print/accessibility verification is running; no result claimed yet.
  All seven-ticket final gates and consolidated review remain pending.
- Native Termination journeys passed **4 / 4, 1.5m** (Student Affairs light
  and Dean dark, EN/AR): exact 3 Cases / 3 Students, keyboard state drill,
  seven-state table with zero-state links absent, privacy-safe List, no oversight
  review link, canonical downloads, Back navigation, and Report/List axe without
  serious/critical violations. A4 PDFs preserve direction, black text and table fit;
  EN/AR each occupy two pages. Actual Arabic page 1 inspected; complete paper
  inspection and final cross-phase acceptance remain pending.

### Existing-screen polish frontier

- #04 confirmed two concrete defects test-first: untranslated session-loading
  accessible label and Arabic selection controls inheriting English built-in
  labels. Paired auth key and locale-derived MUI defaults fix them; **16 focused
  guard/theme/control tests passed, 9.37s**. Arabic Pipeline population wording
  regression also captured; count-neutral translation verification is running.
- Actual Termination paper second pages inspected in English and dark-theme Arabic:
  all seven state labels/counts fit with correct direction and black-on-white text.
  Human presentation signoff remains unclaimed.
- Bilingual audit records retain explicit coverage gaps rather than infer complete
  screen correctness from dictionary parity. #04–#07, final gates/review pending.
- Pipeline EN/AR focused Report tests passed **2 / 2, 15.32s**; frontend typecheck
  and lint passed for this polish slice. Full four-state surface audit remains pending.
- Catalog narrow overflow RED reproduced in English Faculties and Arabic
  Departments. Main shell flex child now permits shrinkage (`minWidth: 0`) while
  local tables retain scrolling. Read-only Catalog matrix passed **4 / 4, 2.5m**:
  tree, Faculties, Departments, Programs, Courses at 390/1280px, all EN/AR × themes,
  raw-key checks and axe without serious/critical violations.
- Termination reviewer Arabic labels now inherit sentence direction with separate
  LTR bdi Student ID/fingerprint values. Regression captured RED; **3 focused
  review tests passed, 7.66s**, preserving Council decision confirmation.
- Readiness/Termination pending/error messages previously named Academic Standing.
  Actual generated-hook feedback tests captured **8 failures** before paired owner
  messages; focused feedback and Report regressions passed **12 / 3 files, 21.55s**.
  Case List feedback checks and public-auth matrix are running; no outcome claimed.
- Public-auth routes lacked a language control. Native RED captured that absence;
  app-owned header now composes existing controls. A separate anonymous interaction
  captured unnecessary `/me` locale persistence; the shared app switcher now
  persists only for authenticated users. **8 switcher/registration tests passed,
  16.61s**. Native public login/registration matrix passed **4 / 4, 41.2s**, covering
  EN/AR × light/dark, 390/1280px, axe and preserved populated email/password inputs.
- Case loading/error/empty feedback regressions passed **17 tests / 2 files,
  14.04s** after six new expected RED failures. These owner messages distinguish
  absence of Cases from absence of Students. Existing review regressions pass.
- Remaining existing-surface browser matrix is running serially with stop-on-first
  failure. A cross-Report scoped headline with workbook membership read-back is
  authored but not yet run. Final package gates and review remain pending.
- Frontend typecheck, lint and full format check passed for the public controls,
  shell width, identifier isolation and owner feedback slice. Broader acceptance
  coverage remains pending; no consolidated code review has been run yet.
- Worked HTTP verification reads an F/zero CGPA, then a newer Finalized Correction
  Grade and Enrollment owner outcome, while a new Programme Curriculum version
  changes current requirements. Standing and Readiness follow the corrected owner
  facts and Student-bound version, retain no-proposal approaching=false, and create
  no Cases/Observations/notifications. **4 readiness tests / 68 assertions passed,
  3.92s**. Existing capability verified; no new academic calculation introduced.
- Remaining matrix stopped at demonstrated dark Screening Queue age contrast
  (**2.89:1**). Light EN/AR staff paths passed before the failure; 29 subsequent
  tests did not run. Static palette-mode branching replaced with CSS-variable
  tokens and existing dark applyStyles. Four-state staff verification rerunning.
- Dark staff matrix passed **4 / 4, 2.2m** after token repair. Focused Screening
  Queue/typed-notification regressions passed **14 tests / 2 files, 9.68s**.
- Accepted/Dean-review screening records now show paired read-only notice rather
  than illegal decision buttons. **3 focused tests passed, 7.30s** after two RED
  failures. Backend transition and permission guards remain authoritative.
- Academic loading Skeletons reproduced serious invalid ARIA naming in the native
  Student audit. Academic EN/AR RED and Records EN/AR RED cover role/status repair;
  owner loading groups follow existing status prior art. Focused academic/review/
  Records checks passed **16 tests / 4 files, 14.26s**. Credit-queue regressions,
  all-four loaded Student audit and static checks are running.
- Earlier static check caught Playwright's exact option copied into two RTL role
  queries; removed the unsupported option. Its lint/format stages did not run.
  A fresh static command is running; no failed/unrun check is reported passed.
- Affected credit-decision/capacity queue regressions passed **54 tests / 7 files,
  55.35s**. Fresh typecheck, lint and full format checks passed, including corrected
  RTL role-query options and owner loading semantics. Worked correction/bound-
  Curriculum Report check now additionally proves real zero CGPA matches an
  inclusive 0–0 range: **4 tests / 70 assertions, 4.07s**.
- Loaded Student Dashboard/registration/schedule/academic progress/My Grades
  matrix passed **4 / 4, 2.4m**, at 390/1280px in all language/theme states. Proper
  loading semantics are covered separately rather than scanning Skeletons as
  completed content. Advisor/Head/Council/Records remain on the audit frontier.
- Remaining Advisor/Head/Council and Student/staff Records read-only matrices
  passed **20 / 20, 6.0m**, with all four language/theme states, narrow/wide fit,
  loaded content, raw-key checks and no serious/critical axe findings. Together
  with prior groups this covers the nine core matrix surface groups.
- Academic bookmarked reference edge captured: 101 IDs lost their last named
  criterion at the Catalogue 100-row page cap. Additional EN/AR pending/error/
  absent-reference checks distinguish truthfully labelled states: **5 RED / 2
  existing-state passes**. Exact lookup now batches generated owner queries at 100
  IDs and preserves per-batch pending/error versus missing-reference labels.
  Focused regressions and static checks are running; no result claimed yet.
- Additional Catalog detail/curriculum/version/dialog, invalid-public verification
  and cross-Report headline specs authored but unrun. Existing Records journey
  now saves actual A4 PDFs alongside its existing four-state print checks for
  paper inspection; no completed Records journey claim yet.
- Named reference edge/states now pass **7 focused tests, 15.38s**; three existing
  Report files also passed. The first green attempt used an accessible-region
  query for print-only hidden criteria; corrected the test to inspect its labelled
  criteria section, preserving production print visibility. Typecheck/lint/full
  format passed for the batching/state labels.
- Narrow Curriculum add row now wraps and control widths cap at available space;
  focused Curriculum regressions passed **10 / 2 files, 11.95s**. Native detail
  check progressed through Programme history/dialog to the Course's intentionally
  empty seed history; the test incorrectly assumed non-empty history for both.
  It now verifies the real populated Programme and empty Course states separately.
  No seed/version history fabricated. Detail/dialog matrix still pending.
- Course alternate-language name generated a level-six heading under the title.
  Native heading regression captured RED; explicit paragraph preserves subtitle
  styling and proper document hierarchy. Catalog detail/dialog EN/AR light and
  dark EN cases pass so far; final dark AR result pending. Fresh typecheck, lint
  and format checks passed for named criteria, responsive controls and semantics.
- Final Catalog detail/curriculum/history/dialog matrix passed **4 / 4, 2.2m**,
  covering EN/AR, light/dark, 390/1280px, keyboard dialog focus and loaded axe
  checks. Named reference and Catalog fixes are committed with this evidence;
  headline, invalid-public verifier and Records paper journey remain unrun.
- Serial acceptance slice: `npx playwright test e2e/phase7-headline.spec.ts
  e2e/records-invalid-audit.spec.ts e2e/records.spec.ts --workers=1 --max-failures=1`
  passed **6 tests, 5.0m**. Advisor headline verifies matching three-Student
  membership across all Reports, canonical downloaded workbook contents, keyboard
  drill-through, EN/AR criterion continuity, scoped Grade detail and authorized
  Advisor review. Invalid verifier passed all four states with no private snapshot.
- Records service journey passed payment correction, certification, bearer QR,
  revocation, full refund and collection. Actual PDFs generated for current,
  official, public valid, private revoked and public revoked in all four states:
  current/revoked public **one A4 page**, official/full snapshot **two A4 pages**.
  Inspected EN official and dark AR current/official/public valid/revoked rendered
  pages, including continuation table headings, watermark, seal and unmirrored QR.
  No snapshot mutation or disclosure expansion. Human paper acceptance remains
  required; this is native verification, not human signoff.
- Additional read-only detail audit is running for Applicant draft/populated
  language switching, Screening/Dean/Advisor details, attendance register and
  Interviewer in all four states, narrow/wide. Full package gates still pending.
- B04-10: native Applicant draft audit reproduced meaningful disabled-helper
  contrast of 2.57:1. EN/AR focused tests captured two RED failures; helper state
  now remains enabled while GPA controls retain the scale gate. All **14
  PersonalInfoStep tests passed, 27.57s**, and native Applicant four-state checks
  passed. Remaining detail matrix continues; no whole matrix result claimed yet.
- Added explicit surface/seam coverage map to the bilingual audit. Extended existing
  Admissions/Records owner journeys reuse live populated states for four-state
  presentation checks without repeating transitions; those extensions are unrun.
- Corrected ROADMAP/HANDOFF Phase 4.6 planning-versus-executable status and Phase 7
  branch/frontier. Full gates, consolidated review and human acceptance stay pending.

### Consolidated prerequisite dispositions

| Phase 4.6 ticket | Executable disposition | Evidence seam |
| --- | --- | --- |
| 01 | Implemented bounded fixture/assertion closure; backend gate passed, frontend gate pending | GeneratedCohortSeederTest, named-persona seeder tests, collection/journey counts audited throughout |
| 02 | Existing Support parser verified; shared URL/filter/page controls and scoped screening tracer implemented | CollectionQueryTest, CollectionContractTest, ScreeningQueuePage interactions and serial collections |
| 03 | Existing OpenSpout writer verified; canonical full-selection audited exports wired | Screening/collection and Report export HTTP tests, workbook readback, scoped native exports |
| 04 | Required owner aggregate/batch closure implemented; no foreign Internal calls | BatchCgpaSeededParityTest, aggregate parity tests, GraduationBatchSeededParityTest, owner Curriculum/Credit-Hour/Standing batches |
| 05 | Shared chart primitive implemented with all three chart types and exact keyboard table | Shared Chart tests and four-state style gallery |
| 06 | Admissions/Catalog collection/picker contract implemented and generated | Owner collection HTTP tests, typed picker interactions, native collection and full owner journeys |
| 08 | Analytics composition/Pipeline scoped Report and exact drill-through implemented | ApplicationPipelineReportTest, aggregate tests, application-pipeline native four-state journey |
| 15 | Browser Report print implemented and native papers inspected across Pipeline, Standing, Readiness and Termination | ReportDocument/Chart print tests and real EN/AR A4 artifacts; human paper signoff still required |

Other Phase 4.6 tickets remain outside this goal. This table records executable
implementation, not whole Phase 4.6 completion or passing an unrun final gate.

### Final package verification underway

- `COMPOSER_PROCESS_TIMEOUT=0 composer gate` started against PostgreSQL (log:
  `/tmp/phase7-backend-gate.log`). Native detail audit remains serial on the separate
  development fixture; no competing Pest process or browser journey is running.
- Frontend typecheck, lint and full format checks passed for B04-10 and authored
  presentation audit additions. Final `npm run gate` remains pending.
- Additional detail audit passed **24 / 24, 7.4m**: Applicant unsaved input
  continuity, Screening/Dean/Advisor details, Instructor offerings/register and
  Interviewer, all four language/theme states at 390/1280px with loaded axe checks.
  The only demonstrated defect was B04-10, fixed and independently regressed.
- Full development fixture refresh started only after the serial browser finished,
  preparing the required final frontend gate. Backend gate continues on prs_test.
- First backend gate: Pint/PHPStan/deptrac passed, then Pest stopped at the older
  ManualExemptionWorkflowTest Identity forwarding probe missing newly added
  `studentIdsMatching`. Updated that probe to delegate the Public method without
  altering its bounded hydration assertion. Other explicit contract doubles checked.
  Focused rerun: **22 tests / 274 assertions passed, 11.49s**. Full backend gate
  restarted; initial failed log retained as `/tmp/phase7-backend-gate-attempt1.log`.
- `php artisan migrate:fresh --seed --force` passed for the final development
  fixture. `npm run gate` started (`/tmp/phase7-frontend-gate.log`), with serial
  Playwright and no reseed/competing native journey.
- Frontend gate attempt 1: generated drift, lint, format and typecheck passed; stopped
  deliberately during Vitest before any Playwright started after actual i18next
  source/runtime audit demonstrated Arabic plural categories rendering “one” for
  other owner counts. No unit/full-gate pass claimed. Log retained as
  `/tmp/phase7-frontend-gate-attempt1.log`; development fixture remains untouched.
- B04-11/B05-04: sixteen actual Admissions/Enrollment/Grading Card regressions
  captured RED across Arabic plural categories. Count-neutral paired base/other
  Arabic wording now preserves owner numbers, with English messages and domain
  counts/empty-state rules intact. Affected seven-Card suite is running.
- B04-11/B05-04 affected suite passed **58 tests / 7 files, 28.89s** after the
  sixteen RED regressions. Full frontend gate restarted against the same untouched
  freshly seeded fixture; its static stages rerun rather than reusing the aborted
  attempt. No browser reseed occurred during a suite.
- Post-fix frontend dictionary audit: **2,104 keys per language; zero missing/extra
  keys and zero interpolation mismatches**. Backend source audit: **10 paired
  dictionaries**, no key differences. These supplement actual rendering tests and
  browser checks; they do not substitute for translation quality/human acceptance.
- Backend gate attempt 2: **1 failed / 1,323 passed, 8,384 assertions, 782.05s**.
  Failure exposed enum-object comparison in mixed-status derived pagination.
  A single focused run happened to pass; six insertion-order permutations reproduced
  **5 RED / 1 pass**. Direct PHP probe showed both enum comparison directions
  return 1, whereas string values are antisymmetric. Sort now explicitly reads
  status domain values before the common comparator; focused API checks running.
- Frontend gate attempt 2: generated drift/lint/format/typecheck and **121 files /
  674 unit tests passed, 487.01s**. Stopped deliberately after three Standing
  browser checks passed, before changing the live backend sorter. Full browser/
  gate pass is not claimed. Log retained as `/tmp/phase7-frontend-gate-attempt2.log`.
- Newly expanded existing Admissions/Enrollment/Records journeys and correction
  dialog four-state audit are now running serially with max-failures=1 to verify
  their live presentation seams before the required complete frontend gate.
- Enum sort fix focused verification passed **28 tests / 291 assertions, 6.07s**
  (all insertion permutations plus shared actor-scope collection contracts); Pint
  passed. Repeating the required full backend gate after this concrete failure.
- Expanded owner audit stopped on B04-12: choosing English test None left its
  meaningful instructional helper at 2.57:1. Two EN/AR RED regressions captured
  the populated state. Enabled helper state while retaining disabled score input
  and existing submission/validation rules; focused/static checks and serial owner
  journeys rerunning. Remaining owner checks were not run on that failed attempt.
- B04-12 focused check passed **16 PersonalInfoStep tests, 34.42s**; frontend
  typecheck, lint and full format checks passed. Native expanded owner journey
  remains active; its completion is not claimed yet.
- Backend gate attempt 3: Pint/PHPStan/deptrac and **1,329 Pest tests / 8,421
  assertions passed, 725.83s**; final OpenAPI drift failed on three operational Case
  path examples inferred from mutable development data (16 → 1). Adding explicit
  controller-doc URL examples; no runtime operation changes. Regeneration/drift
  verification and the complete backend gate remain pending.
- Expanded owner audit also exposed two harness races after the wizard checks:
  capturing queue inputs before detail navigation finished, then choosing English
  before saved account locale applied. Await actual Applicant detail heading and
  authenticated `/me` preferences, and await persisted language GET data when
  switching. Product behavior unchanged. Revised eight-test serial run active.
- Explicit operational Case controller URL examples regenerated without further
  tracked OpenAPI drift (`composer api:check`). Complete backend gate attempt 4
  is running; attempt 3 retained in `/tmp/phase7-backend-gate-attempt3.log`.
- Revised expanded owner audit: Admissions passed **4.6m**. Correction audit
  selected the first CSE511 row, which is Draft, rather than an existing Finalized
  round. Corrected the harness to filter Finalized while English is applied, then
  switch using persisted authenticated locale. No grade workflow write or fixture
  change was needed. Remaining seven checks rerunning serially; unrun checks on
  the stopped attempt are not claimed as passing.
- Complete backend gate attempt 4 **PASSED (exit 0)**:
  `COMPOSER_PROCESS_TIMEOUT=0 composer gate` from `backend/`. Pint, PHPStan,
  deptrac, **1,329 tests / 8,421 assertions (740.02s)** and OpenAPI drift all
  passed. Exact log: `/tmp/phase7-backend-gate.log`. Prior failed attempts remain
  documented with fixes; this is the first complete green backend gate.
- Correction-dialog native audit passed all four language/theme combinations
  (13.3s / 19.7s / 12.8s / 19.2s), including validation, keyboard selection and
  narrow/wide axe. Expanded Enrollment passed **4.2m**; Phase 7 exact-population/
  workbook/language/investigation headline passed **59.9s**. Records remains active.
- Remaining expanded owner run **PASSED (exit 0): 7 tests, 15.2m**.
  Records expanded service/presentation/print journey passed **8.9m**; all 20
  EN/AR/light/dark current, official and public/private revoked A4 PDFs produced.
  Admissions separately passed **4.6m** in the immediately preceding serial run.
  Harness fixes are committed with these observed results; Prettier passed for
  all affected browser files. Log: `/tmp/phase7-owner-audit-remaining.log`.
- Preparing a fresh migrated/seeded development fixture after all browser contexts
  ended, then rerunning the required complete frontend gate. No browser suite
  was reseeded or competed with another journey.

- Required fresh development migration/seed completed successfully before final
  frontend gate attempt 3. Generated cohort layer took **13.376s**; academic
  Case fixture took **2.318s**. `npm run gate` is active from `frontend/` (log:
  `/tmp/phase7-frontend-gate.log`); regenerated API, lint, formatting and typecheck
  passed. Full unit and serial browser results remain pending.
- Frontend gate attempt 3 unit stage passed: **121 files / 676 tests, 429.95s**.
  Required complete serial Playwright stage now running in the same invocation;
  no full frontend-gate pass is claimed until its final exit status is observed.
- Complete frontend browser stage: all **36 core bilingual audit checks** passed
  uninterrupted, including expanded Student/Advisor/Head credit surfaces. Earlier
  grouped runs are now supplemented by the complete core matrix within the full
  gate. Standing/Pipeline four-state Reports, Admissions headline, public auth and
  interview collections also passed. Remaining serial suite is active.
- Interruption recovery: the prior frontend gate handle is missing, no gate/browser
  process is alive, and temporary logs were cleared. Retained browser artifacts
  reach the Exemptions checks, but there is no terminal full-gate result. Prior
  observed passes remain documented; full frontend success is not inferred.
  Archived interrupted artifacts under ignored backend private storage. Repeating
  the full gate from a fresh seed with durable workspace logs under
  `backend/storage/logs/phase7-verification/`, without any competing browser.
- Recovery frontend gate: generated client drift, lint, format and typecheck passed;
  **121 unit files / 676 tests passed (545.89s)**. Complete Playwright stage
  started with **175 tests / one worker** against the fresh fixture. Durable log:
  `backend/storage/logs/phase7-verification/frontend-gate.log`. Its terminal result
  remains pending; the interrupted earlier run is not claimed complete.
- Recovery frontend full-gate browser attempt stopped deliberately (exit 143) after
  two concrete failures: grading headline and English Readiness setup selected
  English before saved `/me` Arabic preferences applied. Failure artifacts show
  correct authenticated accounts/permissions with Arabic shell labels; Arabic
  Readiness subsequently passed. No academic calculation failure was observed.
  All 121 unit files / 676 tests, static stages and preceding 109 browser checks
  passed; remaining browser checks were not completed and no full-gate pass is
  claimed. Durable failed-attempt log remains in workspace storage.
- Reused the existing authenticated locale helper across seeded browser setups:
  await actual `/me` preference, then English and saved-locale GET completion;
  explicit language switches await persistence before navigation/reload. Product
  code, owner workflows and assertions remain unchanged. Focused five-test serial
  grading-headline/Readiness run is active against the retained failing preference
  state; next full gate will receive a new fresh fixture after it ends.
