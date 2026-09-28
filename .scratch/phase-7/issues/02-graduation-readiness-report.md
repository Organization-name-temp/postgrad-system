# 02 — Graduation Readiness Report

**What to build:** Authorized staff inspect advisory Graduation Readiness for their permitted Students, identify every blocker, distinguish incomplete evidence from unmet requirements, and drill into/export the population needing a particular intervention.

**Blocked by:** Phase 7 #01 — Academic Standing Report, which establishes academic cohort scope, selection and Analytics integration.

**Status:** implemented — final verification pending

- [x] AcademicStanding supplies a readiness summary, pageable Student List and audited export using #01's staff gate, live scope union and common academic/lifecycle/search filters. Direct API access and export enforce the same scope as presentation.
- [x] Reuse GraduationProgress and the authoritative Credit-Hour/Grade owners with bounded batch reads and single-Student parity. Requirements use admission-bound Curriculum Versions; the frontend and Report do not duplicate allocation, supersession, rounding or thresholds.
- [x] Rows render owner `ready`, Remaining CH, nullable CGPA, `approachingGraduation` and all typed blockers. Null CGPA is unavailable, distinct from zero. A missing curriculum result is explicitly unavailable and included in population totals.
- [x] Ready/not-ready/unavailable summary groups cover the selected population. Historical Terminated rows retain clearly labelled advisory academic facts without being presented as currently eligible for graduation. No Graduated lifecycle or formal approval action is introduced.
- [x] Blocker counts are labelled overlapping; multiple blockers remain visible and are never summed as a distinct Student count. Every blocker chart/table link lands on the exactly matching filtered Student List.
- [x] Warning alone does not exclude a Student whose curriculum is satisfied and CGPA qualifies. Actionable Termination Pending blocks readiness; fresh owner reads reflect dismissal or trigger clearance without report-triggered reconciliation.
- [x] Ready/unavailable, typed blocker, Remaining-CH and nullable-CGPA range filters compose with common filters before sorting/pagination. Provide a clearly labelled 1–5 CH remaining proximity filter.
- [x] Proximity is not labelled Approaching Graduation. No hypothetical schedule is supplied: preserve the owner's no-proposal result, which additionally requires a completing proposed for-credit schedule. Registration exception semantics remain unchanged.
- [x] Summary, filtered List, drill-through and full XLSX export share identical selection semantics. Stable ordering, truthful totals, export authorization/audit, EN/AR headers, RTL workbook direction and string identifiers follow #01. No private identifiers or evidence are added.
- [x] API documentation and committed generated hooks/types are synchronized. The owner feature provides the Report through app Analytics composition without feature-to-feature imports.
- [x] Accessible chart/table equivalents, advisory labels, URL filters, loading/empty/error/unavailable states, EN/AR switching and browser print work in the four theme/direction combinations with design tokens.
- [x] Test-first Pest HTTP tests cover owner parity, bound versus changed current curricula, effective credit decisions, Grade corrections/supersession, Warning-ready, pending/cleared/dismissed termination, null/zero CGPA, overlapping blockers, missing curriculum, historical lifecycle and no-proposal versus proximity semantics.
- [x] Tests assert scoped totals, exact drill-through membership, derived filter/sort-before-page behaviour, full-file export and repeated GETs without academic writes or notifications. Focused Vitest covers blockers, unavailable presentation, filter URL round trips and chart/table agreement. Run relevant tests/package checks and record omissions.

## Comments

Academic readiness remains advisory. The approved specification preserves owner definitions rather than inventing a population-level approximation of Approaching Graduation.

## Executable disposition

Implemented on `phase-7-academic-reports-bilingual` against fixed baseline
`b3a3ae7b71538c36230985270de2b73bc2f92088`. Focused red/green evidence,
owner/API/UI verification and prerequisite dispositions are recorded in
[the Phase 7 tracker](../tracker.md). The single consolidated
[Standards/Spec review](../review.md) has all four actionable findings resolved.
The final backend gate passed (1,334 tests / 8,458 assertions); frontend static
checks and 684 unit tests passed. The complete serial browser stage is still
running. This disposition does not claim phase acceptance or human review.
