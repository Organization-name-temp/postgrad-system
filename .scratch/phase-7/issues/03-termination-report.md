# 03 — Termination Report

**What to build:** Authorized staff monitor Termination Cases across review stages and final outcomes, inspect historical replacements without inflating Student counts, and export a scoped summary. Reviewers can follow an authorized link to the existing action workflow.

**Blocked by:** Phase 7 #01 — Academic Standing Report, which establishes academic cohort scope and Analytics integration.

**Status:** implemented — final verification pending

- [x] AcademicStanding owns the Case-based summary, pageable List and export. Apply #01's `standing.monitor` oversight gate, live assignment scope union and administrator override. Staff without operational review authority receive only the minimal oversight summary.
- [x] Default results include non-terminal Cases and final Terminated decisions. An explicit history filter also includes Dismissed, Trigger Cleared and Evidence Changed outcomes, preserving replaced Case history.
- [x] Existing owner state values remain unchanged. Recommended means a Department Head recommendation reaching Awaiting Council; initial flagging and Advisor assessment are separate stages, not Council-ready recommendations.
- [x] Rows show Case reference, Student name/University ID, Program, lifecycle, state and available workflow timestamps. Do not expose private evidence, fingerprints, review narratives, National ID/passport or bearer links through Report payloads or export.
- [x] State summaries distinguish each closed outcome from final termination. Provide separately labelled Case counts and distinct Student counts so Evidence Changed replacements do not inflate a population figure.
- [x] Faculty, Department, Program, degree, lifecycle and bilingual name/University-ID search compose with Case state/history and Case-created date ranges. Define inclusive date boundaries consistently in the documented API; dates describe Case creation rather than academic evidence dates.
- [x] Summary/chart/table selections, drill-through List and complete XLSX export agree. Scope is applied before filters, stable Case identifiers break ties, pagination totals are truthful and export preserves localized labels, RTL direction, string identifiers and actor/criteria/count audit entries.
- [x] Summary links to existing Advisor, Department Head or Council review surfaces appear only when the actor can open that surface and the Case is relevant to its stage. Those routes continue to enforce their own gates; Report access grants no assessment/recommendation/decision authority.
- [x] GETs do not reconcile triggers, start replacement Cases, clear Cases, append history, notify Users or mutate Student lifecycle. Existing reconciliation and review actions remain the only workflow mechanisms.
- [x] Documented APIs regenerate committed OpenAPI/SPA hooks. Owner feature composition respects boundaries. EN/AR status text, filters, chart-as-table, keyboard links, empty/error states and browser printing use shared presentation/design tokens in all theme/direction combinations.
- [x] Test-first Pest HTTP coverage proves role/scope restrictions, multiple-role union, reassignment, direct API/export access, minimal disclosure, all workflow states, recommendation-stage meaning, replacements, Case versus Student counts, date boundaries, deterministic pagination and repeated GETs without workflow changes.
- [x] Verify full XLSX selection beyond one page, localized content and export audits. Focused Vitest checks stage/access-aware links, history filters, count labels, URL persistence and chart/table agreement. Preserve existing demo accounts and run affected checks with recorded results.

## Comments

This is oversight reporting. Existing transition-specific Roles and Permissions continue to govern operational review, including evidence access and final Council authority.

## Executable disposition

Implemented on `phase-7-academic-reports-bilingual` against fixed baseline
`b3a3ae7b71538c36230985270de2b73bc2f92088`. Focused red/green evidence,
owner/API/UI verification and prerequisite dispositions are recorded in
[the Phase 7 tracker](../tracker.md). The single consolidated
[Standards/Spec review](../review.md) has all four actionable findings resolved.
The final backend gate passed (1,334 tests / 8,458 assertions); frontend static
checks and 684 unit tests passed. The complete serial browser stage is still
running. This disposition does not claim phase acceptance or human review.
