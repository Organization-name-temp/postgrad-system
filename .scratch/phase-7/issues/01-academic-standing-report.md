# 01 — Academic Standing Report

**What to build:** Authorized academic staff open Analytics, narrow their permitted Student population, inspect current Academic Standing, drill from a summary segment into its exact Student List, and export or print the result in English or Arabic. This first academic Report establishes the reusable cohort scope and selection behaviour for the other two Reports.

**Blocked by:** Phase 4.6 #08 — Analytics section + Application Pipeline Report + drill-through; Phase 4.6 #15 — Print stylesheet for Reports. Their own blocking edges remain in force. Verify executable delivery before starting: merged planning documents do not satisfy these prerequisites.

**Status:** ready-for-agent

- [ ] AcademicStanding owns the Report, summary, pageable List and export. Cross-module facts arrive through owner Public contracts and DTOs; no new Reporting module, stored standing balance or cross-owner SQL join is introduced.
- [ ] Backend access requires `standing.monitor` and an eligible oversight Role or administrator override. Student Affairs/Council see the university population, Advisors live advisees, Deans assigned Faculties, and Department Heads assigned Departments. Student-only and Instructor-only Users cannot access cohort APIs or navigation.
- [ ] Eligible scopes combine as a union for multiple-role Users. Reassignment takes effect on the next request. Academic filters only narrow this base scope; cross-scope identifiers yield empty results.
- [ ] Active Students are the default population; a lifecycle filter permits historical Terminated rows. Lifecycle remains separate from the five authoritative Standing values and never becomes a sixth Standing band.
- [ ] Rows show Student name, University ID, Program, lifecycle, nullable CGPA, derived Standing and explanation. Current values come from StandingRecords, not stale/missing Observations. Missing supporting academic data has an explicit unavailable representation rather than fabricated values or silent omission.
- [ ] Faculty, Department, Program, degree, lifecycle, Standing and bilingual name/University-ID search work from URL parameters. Arabic search uses existing normalization. The URL supports bookmarking, browser Back and language switching without lost criteria.
- [ ] Bounded batch acquisition/calculation reuses authoritative owner calculators and agrees with single-Student reads. Derived filtering/sorting precedes page slicing; stable identifiers break ties, totals cover all matches and page size is capped at 100. Preserve actor-readable allowlists and unknown-field validation.
- [ ] Chart and accessible table represent identical full-selection Standing counts. Both offer keyboard-operable links to exactly matching filtered Students; individual detail links respect their existing backend gates.
- [ ] XLSX exports contain the complete filtered population with canonical authorized columns, localized labels, string identifiers and Arabic sheet direction. Record actor, effective criteria and row count in the Audit Log; exclude National ID/passport, review narratives, private evidence and bearer links.
- [ ] Repeated Report/List GETs create no Cases, Observations, notifications, Grade conversions or lifecycle changes. A fresh read may reflect committed changes; separate requests do not claim a frozen snapshot.
- [ ] OpenAPI and generated SPA hooks/types are regenerated from documented controllers/resources. Frontend academic code stays within its owner feature; app composition and shared UI maintain feature/module boundaries.
- [ ] Loading, empty, error/retry and unavailable states distinguish failure from zero results. Report charts/tables, status text, filters and browser printing work in EN/LTR and AR/RTL, light/dark themes, using design tokens. Printing includes title, active criteria and matching chart/table content.
- [ ] Test-first Pest HTTP coverage proves allowed/denied Roles, scope union/reassignment, hidden-data protection in counts and exports, all Standing values, null versus zero CGPA, stale Observations, batch/single parity, derived pagination and side-effect-free reads. Read an exported workbook back to verify content and Audit Log criteria.
- [ ] Focused Vitest/RTL tests cover URL filters, chart/table agreement, keyboard drill-through and language switching. Existing named demo personas remain intact; add deterministic cases only where needed. Run narrow tests and affected package checks, reporting any unrun check with its reason.

## Comments

The seven-ticket breakdown and these direct blocking edges were approved by the user. Phase 4.6's broader report/platform work stays in its own effort. Work only starts when these prerequisites are implemented and verified.
