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
| 05 Enrollment/Grading/progress polish | Pending audit | |
| 06 Records polish | Pending audit | Existing Records print regressions to retain |
| 07 Acceptance | Pending 01–06 | Human demo review remains required before phase acceptance |

## Commands and results

No implementation gates have run yet. Starting commit and clean worktree verified.

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
natural keys. Red test confirmed missing seeder. The first implementation run
revealed the shipped Catalog has five Programs; corrected the test's invented
six-Program expectation to require all five. Focused parity/idempotence and named
persona preservation test is running; full assertion audit is still pending.

## Consolidated review

Reserved until all tickets and prerequisite changes are implemented.
