# Phase 7 bilingual presentation audit

This record distinguishes source/runtime checks from browser coverage. No surface
is considered fully covered until its specified EN/AR × light/dark and responsive
states have executable or inspected evidence. Certified Transcript snapshots must
remain unchanged.

## Concrete defects

| ID | Surface | Evidence | Disposition |
| --- | --- | --- | --- |
| B05-01 | Waiver evidence download failure | EN/AR dictionary comparison found `enrollment.waivers.downloadError` absent in Arabic; the Arabic message was under the unrelated manual Exemption namespace. Existing page regression extended to Arabic failed, displaying English fallback. | Move the existing Arabic message to the Waiver namespace. Preserve the request/download behaviour; rerun the actual error rendering path in both languages. |

## Coverage so far

- Compared every frontend dictionary leaf: 1,866 entries in each locale before the
  fix, with one misplaced Arabic entry. This proves key-shape coverage only, not
  correctness of every translation or layout.
- Shared charts: serial `/style` Playwright/axe verification passes in all four
  combinations, including bar/line/pie and chart-as-table with exact values.
- Waiver evidence download error: focused page interaction exercises the failed
  authenticated download and rendered error in EN and AR.
- Backend message source audit: every English PHP dictionary has an Arabic
  counterpart with matching leaf keys. This does not prove message runtime paths
  or translation quality.

## Remaining coverage

- #04: authentication, shell/navigation, notifications, Dashboard, Catalog
  management/tree/curriculum/versions and Applicant/staff Admissions workflows.
- #05: registration/advising/on-behalf, offerings/schedules/attendance, capacity
  overrides, credit decisions, grading/corrections/Incompletes, Student Grades,
  Credit-Hour Summary, Standing and Termination review.
- #06: current/official Transcripts, service workflows, evidence, issuance/history/
  revocation and public valid/revoked/invalid verification, including EN/AR print.
- New Reports: role visibility, keyboard drill-through, criteria preservation,
  unavailable/error states, four presentation combinations and print layout.

All remaining items require the final acceptance pass. No broad audit claim is made
from dictionary parity alone.

## Confirmed shell/Admissions corrections

| ID | Surface | Evidence | Disposition |
| --- | --- | --- | --- |
| B04-01 | Authenticated route loading | Arabic interaction regression found English-only `Checking your session` accessible spinner label. | Paired auth dictionary key and actual guard translation; 8 guard tests passed, including Arabic loading. |
| B04-02 | Built-in selection controls | Themed Autocomplete interaction passed English and failed Arabic clear/open/close labels before change. Existing Admissions selector instances have no local control-label override. | Apply MUI locale defaults with existing locale-derived theme; component overrides remain authoritative. EN/AR control tests preserve selected value. Theme/guard/control focused checks: 16 tests passed. |
| B04-03 | Pipeline population | Arabic Report rendered `{{count}} طلبات` regardless of count, which is unsuitable for the generated population (e.g. 69). | Use `عدد الطلبات: {{count}}`, a count-neutral label; actual Arabic Report regression captured RED. Final focused verification pending. |

These fixes do not establish complete existing-surface audit coverage. Four-state
responsive/browser checks and runtime message paths remain on the acceptance frontier.

| B04-04 | Narrow Catalog collections | Read-only browser audit reproduced document overflow at 390px on Faculties (EN) and Departments (AR). | Shell main flex child must shrink below intrinsic table width; add `minWidth: 0`, retain local TableContainer scrolling. Four-state Catalog verification running; initial EN/AR light checks now pass. |
| B05-02 | Termination reviewer mixed-script identifiers | Source inspection found the entire translated Student/fingerprint sentence forced LTR. Arabic regression confirms no isolated identifier nodes. | Pending paired rich-text labels with LTR `bdi` values and inherited Arabic sentence direction; preserve evidence/review authority. |

| B04-05 | Public login/registration language choice | Native public-auth matrix timed out waiting for the absent language switcher; these routes only rendered the forms. | App composes a public auth header using existing language/theme controls. Anonymous choice stays local; explicit test caught unnecessary persistence before conditional authentication guard. Browser matrix rerunning. |
| B03-01 | New Report feedback | Generated-hook pending/error scenarios for Readiness/Termination showed Academic Standing messages. Case List loading/error/empty messages referred to Students instead of Cases. | Paired owner messages; Report scenarios passed 12 focused tests, Case feedback/List regressions passed 17. No arithmetic, scope or workflow changes. |
