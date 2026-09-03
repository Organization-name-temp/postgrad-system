# Spec — Phase 4.6: Query, Filter, Export & Reports

**Status:** ready-for-agent
**Feature slug:** phase-4.6
**Phase:** 4.6 of the [Build Roadmap](../../docs/ROADMAP.md) — a platform-plus-analytics slice inserted between Phase 4.5 (Dashboards) and Phase 5 (Standing). Phases 5, 6 and 7 keep their numbers and order; **Phase 7 shrinks**, because this phase takes five of its eight Reports.
**Runtime:** Laravel 12 / PHP 8.4 ([ADR-0006](../../docs/adr/0006-runtime-baseline-laravel-12-php-84.md))
**Sources:** [SRS §4.12](../../docs/SRS.md) (Reporting & Analytics — eight Reports, never built) · [SRS FR-PCM-003.5](../../docs/SRS.md) (bulk course import/export via CSV/Excel — deferred since Phase 1) · [SRS NFR-PRF-010](../../docs/SRS.md) (search) · [design-system.md §14](../../docs/design-system.md) (chart palette, written in Phase 0.5 and unused since) · [CONTEXT.md](../../CONTEXT.md) (*Report*, *Export*, *Dashboard Card*) · ADRs [0002](../../docs/adr/0002-modular-monolith-seams.md)/[0005](../../docs/adr/0005-localhost-learning-build.md)/[0008](../../docs/adr/0008-rbac-enums-static-matrix.md)/[0011](../../docs/adr/0011-catalog-version-snapshots.md)/[0012](../../docs/adr/0012-cross-module-reads-by-id-and-dto.md)/[0016](../../docs/adr/0016-private-document-serving.md)/[0019](../../docs/adr/0019-attendance-session-register.md)/[0020](../../docs/adr/0020-grading-owns-gpa-read-model.md)/[0021](../../docs/adr/0021-grading-scale-uniform-bands.md)/[0024 + its Phase-4.6 amendment](../../docs/adr/0024-permission-composed-dashboard-cards.md) · **new** [ADR-0025](../../docs/adr/0025-support-layer-query-contract.md) · [ADR-0026](../../docs/adr/0026-reports-owned-by-modules.md) · [ADR-0027](../../docs/adr/0027-export-sibling-route-audited.md) · [ADR-0028](../../docs/adr/0028-aggregate-facades-sql-php-parity.md)

---

## Problem Statement

Five phases have shipped, and the system can now *record* everything the bylaws describe. It
cannot *answer a question about any of it.*

- **Every list in the system returns every row, unfiltered and unpaginated.** Only the
  notification bell paginates. The Student Affairs screening queue, the course registry, the
  Offering list, the grade-sheet queues, the oversight student list — all of them load in full
  and sort one way, the way the controller decided. A Student Affairs officer looking for one
  Applicant scrolls.
- **The only search in the system is client-side and lives in one screen.** Phase 1's Catalog
  tree filters in the browser over data already fetched. Nothing else searches at all, and the
  app-bar global-search slot has been empty since Phase 0.5 — deferred then "to the phases that
  add their endpoints", which never happened.
- **Nothing can leave the system.** There is no export of any kind. A Dean asked for the
  admissions intake by Program has no path that does not end in a hand-typed spreadsheet, and a
  registrar reconciling enrollment against a ministry return has no path at all.
- **SRS §4.12 — the entire Reporting & Analytics module, eight Reports — has never been built,
  and the ROADMAP's reason for deferring it was only two-thirds true.** It records that Phase 7
  cannot be pulled forward because its Reports need read models Phase 5 has not built. That is
  true of **three** of them (Academic Standing, Graduation Readiness, Termination) and of no
  others. Five — Application Pipeline, Enrollment Summary, Course Registration Summary, GPA
  Distribution, Audit Log — read data Phases 0–4 already shipped and have been buildable for
  months.
- **Not one figure in the system is shown as a distribution, a trend, or a comparison.** Phase 4.5
  built twelve Dashboard Cards of counts; every one answers "how many are waiting for me" and
  none answers "is that normal", "is it getting worse", or "how are we doing against last term".
  `design-system.md` §14 has carried a colourblind-aware chart palette since Phase 0.5 that
  nothing has ever rendered.
- **Derived values are unreachable to any question that spans more than one Student.** CGPA,
  attendance percentage, queue age and remaining credit hours are all computed on read, one
  record at a time — `GradingRecords::cgpaFor()` takes a single Student id and crosses two module
  seams per call. So "students below 3.0", "the CGPA spread in this Program", and "sort this list
  by CGPA" are not slow; they are **not expressible**.
- **The Audit Log is written and never read.** It has accumulated authentication events since
  Phase 0 and has no list endpoint at all; the only thing that reads it is the Administration
  Card, which shows the most recent few.
- **The Catalog cannot be loaded in bulk.** FR-PCM-003.5 has been deferred since Phase 1, so
  standing up a Faculty's Courses means keying them one at a time through a form.

The system holds the data and refuses the questions. It works and cannot be interrogated.

## Solution

Two things, built together because the second is unaffordable without the first.

**One query contract, on every collection endpoint in the system.** A shared `Support` layer —
the project's first shared backend code — parses one flat query syntax into an immutable query
spec: filters, sort, page, and a normalised bilingual free-text term. Every list endpoint adopts
it, returning an always-paginated envelope. Every filterable list gains a sibling `/export` route
taking the identical query string and streaming a true `.xlsx`, authorized by that list's own gate
and written to the **Audit Log**, because the new fact is not that data was read but that it left.
The SPA renders it through one `<FilterBar>` in `shared/ui` — the query rules and no domain names,
the `<StatusChip>` discipline — with filter, sort and page state living in the **URL**, so a
filtered view is bookmarkable, shareable, and one string away from its own export.

**Six Reports on top of it.** A **Report** — now a first-class term in
[CONTEXT.md](../../CONTEXT.md) — is a parameterised, cross-record view: figures, charts, and the
drillable list behind them. Each is owned by the module that owns its spine data and gated on the
Permission that already authorizes reading that data, so a Report discloses nothing its viewer
could not already open one record at a time. The SPA composes them into one Analytics section
exactly as it composes Dashboard Cards — *owner supplies, shell arranges*. Charts render through
`@mui/x-charts` on §14's existing palette, each paired with a **"View as table"** disclosure of the
identical series, which is how the accessibility gate is met in substance rather than form. Clicking
a chart segment navigates to the filtered list behind it — which is itself exportable, closing the
loop: **one contract drives grid, Report parameters, drill-through and Export, so a bar and the rows
it stands for cannot disagree.**

Around those two: derived values become batch methods on their owners' existing `Public` facades;
the authorized result is filtered and sorted before page slicing when a derived value is involved.
Dashboard Cards grow
figures, deltas and a sparkline over their own user's data; the app-bar search slot is finally
filled by a client-side fan-out over the new per-module search; and bulk Course import closes
FR-PCM-003.5 now that the spreadsheet library is paid for.

## User Stories

**Filtering and searching**

1. As a Student Affairs officer, I want to narrow the screening queue by Application Status, Program and Admission Cycle, so that I work one intake's backlog instead of every Application ever submitted.
2. As a Student Affairs officer, I want to filter the screening queue by how many days an Application has waited, so that I clear the overdue ones first rather than re-deriving age by eye.
3. As a Student Affairs officer, I want to search an Applicant by name in Arabic without typing the hamza correctly, so that `احمد` finds `أحمد` and I stop failing to find people who are there.
4. As a Student Affairs officer, I want to search by partial National ID or University ID, so that a half-remembered number from a phone call is enough to find the record.
5. As a Dean, I want my Faculty's Applications filtered by degree level and decision state, so that I can work MSc and PhD decisions separately.
6. As a Dean, I want a filter I apply to be unable to reach another Faculty's Applications, so that my scope is a property of the system rather than of my restraint.
7. As an Advisor, I want to filter my advisee list by CGPA range, so that I can find the students who need me before their next registration. Academic Standing remains Phase 5 scope.
8. As an Advisor, I want to filter registration requests by Semester and status, so that last term's decided requests stop crowding this term's pending ones.
9. As an Instructor, I want to filter my roster by Course Status and attendance percentage, so that I can see who is at risk on an Audit Enrollment without reading every row.
10. As a Department Head, I want to filter the grade-sheet queue by Semester, sheet kind and state, so that I can find the Correction rounds among the Initial ones.
11. As a Department Head, I want to sort a queue by how long a Grade Sheet has waited, so that nothing sits unreviewed because it scrolled off.
12. As a catalog administrator, I want to filter Courses by Department, level, credit hours, Research Type and active state, so that curriculum work stops beginning with a scroll.
13. As a catalog administrator, I want to search Courses by code fragment, so that `CSE5` finds `CSE511` — which a word-based search would not.
14. As any user of a long list, I want my filters, sort and page in the URL, so that I can bookmark a view, share it with a colleague, and use the back button without losing my place.
15. As any user, I want to know how many rows matched, so that I can tell a narrow filter from an empty one.
16. As any user, I want a list page to load in a fixed number of rows, so that a large registry does not become slower every term.
17. As a signed-in user of any role, I want a search box in the app bar that looks across Students, Courses, Programs and Applications at once, so that I can jump to a record without first deciding which screen owns it.
18. As a signed-in user, I want global search to show me only what I am allowed to see, so that its result groups are never a directory of things I cannot open.

**Export**

19. As a Dean, I want to export any filtered list as an Excel file, so that I can take the exact set I am looking at into a meeting.
20. As a registrar, I want an exported National ID to survive as fourteen digits, so that a spreadsheet does not silently turn it into `2.9001E+13` and destroy it.
21. As an Arabic-speaking staff member, I want my export in Arabic with the sheet reading right-to-left, so that a spreadsheet is as usable as the screen it came from.
22. As a staff member, I want the export to contain the same rows my filters produced, so that I never have to check whether the file and the screen agree.
23. As a staff member, I want export to give me every matching row rather than the page I am on, so that pagination is a viewing convenience and not a limit on my work.
24. As a staff member, I want the exported file to contain a fixed, complete column set, so that a colleague opening my file finds the columns they expect regardless of what I had on screen.
25. As an Instructor, I want to export my own roster, so that a routine departmental task does not require Student Affairs.
26. As a System Administrator, I want every export recorded with who ran it, over what, with which filters and how many rows, so that bulk movement of personal data is reviewable after the fact.
27. As a System Administrator, I want export to require no permission beyond reading the list, so that access stays governed by one rule rather than two that can disagree.

**Reports and analysis**

28. As a Dean, I want the Application Pipeline by status with conversion rates, so that I can see where Applicants are being lost rather than only how many remain.
29. As a Student Affairs officer, I want the pipeline over an Admission Cycle, so that I can compare this intake with the last one.
30. As a Department Head, I want the GPA Distribution across a Program, so that I can see whether a cohort's spread is normal before an Academic Affairs conversation.
31. As a Dean, I want GPA Distribution by Semester and Program, so that I can tell a hard term from a weak cohort.
32. As a Student Affairs officer, I want the Enrollment Summary — Students per Program, Faculty and degree level — so that I can answer a ministry return without counting by hand.
33. As a Department Head, I want the Course Registration Summary — Enrollments per Course Offering against capacity — so that I can plan next term's Offerings from evidence.
34. As an Instructor, I want attendance analytics across my Offerings, so that I can see which of my sessions lose the room.
35. As an Advisor, I want attendance analytics across my advisees, so that an at-risk pattern is visible before it becomes individual Fail Withdrawals.
36. As a System Administrator, I want to browse and filter the Audit Log by actor, event type and date range, so that seven years of retained security events are actually usable.
37. As any Report viewer, I want to click a chart segment and land on exactly the records behind it, so that "twelve students below 3.0" is answerable with "which twelve".
38. As any Report viewer, I want the list I drilled into to be exportable, so that analysis ends in something I can act on.
39. As any Report viewer, I want to set the Report's parameters with the same filter bar the lists use, so that I learn one control and not two.
40. As a Report viewer using a screen reader, I want every chart's data available as a real table, so that the analysis is available to me and not only its picture.
41. As a low-vision or keyboard user, I want to switch any chart to its table, so that I can read exact values instead of estimating from bars.
42. As an Arabic-reading user, I want charts to render correctly right-to-left in both light and dark themes, so that Analytics is not the one part of the system that only works in English.
43. As a Department Head, I want to print a Report cleanly, so that I can take it into a meeting on paper.
44. As a Report viewer, I want a Report never to show me data I could not reach through an ordinary screen, so that Analytics is a faster route and not a wider one.

**Dashboard Cards**

45. As a Student, I want my Card to show my CGPA against the 3.0 graduation minimum and my trend across Semesters, so that I know where I stand and not only what I scored.
46. As an Advisor, I want my Card to show how many advisees are below the threshold alongside my pending requests, so that my Dashboard reflects the part of my job that is not a queue.
47. As an Instructor, I want my Card to show the grade spread of the Offering I most recently finalized, so that I learn something from my Dashboard.
48. As a Student Affairs officer, I want my Card to show this Cycle's applications against the last, so that a count has context.
49. As a user of any role, I want my Cards to remain about my own work, so that the Dashboard stays a summary of what is waiting for me and does not become a report I have to read.
50. As a user holding several Roles, I want the union of my Cards on one page with no role switcher, so that Phase 4.5's composition rule still holds after the Cards get richer.

**Bulk Course import**

51. As a catalog administrator, I want to upload a spreadsheet of Courses, so that standing up a Faculty is not a hundred form submissions.
52. As a catalog administrator, I want a preview showing which rows would be created, updated and rejected — and why, per row and per column — before anything is written, so that I can fix the file rather than the Catalog.
53. As a catalog administrator, I want an import to apply completely or not at all, so that a typo on row 400 never leaves the Catalog in a state matching neither the file nor what I started with.
54. As a catalog administrator, I want the Courses export to be a valid import template, so that the round trip is export, edit, preview, apply.
55. As a catalog administrator, I want an import to require both English and Arabic names on every row, so that bulk loading cannot create Courses the Arabic interface renders blank.
56. As a catalog administrator, I want each changed Course versioned and the import transaction audited once, so that Course history stays valid while the batch remains one deliberate act.

**Security and integrity**

57. As a security reviewer, I want a field to be filterable only if the requesting user may read it, so that filtering plus a total count cannot be used to recover values that were never displayed.
58. As a security reviewer, I want row-level scope applied as a base constraint the client cannot supply, so that no filter parameter can widen a Dean's Faculty or an Advisor's advisees.
59. As a security reviewer, I want an out-of-scope filter value to return an empty page rather than an error, so that scope boundaries are not probeable.
60. As a maintainer, I want every filterable endpoint proved against the same four properties by one shared dataset, so that adding an endpoint without its authorization checks fails the build.

**Maintenance**

61. As a maintainer, I want the query contract to exist once rather than seven times, so that a change to the allowlist rule cannot be applied to six modules and missed in the seventh.
62. As a maintainer, I want the shared layer to be incapable of referencing a module, so that "shared" cannot quietly become "knows about everything".
63. As a maintainer, I want batched and single-record facade results asserted equal over the seeded cohort, so that batching cannot change an academic result.
64. As a maintainer, I want Phases 5, 6 and 7 to inherit filtering and export automatically, so that this migration happens exactly once.
65. As a reviewer running the demo, I want enough seeded Students for a distribution to have shape and a list to actually paginate, so that the review gate judges the feature rather than the fixture.

## Implementation Decisions

### The `Support` layer (ADR-0025)

- A new top-level `Support\` namespace, autoloaded beside `App\` and `Modules\`, declared to
  deptrac as **its own layer with zero outbound edges** and `*_Internal → Support` granted for
  every module. It is the backend counterpart of the frontend's `shared/ui`: it encodes the query
  rules and **no domain names**. It is deliberately **not** a module — it owns no domain language,
  which is the objection ADR-0024 raised against a Dashboard module.
- **Parsing and applying are separate.** The parser takes request input plus a declared allowlist
  and returns an **immutable query spec** — no database, no Eloquent, no framework request object
  beyond primitive extraction. A separate applier binds that spec to an Eloquent builder. This split
  is what makes exhaustive parser testing possible without a database, and it is the seam the unit
  tests attach to.
- **Wire syntax is flat**, never bracketed:
  `?status=under_sa_review,sa_approved&cgpa_min=3&q=…&sort=-cgpa&page=2&per_page=25`.
  Multi-value filters are comma-separated; range filters use `_min`/`_max` suffixes; sort takes a
  leading `-` for descending and accepts a comma-separated list. Each endpoint appends a stable
  unique-key tie-breaker to the effective sort for deterministic SQL and in-memory pagination.
- **The envelope is always paginated** — `{data, links, meta}` on every collection endpoint,
  unconditionally. A conditional envelope would put two `@response 200` shapes on one operation and
  generate a TypeScript union the Orval client cannot read; this project has hit that before.
  Defaults: 25 rows on work surfaces, 100 on reference and picker endpoints. `per_page` is
  **hard-capped at 100**; a larger request is clamped, not rejected.
- **Free-text `q`** is a case-insensitive substring match over a **per-resource declared field
  list** spanning both `_en` and `_ar` columns. Arabic is **normalised on both sides of the
  comparison**: alef forms folded to a bare alef, `ى`→`ي`, `ة`→`ه`, and combining diacritics
  stripped. Substring matching is chosen over Postgres full-text because there is no Arabic
  dictionary (so full-text buys Arabic no stemming) and because its tokenizer cannot match a code
  fragment against a Course code or a partial National ID. No ranking is offered.
- **Filterable if and only if readable.** An endpoint's allowlist is a function of the acting user
  wherever a field is permission-sensitive (CGPA, National ID, Mark, later Academic Standing);
  non-sensitive fields stay static. A known actor-hidden field absent from the effective allowlist
  is **ignored**, not honoured and not an error; an unknown field name is rejected. This closes an
  inference channel: `?cgpa_min=` plus `meta.total` is a CGPA oracle for anyone who can list the rows.
- **Scope and filters compose one way.** Row-level scope — the Dean's Faculty, the Advisor's
  advisees, the Department Head's Department, the Instructor's Offerings — is a base query
  constraint the controller applies and the client never supplies. Filters narrow within it and can
  never widen it. An out-of-scope filter value yields an **empty page, not a 422**, so scope
  boundaries are not probeable.

### Endpoint migration

- **Every collection endpoint adopts the contract**, including pickers and personal lists, so the
  API has one response shape. UI investment is tiered: the full `<FilterBar>` on the work surfaces,
  a type-ahead on pickers, and no visible control on single-screen personal lists.
- A **collection endpoint** returns independently filterable rows. Nested aggregate/history read
  models (`/catalog/tree`, an Application timeline, Course/Program versions, Program curriculum)
  describe one parent object and retain their complete response; they are not paginated or exported.
- `openapi.yaml` and the generated Orval client **regenerate across roughly twenty-five endpoints**.
  Both `composer api:check` and `npm run api:check` are `git diff --exit-code` gates, so this is
  part of the work and not a follow-up. Scribe documents each filter as a `@queryParam`; every
  model-bound route keeps its explicit `@urlParam … Example:` or the drift check flakes.

### Export (ADR-0027)

- **`GET /{resource}/export`** sits beside each filterable list and takes the **identical query
  string**, parsed by the same `Support` code, so the file and the grid cannot disagree. It is
  **uncapped and streamed**, which is what makes the 100-row page cap correct rather than merely
  tolerable.
- **Export belongs to Lists.** Every filterable collection, including personal collections and
  pickers, receives a sibling route unless its endpoint ticket names a specific data-minimization
  reason and asserts the route is absent. Reports export by drilling into the corresponding List.
- **True `.xlsx` via `openspout/openspout`** — a streaming writer at constant memory, wrapped in one
  `Support` class that takes a column definition and a row generator. All required PHP extensions
  (`zip`, `xmlreader`, `xmlwriter`, `mbstring`) are present on the Herd runtime. CSV is rejected on
  a domain ground rather than an aesthetic one: National ID is a 14-digit number and CSV carries no
  type, so Excel renders it in scientific notation and destroys it on save.
- **A canonical column set per resource, server-rendered in the acting user's locale**, using the
  `Accept-Language` middleware and backend translation files that have existed since Phase 0;
  Arabic exports set the sheet direction to RTL. This is an **explicit, recorded exception** to
  ADR-0024's typed-values-only rule — a spreadsheet has no renderer, so the export endpoint is the
  one place in this API where the server produces display strings. The column set is canonical and
  **not** the grid's current columns: the client is never the authority on what an export contains,
  which is also what keeps the column chooser honest. Each module supplies a separate actor-aware
  export projection (not the filter/sort allowlist), so readable-but-unfilterable fields may remain
  while a richer export never reveals a field that actor cannot read.
- **Authorized by the mirrored list's own `can:` gate and row-level scope**, adding no Permission —
  it reveals nothing the user could not already read, so this is the plain reading of the §3.2
  matrix rather than a new power (the Central Grade Entry precedent). **Every export writes an
  Audit Log entry** carrying actor, resource, filter criteria and row count. That extends the Audit
  Log from authentication events into the "sensitive-data CRUD" category CONTEXT.md anticipated.

### Aggregates and derived values (ADR-0028)

- Each owner grows **batch methods on its existing `Public` facade**. Candidate IDs and immutable
  DTOs cross module seams in bounded batches; the owner bulk-reads only its own tables and invokes
  its existing PHP calculator. No consumer joins another module's tables or reimplements academic
  arithmetic.
- Stored-column filters and sorts remain SQL-paginated. For a derived filter or sort, the complete
  authorized candidate result is calculated, filtered, and sorted before the requested page is
  sliced in memory, keeping `meta.total` and page boundaries truthful.
- A **batch-versus-single parity test** covers the seeded cohort, including rounding across 3.0,
  supersession across Fall→Spring, and `null` rather than `0.00`. Both call shapes share the same
  calculator; there is no SQL/PHP arithmetic duplication.

### Reports (ADR-0026)

- **No Reporting module.** Each Report lives in the module owning its spine data and reaches
  sideways through `Public` facades whose edges are **already granted** — so this phase adds **zero
  new deptrac edges**. The SPA composes one Analytics section: *owner supplies, shell arranges*, the
  rule the side nav and the Dashboard already follow.
- **Six Reports.** Application Pipeline (Admissions) · Enrollment Summary, Course Registration
  Summary and Attendance Analytics (Enrollment) · GPA Distribution (Grading) · Audit Log (Identity,
  which also builds the system's first audit read surface). **Enrollment Summary is owned by
  Enrollment, not Identity** — Identity is the one module with no outbound edges and must not gain
  one for a view.
- **Gates mirror ordinary-screen scope.** Application Pipeline is unscoped for Student Affairs and
  Faculty-scoped for Deans. GPA Distribution starts from the Student-Grades scope: Advisor advisees,
  Instructor teaching roster, Department Head Departments, Dean Faculties, and the existing
  unscoped Student Affairs/Council or administrator override. Enrollment and Registration summaries
  use equivalent existing Student/Offering scopes. Attendance Analytics is only Instructor-owned
  Offerings, Advisor advisees, and R09/R10 override; it does not grant Department Head, Student
  Affairs, Dean, or Council a new attendance surface. Filters only narrow, and every role has
  in-scope and out-of-scope tests.
- **Parameters and drill-through.** A Report's parameters ride the same query contract as the grids;
  a chart segment navigates to the filtered list behind it, which exports. No cross-filtering
  between charts and no client-side pivoting.

### Frontend

- **New `shared/ui` primitives** — `<FilterBar>`, `<DataToolbar>`, pagination controls, a URL-state
  hook, a `<Chart>` wrapper and its `<ChartDataTable>` — each encoding the rules and **no domain
  names**, the `<StatusChip>`/`<DashboardCard>` discipline. They render into the plain MUI `<Table>`
  already in use. **`@mui/x-data-grid` is still not adopted**: with filtering, sorting and paging
  server-side the grid is dumb, and the community tier's single-criterion filter panel is precisely
  what this phase replaces.
- **Charts via `@mui/x-charts`** — the same MUI X family as the installed tree view, so it inherits
  the theme, CSS variables, dark mode and the `direction` flip `AppThemeProvider` already performs;
  series colours come from `design-system.md` §14's existing palette. **Every chart is `role="img"`
  with a concise label plus a "View as table" disclosure** rendering the identical series as a real
  table with scoped headers. Each table row exposes the same drill-through as a keyboard-operable
  link; chart pointer interaction is never the only route. Version alignment with the installed MUI X major is to be **verified at
  install**.
- **URL is the single source of truth** for filter, sort and page state. Drill-through is an
  ordinary link. Export uses the same query string with `/export`, fetched through the authenticated
  Axios client and downloaded from an in-memory blob; bare export links are forbidden.
- **A column chooser** affects the screen only and never the file. **A print stylesheet** renders a
  Report cleanly on paper with no PDF dependency.
- **Global search** fills the Phase-0.5 app-bar slot as a **client-side fan-out**: the shell calls
  each permitted module's own list endpoint at `?q=&per_page=5` and groups the results. Each request
  is independently authorized and independently failing, exactly as Dashboard Cards are — so there
  is no cross-module server endpoint, no new module, and no deptrac change. This is why the `App`
  layer's missing `Admissions`/`Notifications` edges stay **recorded, not fixed**.
- **Richer Dashboard Cards** per the ADR-0024 amendment: headline figures, a term-over-term delta,
  and at most one chart **over data already scoped to the acting user**. A Card takes no parameter;
  the moment it would, it is a Report.

### Bulk Course import

- Closes **SRS FR-PCM-003.5**. Two calls: **upload → dry-run preview** returning rows to create,
  rows to update, and rows rejected with per-row per-column reasons, **writing nothing**; then
  **confirm → apply the whole file in one transaction or none**. Every created or updated Course
  writes its own ADR-0011 version snapshot, while one Identity Audit Log event records the import
  identifier, actor, source filename, counts, and transaction outcome.
- The uploaded file is held on the private local disk under a ULID between the two calls, reusing
  the ADR-0016 pattern rather than persisting a parsed preview.
- Gated on `catalog.manage`. Both English and Arabic names are required per row, as the Catalog
  demands. **The Courses export is the import template**, so the loop is export → edit → preview →
  apply. MIME, size, workbook structure, enum values, and credit-hour arithmetic are validated;
  ADR-0005 explicitly defers malware scanning, and the private file is deleted after confirmation
  or expiry. The importer recognizes the canonical English and Arabic header/enum dictionaries,
  rejecting mixed or unknown labels, so exports from either locale round-trip deterministically.

### Seeder

- **Every named persona stays exactly as written** — the existing smokes assert against them and
  must not move. A **deterministic generated cohort of roughly 150–250 Students** is layered on top
  from a fixed seed, spread across Programs, Semesters, grades and attendance, inside the reserved
  namespace, so a distribution has shape and pagination genuinely pages. Still idempotent, still
  relative-to-seed-time.
- Existing count-based assertions across Pest and Playwright are **audited as part of this change**;
  this is the item most likely to be underestimated.

## Testing Decisions

**What makes a good test here.** A test asserts what a caller can observe — a response envelope, a
row set, a status code, a rendered control, a file's contents — never how the answer was reached. It
does not assert that a particular query builder method was called, that a spec object has a
particular internal shape, or that a component uses a particular hook. Two properties are
security-relevant and must be asserted **positively and negatively**: a user sees what they should,
and a user does **not** see what they should not — the discipline `dashboard.spec.ts` established in
Phase 4.5 by asserting Card presence *and* absence.

**Five seams, four already existing.**

1. **HTTP feature seam** — `tests/Feature/<Module>/`, the highest and primary seam (70 files of
   prior art, e.g. the Phase-4.5 Card endpoint tests). Proves everything user-observable: filtering,
   sorting, pagination and `meta`; allowlist enforcement including the actor-aware cases; scope
   composing with filters and out-of-scope values returning empty; export bytes and its Audit Log
   entry; Report payloads and their gates; import preview then apply; the per-module search results
   global search fans out over.
2. **`Public` facade seam** — `tests/Feature/<Module>/*RecordsTest.php`, an **existing** seam with
   direct prior art in `Feature/Grading/GradingRecordsTest.php` and
   `Feature/Enrollment/EnrollmentRecordsTest.php`. This is where **batch-versus-single parity** lives:
   it calls both facade forms over the seeded cohort and asserts equal results, plus the three subtle
   cases named above, truthful totals, ordering, and slicing after derived filtering.
3. **Pure unit seam** — `tests/Unit/Support/`, one **new directory under an existing convention**
   (prior art: `Unit/Grading/GpaCalculatorTest`, `Unit/Enrollment/CreditHoursValidatorTest`,
   `Unit/Admissions/BusinessDaysTest`). Proves the parser exhaustively with no database: operator and
   multi-value parsing, range suffixes, sort parsing and rejection of unknown fields, `per_page`
   clamping, and Arabic normalisation across every fold. **A parser that cannot be tested here has
   been built wrong** — that is the seam's purpose, not an incidental benefit.
4. **Vitest + RTL** — existing seam, non-trivial components only: `<FilterBar>`'s states, the
   URL-state hook's round trip, and **every chart's table matching the series it was given**, so the
   accessibility affordance cannot drift from the picture.
5. **Playwright** — existing seam. **One comprehensive multi-flow smoke**, the Phase-2 precedent
   (`admissions.spec.ts` chained applicant → SA → Dean → interviewer → Student) rather than one smoke
   per flow: filter a queue → export it → open Analytics → drill a chart segment back into that
   filtered queue. Plus **axe on Analytics across light/dark × EN/AR**, the Phase-0.5 and Phase-4.5
   discipline. The suite is **serial by config and must stay that way**.

**The shared contract test.** One cross-module `tests/Feature/Support/QueryContractTest.php` holds
the full `(endpoint, actor, expected scope)` dataset and the four assertions every filterable
endpoint must satisfy — unknown fields rejected, scope composes with filter, actor-aware fields stay
hidden, `meta` correct. It is deliberately the **first backend test file that cuts across module
boundaries**, because the contract is one thing and should be proved in one place: adding an endpoint
is one dataset row, and omitting its authorization checks fails the build. Module-specific
authorization subtleties stay in that module's own feature tests.

**Export is verified end to end once.** One `.xlsx` is read back with openspout's reader — headers in
the requested locale, a National ID cell typed as text, RTL on the Arabic sheet — and the remaining
resources assert their column definitions and row generators only, so cost stays linear in resources
rather than quadratic.

**Gates.** `composer gate` (Pint, PHPStan/Larastan L6, deptrac — now including the `Support` layer's
zero outbound edges, Pest, OpenAPI drift) and `npm run gate` (api:check, ESLint + boundaries,
Prettier, `tsc`, Vitest, Playwright) must both be green on a fresh seed. Remember PHPStan infers
columns from `database/migrations` only, so anything a module migration adds needs an explicit
`@property`.

## Out of Scope

- **Saved or named filters**, and any filter persistence beyond the URL. No `saved_filters` table,
  no per-user recall.
- **Cross-filtering between charts**, client-side pivoting, and any ad-hoc report builder.
- **Caching or materialising any derived value** — explicitly rejected in ADR-0028; ADR-0005's
  no-cache rule stands unqualified.
- **`pg_trgm` indexes and Postgres full-text search**, recorded as the later optimization in the
  same sense ROADMAP §1 records Redis caching.
- **The three Phase-5-dependent Reports** — Academic Standing, Graduation Readiness, Termination —
  which remain Phase 7, arriving with the Phase 5 read models they need.
- **Scheduled, emailed or queued exports**, and PDF export of Reports. There is no email and no
  queue worker in this build; Phase 6 owns Browsershot, and only optionally.
- **Real-time or auto-refreshing Analytics** — no websocket (ADR-0005).
- **Dashboard Card personalisation** (reorder, hide, pin, per-user layout), still deferred from
  Phase 4.5.
- **A new Permission or Role of any kind.** ADR-0008 stands; ADR-0026 and ADR-0027 both reach their
  conclusions without editing the matrix.
- **Any new migration**, other than none: this phase adds no table and no column. Bulk import writes
  through the existing Catalog surface and its existing version-snapshot mechanism.
- **Bulk import of anything other than Courses**, and import of any kind through the export route.
- **Fixing deptrac's missing `App → Admissions/Notifications` edges** — recorded, not fixed, and this
  phase deliberately does not force the issue.
- **Renaming the four existing `*Panel` components**, still deferred from Phase 4.5.

## Further Notes

- **Sequencing is load-bearing and not a preference.** This phase changes the response envelope of
  every collection endpoint. Placing it before Phase 5 means Phases 5, 6 and 7 are born filterable
  and exportable; placing it after means every list those phases build is migrated a second time.
  Phase 4.5's review gate and PR to `main` were prerequisites and are now satisfied.
- **This is the largest phase yet — 17 tickets against Phase 4.5's nine**, and it grew
  during design when bulk import, the column chooser and the print view were pulled in. The DoD's
  "one Playwright smoke per phase" is honoured through one comprehensive multi-flow smoke rather
  than by relaxing the rule.
- **Two risks worth naming at ticket-planning time.** First, the seeded cohort growing to 150–250
  Students will move count-based assertions in existing Pest and Playwright tests — a wide, boring,
  easily-underestimated job. Second, the envelope change forces an `openapi.yaml` and Orval
  regeneration across ~25 endpoints, and both `api:check` gates only mean anything once the
  generated files are committed.
- **`@mui/x-charts` version alignment with the installed MUI X major is asserted, not verified.**
  Check it at install before the ADR reads as established fact.
- **NFR-PRF-008 (reports ≤ 30s) and NFR-PRF-010 (search ≤ 1s) are informal targets here, not
  gates**, per ADR-0005's deferral of scale NFRs. Search rests on unindexed normalised expressions,
  which is accepted at this build's scale and is exactly what `pg_trgm` would later address.
- **The `Support` layer is the phase's one genuinely new architectural idea**, and its safety rests
  on a property deptrac enforces rather than one the team agrees to: **zero outbound edges**. A
  `Support` class that needs to know what a Program is has been written in the wrong place, and a
  future `Support → Modules` or `Support → App` edge is a regression of ADR-0025 rather than a
  convenience — the same standing ADR-0022 gave the deliberately-absent `Enrollment_Internal →
  Grading_Public` edge.
- **A Report with no natural owning module is the signal to revisit ADR-0026**, not to force a home.
  If one arrives that genuinely spans modules with no spine, that is evidence a Reporting module was
  right after all, and the ADR should be superseded rather than worked around.
