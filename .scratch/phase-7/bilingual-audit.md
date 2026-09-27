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

| B04-06 | Dark Screening Queue aging text | Native Admissions staff dark EN audit measured warning text at 2.89:1 on Dashboard paper. Conditional static `theme.palette.mode` stayed light while CSS variables switched dark. | Use CSS-variable warning text and existing dark `applyStyles` pattern. Preserve overdue words/counts, links and SLA semantics; real browser contrast matrix rerunning. Replace the prior JSDOM static-palette colour assertion with retained semantic checks; browser axe owns rendered contrast. |

| B04-07 | Historical Application screening detail | Read-only source audit and focused accepted/Dean-review fixtures found active approve/return/reject buttons, despite the existing backend 409 transition guard. Two expected RED failures captured this. | Show the screening panel only in under_sa_review and a paired read-only notice otherwise. Record detail and timeline remain readable; backend transitions/permissions unchanged. Three focused tests pass. |

### Executed matrix additions

- Catalog tree and Faculties/Departments/Programs/Courses: all four language/theme
  combinations at 390px and 1280px passed after B04-04. No remaining raw keys or
  serious/critical axe findings on these loaded list/tree surfaces.
- Public login and registration: all four combinations passed, with populated
  email/password values retained on language choice; B04-05 fixed the missing
  controls. No registration mutation is performed by the read-only audit.
- Student Affairs Dashboard/screening/scheduling/slots: all four combinations
  passed after B04-06; typed notification-focused interactions also pass.
- Dean Dashboard/review queue/decision queue: all four combinations passed.
- Academic and Records matrices, owner workflow journeys, additional detail/dialog
  coverage, invalid public verification, actual Transcript paper inspection and
  final gates remain pending. These outcomes do not imply those checks passed.

| B05-03 / B06-01 | Academic and Records loading indicators | Native Student academic audit caught serious aria-prohibited-attr on named Skeleton spans. The same invalid naming pattern appears in Records requests/history and unnamed Stack loading groups across credit-decision/review queues. Academic EN/AR loading-role regressions failed twice; Records regressions failed four times before source changes. | Give existing named loading indicators role=status, following AutomaticExemptionsPanel prior art. Retain translated messages and workflow behavior. Sixteen focused academic/review/Records tests pass; broader affected credit-queue tests and loaded browser matrix running. |

The matrix now explicitly waits for progressbars, Skeletons and busy Cards to
finish before recording loaded screen results. The transient loading defects above
are fixed and covered separately; waiting for data is not used to hide them.
