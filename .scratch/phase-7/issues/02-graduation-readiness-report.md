# 02 — Graduation Readiness Report

**What to build:** Authorized staff inspect advisory Graduation Readiness for their permitted Students, identify every blocker, distinguish incomplete evidence from unmet requirements, and drill into/export the population needing a particular intervention.

**Blocked by:** Phase 7 #01 — Academic Standing Report, which establishes academic cohort scope, selection and Analytics integration.

**Status:** ready-for-agent

- [ ] AcademicStanding supplies a readiness summary, pageable Student List and audited export using #01's staff gate, live scope union and common academic/lifecycle/search filters. Direct API access and export enforce the same scope as presentation.
- [ ] Reuse GraduationProgress and the authoritative Credit-Hour/Grade owners with bounded batch reads and single-Student parity. Requirements use admission-bound Curriculum Versions; the frontend and Report do not duplicate allocation, supersession, rounding or thresholds.
- [ ] Rows render owner `ready`, Remaining CH, nullable CGPA, `approachingGraduation` and all typed blockers. Null CGPA is unavailable, distinct from zero. A missing curriculum result is explicitly unavailable and included in population totals.
- [ ] Ready/not-ready/unavailable summary groups cover the selected population. Historical Terminated rows retain clearly labelled advisory academic facts without being presented as currently eligible for graduation. No Graduated lifecycle or formal approval action is introduced.
- [ ] Blocker counts are labelled overlapping; multiple blockers remain visible and are never summed as a distinct Student count. Every blocker chart/table link lands on the exactly matching filtered Student List.
- [ ] Warning alone does not exclude a Student whose curriculum is satisfied and CGPA qualifies. Actionable Termination Pending blocks readiness; fresh owner reads reflect dismissal or trigger clearance without report-triggered reconciliation.
- [ ] Ready/unavailable, typed blocker, Remaining-CH and nullable-CGPA range filters compose with common filters before sorting/pagination. Provide a clearly labelled 1–5 CH remaining proximity filter.
- [ ] Proximity is not labelled Approaching Graduation. No hypothetical schedule is supplied: preserve the owner's no-proposal result, which additionally requires a completing proposed for-credit schedule. Registration exception semantics remain unchanged.
- [ ] Summary, filtered List, drill-through and full XLSX export share identical selection semantics. Stable ordering, truthful totals, export authorization/audit, EN/AR headers, RTL workbook direction and string identifiers follow #01. No private identifiers or evidence are added.
- [ ] API documentation and committed generated hooks/types are synchronized. The owner feature provides the Report through app Analytics composition without feature-to-feature imports.
- [ ] Accessible chart/table equivalents, advisory labels, URL filters, loading/empty/error/unavailable states, EN/AR switching and browser print work in the four theme/direction combinations with design tokens.
- [ ] Test-first Pest HTTP tests cover owner parity, bound versus changed current curricula, effective credit decisions, Grade corrections/supersession, Warning-ready, pending/cleared/dismissed termination, null/zero CGPA, overlapping blockers, missing curriculum, historical lifecycle and no-proposal versus proximity semantics.
- [ ] Tests assert scoped totals, exact drill-through membership, derived filter/sort-before-page behaviour, full-file export and repeated GETs without academic writes or notifications. Focused Vitest covers blockers, unavailable presentation, filter URL round trips and chart/table agreement. Run relevant tests/package checks and record omissions.

## Comments

Academic readiness remains advisory. The approved specification preserves owner definitions rather than inventing a population-level approximation of Approaching Graduation.
