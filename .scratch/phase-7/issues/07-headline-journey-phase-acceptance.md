# 07 — Headline journey and Phase 7 acceptance

**What to build:** Demonstrate and verify that authorized staff can use all three academic Reports, investigate an exact population, export it and switch languages without losing their work, while the application's bilingual polish and package quality gates have reproducible acceptance evidence.

**Blocked by:** Phase 7 #01 — Academic Standing Report; #02 — Graduation Readiness Report; #03 — Termination Report; #04 — Bilingual polish: shell, Catalog and Admissions; #05 — Bilingual polish: Enrollment, Grading and academic progress; #06 — Bilingual polish: Records and public verification.

**Status:** ready-for-agent

- [ ] Add one serial Playwright headline journey using appropriate scoped staff accounts. Open all three Reports, filter a cohort, drill from a chart/table into matching rows, download and inspect an export, follow an authorized detail/review link and switch EN/AR without losing active criteria.
- [ ] Journey assertions establish meaningful membership/count agreement and role visibility rather than only checking page headings. Preserve named seeded personas and mutable-account conventions; add deterministic cases only where necessary. Wait on observable authenticated data and saved language preferences, not arbitrary delays.
- [ ] Check new Report accessibility in light/LTR, light/RTL, dark/LTR and dark/RTL, including keyboard drill-through and chart-as-table. Review browser print title, criteria, chart/table readability, direction and page fit; retain Records print regressions.
- [ ] Consolidate #04–#06 audit records so every specified existing surface has coverage and each discovered defect has a fix or explicit disposition. Report findings requiring separate scope rather than silently broadening Phase 7.
- [ ] Verify report/list/export access, scope union, derived-result parity, no workflow writes on GET, generated API synchronization and module/frontend boundaries through the existing focused tests and package gates. Broaden testing only for a concrete unresolved risk or a required gate.
- [ ] Run the backend gate and frontend gate against PostgreSQL with the required migrated/seeded development fixture. Browser tests remain serial; do not reseed or run competing journeys during the suite. Record exact commands, results and concrete reasons for any gate not run.
- [ ] Review handwritten and generated diffs separately. Confirm that academic rules, private evidence access, immutable Transcript content and deferred features were not accidentally changed.
- [ ] Write a reproducible EN/AR headline demo and acceptance evidence. Update phase-status/source-of-truth documents to the verified state, including any prerequisite status correction supported by Git and executable evidence. Do not infer Phase 4.6 completion from planning merges.
- [ ] Present the completed result for the required manual review before declaring the phase accepted. Missing gates or unresolved scope-critical findings remain explicit outstanding work; publishing this ticket does not authorize merge, push or deployment.

## Comments

The approved testing seams are PostgreSQL-backed Pest HTTP tests, focused Vitest/RTL interactions and one serial Playwright headline journey. Individual tickets own their behaviour tests; this ticket proves integration and records phase acceptance.
