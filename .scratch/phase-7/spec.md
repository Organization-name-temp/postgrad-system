# Spec — Phase 7: Academic Reports and Bilingual Polish

**Status:** ready-for-agent
**Feature slug:** phase-7
**Phase:** 7 of the Build Roadmap
**Scope:** the three remaining SRS §4.12 Reports and the app-wide EN/AR polish pass
**Implementation dependency:** missing Phase 4.6 Analytics/report presentation capabilities; see Further Notes
**Sources:** SRS §4.8 and §4.12; business requirements for academic progress and termination; CONTEXT; the Phase 7 roadmap; the Phase 4.6 specification; design-system rules; accepted ADR-0024–0029, ADR-0034–0041 and ADR-0045.

## Problem Statement

Student Affairs, Advisors, Department Heads, Deans and Council can inspect individual
academic records or perform scoped Termination Case reviews, but cannot ask population-level
questions about Academic Standing, Graduation Readiness or termination. Reviewing Students
one by one makes it difficult to identify cohorts needing support, understand why apparently
completed Students are not ready, or track recommendations and final termination decisions.

Users also need consistent English and Arabic presentation across the completed application.
Missing translations, inconsistent direction, clipped labels and untranslated errors undermine
the bilingual contract even when the underlying academic workflows work.

## Solution

Provide three read-only, parameterised Reports owned by AcademicStanding and composed into
the application's Analytics section: Academic Standing, Graduation Readiness and Termination.
Each combines scoped summary figures, an accessible chart/table representation, and a
drillable filtered List. Users can search, filter, sort, page, export the complete filtered
List as XLSX, and browser-print the Report in either language.

Reuse Phase 5's authoritative calculations and case histories. Reports do not decide
graduation, reconcile academic state, recommend termination or replace operational review
queues. Complete a bounded bilingual polish pass over existing surfaces, with English/Arabic
coverage, correct LTR/RTL behaviour, and light/dark presentation.

## User Stories

1. As a Student Affairs staff member, I want to see Students grouped by Academic Standing, so that I can identify populations needing intervention.
2. As an Advisor, I want the Standing Report scoped to my current advisees, so that I can focus on Students I support.
3. As a Dean, I want the Standing Report scoped to my assigned Faculties, so that I can monitor their academic progress.
4. As a Department Head, I want Reports scoped to my assigned Departments, so that I can monitor the Students under my authority.
5. As a Council member, I want authorized Standing and termination summaries, so that I can understand the population behind my review work.
6. As an authorized staff member, I want Not Yet Assessed distinguished from Good Standing, so that missing finalized Grades are not represented as success.
7. As an authorized staff member, I want Warning, Probation and Termination Pending distinguished, so that different academic conditions remain clear.
8. As an authorized staff member, I want Student Lifecycle Status separate from Standing, so that final termination is not mistaken for another calculated standing band.
9. As an authorized staff member, I want Program, Faculty, Department and degree filters, so that I can narrow my permitted population.
10. As an authorized staff member, I want search by Student name and University ID, so that I can find a Student within a Report.
11. As an Arabic-speaking staff member, I want normalized Arabic search, so that spelling variants and diacritics do not prevent matching.
12. As an authorized staff member, I want to click a Standing segment and see exactly its Students, so that I can investigate the summary.
13. As a Student Affairs staff member, I want a Graduation Readiness Report, so that I can identify Students whose academic requirements are satisfied.
14. As an Advisor, I want readiness blockers for my advisees, so that I can explain what still prevents readiness.
15. As a Department Head, I want Remaining CH and CGPA beside readiness, so that I can understand progress without reconstructing the rules.
16. As an authorized staff member, I want Warning Students included when requirements and CGPA qualify, so that an extra Good Standing condition is not invented.
17. As an authorized staff member, I want actionable Termination Pending shown as a readiness blocker, so that unresolved due process is visible.
18. As an authorized staff member, I want readiness to reflect dismissal or trigger clearance on a fresh read, so that resolved blockers do not persist as cached balances.
19. As an authorized staff member, I want unavailable CGPA distinguished from zero CGPA, so that missing assessment is not treated as failure.
20. As an authorized staff member, I want multiple readiness blockers shown together, so that one explanation does not hide another.
21. As an Advisor, I want Students with 1–5 Remaining CH identifiable separately from schedule-qualified Approaching Graduation, so that proximity is not confused with a registration exception.
22. As an authorized staff member, I want unavailable curriculum data identified explicitly, so that incomplete evidence is not silently omitted or classified as ready.
23. As a Student Affairs staff member, I want termination workflow summaries and history, so that I can track recommended and approved termination.
24. As a Dean, I want scoped termination summaries, so that I can monitor departmental due process without receiving Council decision authority.
25. As a Council member, I want recommended Cases distinguished from earlier review stages, so that I know which Cases have reached the Council.
26. As an authorized staff member, I want dismissed, Trigger Cleared and Evidence Changed outcomes distinguished from termination, so that closed Cases are not all counted as approved decisions.
27. As an authorized staff member, I want replaced Cases retained in historical reporting, so that earlier reviews remain traceable.
28. As an authorized staff member, I want distinct Student counts separate from Case counts, so that replacement histories do not inflate the population.
29. As an authorized reviewer, I want a permitted link from a reported Case to my existing review surface, so that I can act through the established workflow.
30. As an authorized staff member, I want bookmarked filter URLs and browser Back support, so that I can return to an investigation.
31. As an authorized staff member, I want stable sorting and truthful pagination totals, so that paging does not lose or duplicate rows.
32. As an authorized staff member, I want all filtered rows exported rather than only the current page, so that offline review covers the full selected population.
33. As an Arabic-speaking staff member, I want Arabic spreadsheet headers and RTL sheet direction, so that exported data is usable in my language.
34. As an auditor, I want exports attributed and recorded with their criteria and row count, so that disclosure is traceable.
35. As a keyboard or screen-reader user, I want every chart available as a matching table and drill-through control, so that Reports do not depend on visual interpretation.
36. As a bilingual user, I want language switching to preserve my Report filters, so that I do not lose my investigation.
37. As a user, I want clear loading, empty, unavailable and retry states, so that failed requests cannot masquerade as zero results.
38. As a user with several Roles, I want my permitted scopes combined, so that one Role does not hide access granted by another.
39. As a Student, I want my existing personal academic views preserved without access to other Students' Reports, so that personal access stays private.
40. As a bilingual user, I want existing forms, validation, navigation, notifications and documents checked in both languages, so that the application feels consistent beyond Analytics.
41. As an Arabic-speaking user, I want readable Course codes, University IDs, dates and numbers within RTL layouts, so that mixed-direction content is not reordered incorrectly.
42. As a user, I want readable Reports in light and dark themes and browser printing, so that each supported presentation remains usable.

## Implementation Decisions

### Ownership and prerequisite boundary

- AcademicStanding owns all three Reports, their cohort Lists and exports. Identity owns
  Student anchors, assignments and lifecycle; Catalog owns academic labels; Grading and
  Enrollment own Grade and credit inputs. Resolve those through existing Public contracts
  and DTOs. No Reporting module, cross-owner SQL joins, Internal imports across modules,
  or cross-module foreign keys.
- Reuse the implemented Support collection query parser, Arabic normalizer and XLSX
  exporter. Reuse Phase 4.6's accepted query, export and Analytics design. Missing platform
  capabilities are an explicit prerequisite, not assumed existing implementation.
- Extend owner Public facades with bounded batch reads where needed. Batch results must
  match single-Student calculations and use the same owner calculators. Scope candidates
  before derivation; apply derived filters/sorts before pagination. Do not add stored
  standing/readiness balances, report caches, snapshots or projections.
- No new Role, Permission, central provider registry or reporting table is required.
  Update explicit dependency rules only for actual Public-contract consumption.

### Access and population

- Gate the three cohort Reports and their Lists/exports on the existing
  `standing.monitor` Permission plus staff oversight scope. Follow existing academic
  oversight access: Student Affairs and Council have university-wide scope, Advisors have
  live advisees, Deans have assigned Faculties, Department Heads have assigned Departments,
  and administrators retain their explicit override. Student-only and Instructor-only
  Users receive no cohort Report access. This follows ADR-0026's underlying-data rule,
  including Council's existing Standing access, rather than treating SRS §4.12's role
  examples as new Permissions.
- Combine scopes granted by multiple eligible Roles as a union. Filters only narrow
  that union. Resolve assignments on each request; previous assignments grant no scope.
- Termination reporting exposes a minimal oversight summary. Existing operational gates
  remain authoritative for assessment, recommendation, Council decisions and detailed
  evidence. A summary link must not imply permission to open a restricted queue or act.
- Standing/readiness population defaults to Active Students, with an explicit lifecycle
  filter to include historical Terminated Students. Never label Terminated as an extra
  Academic Standing enum or classify a Terminated Student as currently eligible to graduate.
  Historical rows show lifecycle and advisory academic facts separately.
- Unknown or missing curriculum data is an explicit unavailable result, included in totals
  and identifiable in the List. Do not fabricate zero Remaining CH, readiness or blockers.

### Report semantics

- Academic Standing groups by the five authoritative enum values and shows Student name,
  University ID, Program, lifecycle, nullable CGPA, current Standing and its explanation.
  Recompute through StandingRecords; stale/missing Observations are not current truth.
- Graduation Readiness shows the owner's `ready`, Remaining CH, nullable CGPA,
  `approachingGraduation` and complete typed blocker set. Use the Student's bound Curriculum
  Version. Warning alone is not a blocker. Termination Pending blocks readiness until the
  authoritative owner clears it. CGPA rounding and thresholds stay owner-controlled.
- Provide ready/not-ready/unavailable totals and blocker counts. A Student may appear in
  several blocker groups, so label those counts as overlapping and never sum them into a
  distinct Student total. Clicking a blocker produces the matching Student List.
- Provide a separately labelled “1–5 CH remaining” proximity filter. Do not rename it
  Approaching Graduation: the existing owner contract additionally requires a completing
  proposed for-credit schedule. The Report supplies no hypothetical schedule, and must
  preserve the owner's no-proposal result. Schedule selection and exception eligibility
  remain in registration workflows.
- Termination reporting is Case-based, retaining all existing workflow states and terminal
  outcomes. Default to non-terminal Cases plus final Terminated decisions; an explicit
  history filter includes dismissed, Trigger Cleared and Evidence Changed Cases. Define
  “recommended” as the Department Head recommendation reaching Awaiting Council, not an
  initial flag or Advisor assessment. Use actual owner state values in the API.
- Show Case reference, Student identifiers, Program/lifecycle, state and available
  workflow timestamps. Group counts by state and separately expose distinct Students.
  Filter Case-created dates as dates of Cases, not dates of academic evidence. Historical
  counts must not imply that a replaced Case remains actionable.
- GET report/list reads must not create Cases, append Observations, notify Users,
  reconcile triggers, convert Incompletes, graduate Students or mutate lifecycle. Existing
  event/command reconciliation remains responsible for workflow changes.

### Query, API and presentation

- Each Report exposes an aggregate summary and an independently pageable List. Summary,
  chart, drill-through List and export share the same scoped selection semantics. Summary
  totals describe the complete filtered set, never the current page. Stable identifiers
  break sort ties. Do not promise a frozen snapshot between separate requests; fresh reads
  may reflect committed academic changes.
- Common filters cover Faculty, Department, Program, degree, lifecycle and bilingual
  name/University-ID search. Report-specific filters cover Standing; ready/unavailable,
  blockers, Remaining CH and nullable CGPA ranges; and Case state/history/created-date
  range. Default to current academic facts; no historical-as-of Standing calculation.
- Use flat documented query parameters, actor-readable allowlists, deterministic sorting,
  standard pagination and the existing maximum of 100 rows per page. Preserve the query
  parser's unknown-field rejection and actor-hidden-field behaviour. Out-of-scope academic
  identifiers yield an empty scoped result rather than broaden access.
- Export siblings render the complete filtered selection through the same selection
  logic, excluding pagination. Canonical columns are system-defined and authorized;
  headers/statuses use the request language, Arabic sheets use RTL, identifiers remain
  strings, and exports are audited with actor, criteria and row count. Do not include
  National ID/passport, private evidence, review narratives or bearer links in these Reports.
- Generate OpenAPI from documented controllers/resources, then regenerate committed SPA
  types/hooks. Do not edit generated artifacts manually.
- Frontend academic domain code belongs to the academic-standing feature; the app composes
  Analytics routes/navigation from permitted owner contributions. No feature-to-feature
  imports. Reuse shared filters, tables, charts, status components and URL-state conventions
  once the platform prerequisite supplies them. No Data Grid migration is required.
- Summary sections are parameterised Report content, not new parameterless Dashboard Cards.
  Existing Cards may link to the Reports but do not gain report filters.
- Support chart-as-table, keyboard drill-through, loading/empty/error/unavailable states,
  browser printing and preserved URL filters on language switch. Label readiness advisory
  and use text/icons as well as colour. Styling uses theme/design tokens.

### Bilingual polish and acceptance

- Audit existing authentication, navigation, Dashboard, Catalog, Admissions, Enrollment,
  Grading, academic progress/termination, Records and public Transcript verification
  surfaces. Fix demonstrable missing translation keys, untranslated user-facing messages,
  incorrect direction, clipped content and mixed-script layout defects. Preserve existing
  business behaviour and bilingual Transcript printing.
- Add backend messages in EN/AR together and update both frontend dictionaries. Preserve
  dynamic validation messages, status/blocker labels and typed notification rendering.
  Test Arabic names alongside isolated LTR identifiers; logos and QR codes are not mirrored.
- Review light/LTR, light/RTL, dark/LTR and dark/RTL; verify responsive tables, dialogs,
  form controls, navigation and report print output. Record each discovered defect and its
  disposition rather than declaring unspecified “niceties” complete.
- Acceptance requires scoped report/list/export agreement, parity with owner calculations,
  synchronized generated contracts, accessible bilingual presentation, green affected
  backend/frontend gates, the serial headline journey and manual review of the demo.

## Testing Decisions

- The user confirmed the proposed existing seams: PostgreSQL-backed Pest HTTP integration
  tests, focused Vitest/RTL interactions, and one serial full-stack Playwright journey.
  Test observable behaviour, disclosure, response metadata and files rather than private
  helpers or a second implementation of the academic rules.
- Use AcademicStanding Standing/readiness and Termination Case feature tests as academic
  prior art; existing termination and Records collection/export tests as query/export
  prior art; Support tests for parsing/normalization; and Dashboard/Records browser tests
  for Role composition, language persistence and serial seeded journeys. Phase 4.6 Report
  tests are planned prior art until implemented, not existing executable evidence.
- Cover each allowed Role and denied Role, live/former assignments, multiple-role scope
  union, administrators, cross-scope filters, direct API access and export authorization.
  Assert bodies and totals so aggregates cannot leak forbidden Students.
- Prove batch/single parity on real owner data: all Standing values, null versus zero CGPA,
  Grade supersession/corrections, effective credit decisions, admission-bound curricula,
  Warning with satisfied requirements, Termination Pending and its cleared/dismissed
  outcomes, missing curriculum, and lifecycle separation. Verify no-proposal Approaching
  Graduation is not replaced by a Remaining-CH heuristic.
- Exercise overlapping blocker counts, exact drill-through membership, Case replacements,
  unique Student versus Case counts, recommendation stages, date boundaries, derived
  filter/sort-before-page behaviour, stable ties, truthful totals and empty results.
- Assert repeated GETs have no workflow writes or notifications and ignore stale Standing
  Observations. Use the established reconciliation mechanism to prepare workflow fixtures.
- Verify XLSX content through the reader: full selection beyond one page, canonical
  localized columns, string identifiers, Arabic direction, hidden-field exclusion and
  audit criteria/count. Do not retest the exporter's internal implementation per Report.
- Focus Vitest on filter URL round trips, chart/table agreement, keyboard drill-through,
  multiple blockers, unavailable states, access-aware links and language switching. Add
  targeted regressions for concrete polish defects, not snapshots of every existing page.
- One serial Playwright journey opens the three Reports under appropriate scoped staff
  accounts, filters and drills into a population, downloads an export, follows an allowed
  detail link and switches EN/AR without losing criteria. Include light/dark × EN/AR
  accessibility checks on new Reports and review browser printing. Preserve named seeded
  personas; add deterministic academic cases only where existing fixtures lack coverage.
- Implement behaviour test-first. Run narrow tests during iteration, then composer gate
  and the frontend gate with migrated/seeded PostgreSQL. Record gate results and concrete
  reasons for any gate not run; manual review remains required before phase completion.

## Out of Scope

- Implementation of Phase 4.6's five original Reports, Attendance Analytics, global search,
  bulk Course import, richer Dashboard Cards or general platform rollout across old Lists.
  Those remain their existing effort; needed Analytics/chart/filter primitives are a
  declared dependency, not evidence that this work already shipped.
- New graduation approval, Graduated lifecycle, reinstatement, termination workflow stages,
  academic arithmetic, standing thresholds, credit allocation or registration exceptions.
- Historical-as-of academic snapshots, persisted derived balances, aggregate caching,
  new report tables, scheduled/emailed Reports, saved filters, pivots, chart cross-filtering
  and an ad-hoc Report builder.
- Server PDF generation, cryptographic Transcript signatures, payment integrations,
  email/SMS, websockets, production infrastructure and production performance certification.
- Broad UI redesign, new component libraries, speculative asset recreation, unrelated
  cleanup and unspecified deferred niceties. Fixes require a concrete bilingual or
  presentation defect within this phase's audit.
- Expanding academic/evidence access solely because a user can view an aggregate Report,
  exposing private identifiers or review narratives in exports, or adding report-triggered
  academic mutations.

## Further Notes

- Repository inspection was performed on main after Phase 6 merged through PR #10.
  Phase 5 rules and Phase 6 Records services are implemented. Publication of this spec
  authorizes planning output only; it does not mark Phase 7 implemented or authorize a
  code implementation.
- The roadmap and handoff overstate Phase 4.6 delivery. Its merged PR #8 contains planning
  documents and tickets; the Phase 4.6 spec is still ready-for-agent. Current main has
  Support query/export primitives and later phase collection consumers, but no Analytics
  composition, the six planned Report implementations, or the shared report/filter/chart
  presentation described by Phase 4.6. The earlier conversational claim that these were
  delivered is corrected here. Implementation planning must verify and deliver the needed
  prerequisites through the Phase 4.6 effort before relying on them.
- Use the existing ADRs as architectural authority. If implementation changes their
  approved access or academic semantics, record an explicit amendment rather than silently
  widening this specification.
- No interview or new business-policy decision was required. The access, readiness and
  termination definitions above follow executable Phase 5 contracts and accepted ADRs;
  the testing seam check was confirmed by the user.
- Next planning step is decomposition into local vertical-slice tickets with explicit
  Phase 4.6 dependencies. This specification does not create implementation tickets.
