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

| B01-02 | Named multi-reference criteria | Bookmarked 101-reference regression failed at the owning Catalogue 100-row page cap. Separate pending/error/missing tests captured five RED failures while two missing-reference outcomes already passed. | Batch generated exact lookups at 100 IDs and label each batch's pending/error/missing state truthfully. Seven focused tests pass; three existing Report regressions passed. |
| B04-08 | Narrow Curriculum add controls | Catalog detail/version audit loaded the Programme and reproduced page overflow at 390px; add-course plus Preparatory-role controls had fixed minimum widths on a non-wrapping row. | Wrap that row with flex gap and cap control widths at the available width. Keep selection, eligibility, Required Role and add behavior; focused Curriculum and native four-state detail/dialog checks running. |

| B04-09 | Course alternate-language name semantics | Native detail regression found a level-six heading generated by subtitle1 typography immediately beneath the Course title. | Render the alternate name as a paragraph with unchanged subtitle typography; native EN/AR detail checks now pass. Full four-state matrix running. |

- Advisor/advising/on-behalf/Termination queue, Head review/capacity/Termination,
  Council Transfer/Waiver/Termination and Student/staff Records request/history/
  fee surfaces passed all four core matrix states. Combined final subgroup result:
  20 tests passed in 6.0m. This covers loaded queue/list screens; full detail/service
  state-machine and paper evidence are recorded separately when run.

## Acceptance coverage map (current frontier)

The source/interaction column identifies the actual owning rendering seams, not
proof of every browser state. Final package gates will rerun the owner journeys
serially. A pending entry below is not reported as a pass.

| Required surface | Source / interaction evidence | Native four-state evidence |
| --- | --- | --- |
| Login/register and populated language switching | RequireAuth, RegisterPage, LanguageSwitcher and localeControls regressions | `auth-bilingual-audit.spec.ts`: 4 passed |
| Shell, nav, Dashboard and typed notification bell | SideNav, DashboardPage and NotificationsBell interactions; labels use owner typed data | Nine core audit groups each passed all four states; full combined run pending |
| Catalog tree/lists, curriculum, versions, detail dialogs | Owner Catalog/Curriculum regressions, immutable history and generated reference tests | Core Catalog 4 and detail/dialog 4 passed, 390/1280px |
| Applicant personal info/email/documents/program/review/status | Existing wizard interactions and Admissions headline; paired dictionaries, identifier/number inputs and owner validation | Applicant detail matrix: four states passed; expanded Admissions journey passed (4.6m), including populated wizard and owner decision states |
| Screening/Dean detail, scheduling/slots/interviewer | ApplicationReviewPage historical read-only regression; DeanDecisionPage and SlotFormDialog paired runtime validation | Staff/Dean core groups and detail/Interviewer matrix passed all four; expanded owner Admissions journey passed |
| Registration/advising/on-behalf, schedules | RegistrationPage, RegistrationReviewPage, ComposeForStudentPage, SchedulePage interactions; LifecycleReadOnly | Core Student/Advisor groups and Advisor detail matrix passed all four; expanded owner Enrollment journey passed (4.2m) |
| Offerings/attendance | Existing RegisterGrid interaction and multi-role Enrollment journey; percentages remain owner-derived | Instructor attendance detail matrix passed all four |
| Capacity/Exemptions/Transfers/Waivers | Owner review/form interactions, paired reviewer validation, Arabic numeric/code isolation and B05-01 failure path | Head/Council core passed all four; expanded Student/Advisor/Head credit surfaces await final gate; Exemptions prior art covers all four |
| Grade sheets, corrections/Incompletes, Student Grades | Owner grading interactions; unchanged supersession/math; correction and unavailable states | Existing `grading.spec.ts` four-state populated editors/invalid marks/Incomplete fields/resolution/central entry/My Grades retained for final gate |
| Credit-Hour Summary/Standing/Termination review | Owner academic and lifecycle interactions; paired status loading and B05-02 isolated identifiers | Core Student/Advisor/Head/Council groups passed all four; three new Reports separately passed all four and paper inspection |
| Records requests/queues/fee/payment/correction/cancellation/refund/preview/collection/history | Existing RequestActions, FeeForm, RequestPage and issuance interactions; backend service HTTP tests | Core Student/staff Records passed all four; full service journey passed; expanded populated action-state four-state audit passed (Records 8.9m) |
| Current/official Transcript and public valid/revoked/invalid | TranscriptDocument/IssuedDocument, safe revoked payload and immutable snapshot service tests | Full Records journey plus invalid verifier: 6 passed; all four read/print states, actual A4 pages inspected |

B04-01–09 and B05-01–03/B06-01 are fixed with focused regressions and native
checks recorded in the tracker. B04-03's paired Report checks passed; B04-04–07's
core browser matrices passed; the earlier “running/pending” rows above are
chronological discovery notes, superseded by these executed results.

| ID | Surface | Evidence | Disposition |
| --- | --- | --- | --- |
| B04-10 | Applicant GPA scale guidance | Native light EN measured 2.57:1 on meaningful helper text inherited from disabled GPA inputs; two EN/AR focused tests failed. | Keep the actual GPA inputs disabled until scale selection, but the instructional helper is enabled/readable. All 14 PersonalInfoStep tests passed; Applicant four-state native matrix now passes. |

No certified academic content, domain transition, authority or disclosure contract
was changed by these fixes. Native inspection is not human demo/translation/paper
signoff; Phase 7 acceptance remains pending that review.

| ID | Surface | Evidence | Disposition |
| --- | --- | --- | --- |
| B04-11 / B05-04 | Arabic Dashboard/grade workflow counts | Actual i18next rendering returned “one” for 0/2/3/6/11 in legacy Arabic singular base keys because their plural categories were absent. Sixteen actual Admissions, Enrollment and Grading Card interaction regressions failed across plural categories. | Use count-neutral Arabic labels retaining the actual owner-provided count in base/other values for the affected waiting, aging, load, sheet and Incomplete labels. Keep English messages, keys, owner counts and empty-state rules unchanged. Focused affected Card suite and restarted frontend gate pending. |

Final source dictionary audit: **2,104 leaves in each frontend locale**, no missing
or extra keys; **10 backend EN/AR dictionary pairs** have matching keys. Interpolation
inspection found the singular fallback defect above rather than treating matching
keys as proof of correct runtime counts.

After B04-11/B05-04: repeated frontend source audit reports **2,104 keys per
language, zero missing/extra keys and zero interpolation mismatches**. Counts remain
provided by the owners; the fix affects their Arabic wording only. Focused affected
Card verification passed **58 / 7 files**. Both final gates are running.

| ID | Surface | Evidence | Disposition |
| --- | --- | --- | --- |
| B04-12 | Populated Applicant “None” English-score guidance | Extended real Admissions journey reproduced 2.57:1 on the disabled score helper; paired EN/AR focused regressions failed. | Keep score input disabled/omitted for None and enable its instructional helper, as with GPA scale guidance. Sixteen PersonalInfoStep tests passed; expanded Admissions populated-state journey passed (4.6m). |

### Final verification frontier

The complete workflow-detail matrix passed **24 tests in 7.4m**, covering
Applicant, Screening, Dean, Advisor, Instructor attendance and Interviewer detail
surfaces in all four combinations. The expanded Admissions owner journey passed
**4.6m**, including populated wizard inputs, validation and actual transitions.
Correction-dialog four-state checks and extended Enrollment/Records checks passed
after harness fixes (remaining seven tests: 15.2m). The complete backend gate passed;
the final complete frontend gate and single consolidated review remain pending. Chronological pending entries above do not override these later results.
