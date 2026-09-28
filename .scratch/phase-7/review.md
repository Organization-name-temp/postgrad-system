# Consolidated Phase 7 review

One code-review skill run, using two native Codex read-only reviewers. Root-session
implementation and fixes remain local. No per-ticket code reviews were run.

- Fixed baseline: `b3a3ae7b71538c36230985270de2b73bc2f92088`.
- Initial reviewed tip: `48e5e99b0f9a5075b243dc4c38f5fa13e3d02782`.
- Comparison: `git diff b3a3ae7b71538c36230985270de2b73bc2f92088...48e5e99b0f9a5075b243dc4c38f5fa13e3d02782`.
- Commits: `git log b3a3ae7b71538c36230985270de2b73bc2f92088..48e5e99b0f9a5075b243dc4c38f5fa13e3d02782 --oneline`.
- Fix commit: `c4be343` (reviewer confirmations cover the root-session fixes).
- Scope: all seven Phase 7 tickets and executable Phase 4.6 prerequisites
  #01, #02, #03, #04, #05, #06, #08 and #15. Unrelated Phase 4.6 work stays deferred.
- Sources: AGENTS, CONTEXT, requirements, accepted related ADRs, design system,
  codebase map, both phase specs and applicable tickets. Generated API contracts
  and handwritten changes are assessed separately.
- Review started while the final serial gate attempt finished. Backend full gate
  passed; frontend static/unit stages passed. The known Records reload-language
  harness failure had its fix committed, with focused verification pending.
  Reviewers perform no mutable verification and do not compete with the suite.

## Standards

1. **S1 — P2, nullable ordering parity.** `ApplicationCollection::derivedRows`
   used PHP's direct nullable comparison, which disagrees with PostgreSQL's
   ascending nulls-last / descending nulls-first ordering. ADR-0025/0028 require
   identical stored/derived ordering. A non-narrowing age filter could move rows
   between pages. High confidence; documented contract breach.
2. **S2 — P2, loading presentation.** All six new AcademicStanding Report/List
   pages and Application Pipeline used only a spinner. Design-system §9 requires
   skeletons matching table/card layout. High confidence; documented design breach.
3. **S3 — P2, Interview API documentation.** Booked Interview list/export
   annotations advertised `faculty_id`, but the executable allowlist rejects it.
   High confidence; generated contract mismatch. The approved global SA/admin
   Interview worklist does not require this extra filter.

All twelve smell heuristics were considered; no additional actionable heuristic
finding warranted cosmetic refactoring. Tooling-enforced rules were excluded.

## Spec

1. **P1 — P1, overlapping blocker drill-through.** Graduation Report blocker
   links replaced the original OR blocker criterion. A Student outside the Report
   selection sharing the clicked blocker could appear in the resulting List.
   Phase 7 ticket #02 requires “Every blocker chart/table link lands on the exactly
   matching filtered Student List.” High confidence. Preserve the original OR
   criterion and intersect the clicked blocker for List/export selection.

2. **P2 — P2, worked-fixture disposition.** Closeout wording incorrectly claimed
   all named operational rosters were untouched. The generated layer is isolated,
   but Phase7AcademicReportSeeder adds three advisees/open Cases to r03 and
   departmental review work to r06, reusing MSC-CE and two existing Courses.
   The later approved Phase 7 permission for necessary deterministic academic
   Cases permits this additive fixture; original persona/Student records are not
   rewritten. Evidence must distinguish this addition from global roster parity,
   which the current after-DatabaseSeeder comparisons do not prove. High confidence.

Human acceptance and remote publication verification remain explicit dispositions
rather than missing product behavior.

## Resolutions and affected verification

- **S1 fixed:** explicit PostgreSQL-compatible nullable comparison. Four new
  HTTP ordering cases went RED, then GREEN in both directions. Stable-key ties
  remain intact. Standards reviewer confirmed resolution.
- **P1 fixed:** original OR blocker selection is retained; `blocker_all` intersects
  clicked blockers and preserves prior conjunctive criteria. The HTTP test went
  RED then GREEN, including an outside Student and XLSX membership; two EN/AR
  link tests went RED then GREEN. Spec reviewer confirmed resolution. All four
  affected native Readiness states passed (31.1s, 38.5s, 26.3s, 32.6s), proving
  links and actual API counts with an existing blocker criterion.
- **S2 fixed:** shared named status skeletons replace all seven bare spinners;
  List placeholders reserve table rows, and Readiness reserves two chart/table
  sections. Fourteen loading regressions went RED; the affected UI set passed
  **26 tests in 24.66s**. Final two-section refinement passed 20 loading tests (15.43s);
  Standards reviewer confirmed S2 fully resolved.
- **S3 fixed:** removed only two copied Faculty annotations from booked Interview
  list/export and regenerated OpenAPI/Orval artifacts. Existing approved filters
  and actor scope remain unchanged. Standards reviewer confirmed this scope
  choice and resolution.
- Combined affected owner verification: **33 tests / 262 assertions (7.79s)**.
  Frontend lint, full format and typecheck passed. The affected
  Records reload-language recheck passed: **5 native tests (11.7m), exit 0**
  including all four Readiness states and full Records (9.5m). Final backend gate passed (1,334 tests / 8,458 assertions, exit 0);
  final frontend gate passed: 121 files / 684 unit tests and 179 serial browser
  checks (1.1h), terminal exit 0. Its artifacts are retained; no gate failure is hidden.

Initial counts: Standards **3**, worst **P2**; Spec **1**, worst **P1**. After the
fixture-description follow-up, final counts are Standards **3**, worst **P2**;
Spec **2**, worst **P1**. All five findings are resolved; no remaining actionable findings. Human acceptance remains
separate from native review; no merge, push or deployment is performed.

## CI follow-up within this consolidated review

The additional GitHub verification request exposed two browser label-contrast
failures in run `36360347596` (177/179 passed). Root reproduced the rendered
intermediate colour on the actual MUI gallery, locked the finite-transition wait
with a RED/GREEN native regression and committed the audit correction at
`c1dbe1c`. No production colour, axe threshold, domain behaviour or permission
changed. Both existing native reviewers inspected only this follow-up delta and
reported **no actionable Standards or Spec issue**. This is resolution follow-up
within the single consolidated review, not a second full or per-ticket review.
Affected real Enrollment/Records and the new transition regression passed
together **3/3 (12.4m), terminal exit 0** at `12c7c9a` including `c1dbe1c`.
Remote publication verification remains pending under the no-push instruction. See the tracker for exact diagnostic/regression evidence.

## Worked-fixture description resolution

P2 documentation corrected: the tracker limits unchanged-roster wording to the
generated cohort layer and explicitly records the permitted r03/r06 additions.
Prerequisite #01's disposition and the human-demo verification document make
that distinction too. Original persona/Student records remain preserved, with
expanded work populations; no global baseline roster-parity claim is made.
No runtime change or additional gate is required for this factual correction.

Native Spec reviewer confirmed **P2 resolved** and both Spec findings resolved
within this same consolidated review. Standards S1–S3 were already confirmed.
