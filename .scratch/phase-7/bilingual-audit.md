# Phase 7 bilingual presentation audit

This is the current consolidated coverage/disposition record. The [tracker](tracker.md)
retains the chronological RED/GREEN and gate evidence. Browser checks use real
loaded screens at 390px and 1280px, with serious/critical axe assertions; source
and interaction checks are stated separately. Native inspection is not human
translation, demo or paper acceptance.

## Coverage

| Required surface | Source / interaction evidence | Native four-state evidence |
| --- | --- | --- |
| Login/register and populated language switching | RequireAuth, RegisterPage, LanguageSwitcher and localeControls regressions | `auth-bilingual-audit.spec.ts`: 4 passed |
| Shell, nav, Dashboard and typed notification bell | SideNav, DashboardPage and NotificationsBell interactions; labels use owner typed data | All 36 core audit checks passed uninterrupted within the full frontend gate |
| Catalog tree/lists, curriculum, versions, detail dialogs | Owner Catalog/Curriculum regressions, immutable history and generated reference tests | Core Catalog 4 and detail/dialog 4 passed, 390/1280px |
| Applicant personal info/email/documents/program/review/status | Existing wizard interactions and Admissions headline; paired dictionaries, identifier/number inputs and owner validation | Applicant detail matrix: four states passed; expanded Admissions journey passed (4.6m), including populated wizard and owner decision states |
| Screening/Dean detail, scheduling/slots/interviewer | ApplicationReviewPage historical read-only regression; DeanDecisionPage and SlotFormDialog paired runtime validation | Staff/Dean core groups and detail/Interviewer matrix passed all four; expanded owner Admissions journey passed |
| Registration/advising/on-behalf, schedules | RegistrationPage, RegistrationReviewPage, ComposeForStudentPage, SchedulePage interactions; LifecycleReadOnly | Core Student/Advisor groups and Advisor detail matrix passed all four; expanded owner Enrollment journey passed (4.2m) |
| Offerings/attendance | Existing RegisterGrid interaction and multi-role Enrollment journey; percentages remain owner-derived | Instructor attendance detail matrix passed all four |
| Capacity/Exemptions/Transfers/Waivers | Owner review/form interactions, paired reviewer validation, Arabic numeric/code isolation and B05-01 failure path | Head/Council core and expanded Student/Advisor/Head credit queues passed all four; Exemptions prior art passed all four; populated capacity denial dialog passed four native states with typed read fixture after real authorized GET |
| Grade sheets, corrections/Incompletes, Student Grades | Owner grading interactions; unchanged supersession/math; correction and unavailable states | Existing `grading.spec.ts` four-state populated editors/invalid marks/Incomplete fields/resolution/central entry/My Grades passed all four editor/Incomplete/central-entry/My Grades states; grading headline passed after saved-locale harness fix |
| Credit-Hour Summary/Standing/Termination review | Owner academic and lifecycle interactions; paired status loading and B05-02 isolated identifiers | Core Student/Advisor/Head/Council groups passed all four; three new Reports separately passed all four and paper inspection |
| Records requests/queues/fee/payment/correction/cancellation/refund/preview/collection/history | Existing RequestActions, FeeForm, RequestPage and issuance interactions; backend service HTTP tests | Core Student/staff Records passed all four; full service journey passed; expanded populated action-state four-state audit passed (Records 8.9m) |
| Current/official Transcript and public valid/revoked/invalid | TranscriptDocument/IssuedDocument, safe revoked payload and immutable snapshot service tests | Full Records journey plus invalid verifier: 6 passed; all four read/print states, actual A4 pages inspected |

The core matrix passed all 36 checks uninterrupted. Workflow-detail checks passed
24/24, public auth four, Catalog details four, correction dialogs four, and the
populated capacity denial dialog four.
Expanded Admissions passed 4.6m; Enrollment passed 4.2m; Records passed 8.9m.
The five grading-headline/Readiness checks passed 2.9m after the saved-locale
setup race was fixed. The complete frontend gate is rerunning from a fresh seed;
these focused/grouped results do not claim its terminal success.

The seeded capacity queue is empty. Its core scan covers the empty queue. A
separate four-state native audit checks a populated denial dialog using the
existing typed owner interaction fixture, injected only for the read after the
real Department Head GET returned 200. It covers layout, keyboard Cancel, required
reason, and no decision write; it does not claim backend selection or authority.
Those remain covered by the owning Pest HTTP tests. The Card heading is now a
semantic h2 with the same visual typography.

## Fixed defects

| ID | Surface | Demonstrated evidence | Fix and verification |
| --- | --- | --- | --- |
| B05-01 | Waiver evidence download failure | EN/AR dictionary comparison found `enrollment.waivers.downloadError` absent in Arabic; the Arabic message was under the unrelated manual Exemption namespace. Existing page regression extended to Arabic failed, displaying English fallback. | Correct Arabic Waiver namespace; actual EN/AR failure-path regression passed. |
| B04-01 | Authenticated route loading | Arabic interaction regression found English-only `Checking your session` accessible spinner label. | Paired session-loading label; 8 guard tests passed. |
| B04-02 | Built-in selection controls | Themed Autocomplete interaction passed English and failed Arabic clear/open/close labels before change. Existing Admissions selector instances have no local control-label override. | MUI locale defaults preserve local overrides and selected values; 16 focused control/theme/guard checks passed. |
| B04-03 | Pipeline population | Arabic Report rendered `{{count}} طلبات` regardless of count, which is unsuitable for the generated population (e.g. 69). | Count-neutral Arabic Pipeline population label; paired Report regressions and four-state native Pipeline passed. |
| B04-04 | Narrow Catalog collections | Read-only browser audit reproduced document overflow at 390px on Faculties (EN) and Departments (AR). | Allow main flex content to shrink, retaining local table scrolling; four-state narrow/wide Catalog matrix passed. |
| B05-02 | Termination reviewer mixed-script identifiers | Source inspection found the entire translated Student/fingerprint sentence forced LTR. Arabic regression confirms no isolated identifier nodes. | Paired rich labels with isolated LTR identifiers and inherited Arabic sentence direction; three actual reviewer rendering tests passed. |
| B04-05 | Public login/registration language choice | Native public-auth matrix timed out waiting for the absent language switcher; these routes only rendered the forms. | Public auth header with local anonymous language choice and auth-only persistence; 8 interactions and four-state native public forms passed. |
| B03-01 | New Report feedback | Generated-hook pending/error scenarios for Readiness/Termination showed Academic Standing messages. Case List loading/error/empty messages referred to Students instead of Cases. | Paired owner-specific Report and Case feedback; 12 Report and 17 Case feedback/List regressions passed. |
| B04-06 | Dark Screening Queue aging text | Native Admissions staff dark EN audit measured warning text at 2.89:1 on Dashboard paper. Conditional static `theme.palette.mode` stayed light while CSS variables switched dark. | CSS-variable warning text and dark applyStyles; 14 focused checks and four-state staff native contrast checks passed. |
| B04-07 | Historical Application screening detail | Read-only source audit and focused accepted/Dean-review fixtures found active approve/return/reject buttons, despite the existing backend 409 transition guard. Two expected RED failures captured this. | Screening actions only for under_sa_review, paired historical read-only notice otherwise; three interaction tests passed, backend transitions unchanged. |
| B05-03 / B06-01 | Academic and Records loading indicators | Native Student academic audit caught serious aria-prohibited-attr on named Skeleton spans. The same invalid naming pattern appears in Records requests/history and unnamed Stack loading groups across credit-decision/review queues. Academic EN/AR loading-role regressions failed twice; Records regressions failed four times before source changes. | Named loading indicators use role=status; 16 affected rendering tests and 54 credit-queue checks passed; loaded native matrices passed. |
| B01-02 | Named multi-reference criteria | Bookmarked 101-reference regression failed at the owning Catalogue 100-row page cap. Separate pending/error/missing tests captured five RED failures while two missing-reference outcomes already passed. | Generated exact reference acquisition in batches of 100, with per-batch pending/error/missing feedback; seven tests passed after five RED regressions. |
| B04-08 | Narrow Curriculum add controls | Catalog detail/version audit loaded the Programme and reproduced page overflow at 390px; add-course plus Preparatory-role controls had fixed minimum widths on a non-wrapping row. | Wrap Curriculum controls and constrain widths; 10 focused tests and four-state detail/dialog native checks passed. |
| B04-09 | Course alternate-language name semantics | Native detail regression found a level-six heading generated by subtitle1 typography immediately beneath the Course title. | Alternate Course name renders as paragraph with existing subtitle styling; four-state native details passed. |
| B04-10 | Applicant GPA scale guidance | Native light EN measured 2.57:1 on meaningful helper text inherited from disabled GPA inputs; two EN/AR focused tests failed. | Readable helper retains disabled GPA input until scale selected; paired RED captured, PersonalInfoStep suite passed and Applicant native states passed. |
| B04-11 / B05-04 | Arabic Dashboard/grade workflow counts | Actual i18next rendering returned “one” for 0/2/3/6/11 in legacy Arabic singular base keys because their plural categories were absent. Sixteen actual Admissions, Enrollment and Grading Card interaction regressions failed across plural categories. | Count-neutral Arabic wording retains owner values across plural categories; 16 actual Card RED regressions, then 58 tests across seven files passed. |
| B04-12 | Populated Applicant “None” English-score guidance | Extended real Admissions journey reproduced 2.57:1 on the disabled score helper; paired EN/AR focused regressions failed. | Readable None English-test helper retains disabled/omitted score; paired RED captured, 16 PersonalInfoStep tests and populated native Admissions journey passed. |

| B05-05 | Populated capacity decision Card | Empty seeded queue hid a level-six Card heading beneath the page h1. Three owner fixture tests went RED when requiring a level-two heading. | Render the same h6 styling as h2; three focused tests passed. Four native EN/AR × light/dark populated denial-dialog probes passed with 390/1280px axe, validation, keyboard Cancel, no POST. |

## Language and ownership evidence

- Frontend source audit: 2,104 keys per language, no missing/extra keys and no
  interpolation mismatches after the fixes. Backend: ten paired EN/AR dictionaries
  with matching keys. Key parity supplements actual rendering; it does not prove
  translation quality or every runtime state.
- Runtime Arabic count regressions exercise actual i18next Card rendering at
  0/2/3/6/11. Owner-provided counts and empty-state rules are unchanged.
- Report/List/export and academic values remain owner-derived and scoped. No
  presentation fix changes Standing thresholds, supersession, registration
  exceptions, Case transition authority or graduation readiness arithmetic.
- Records evidence controls and public valid/revoked/invalid states retain their
  disclosure contract. Issued certified content is not rewritten. Actual A4
  current/official/public/private revoked documents were generated and inspected
  in EN/AR, including continuation headers, watermark/seal and unmirrored QR.
- Authentication setup now awaits the saved `/me` locale and persisted language
  changes. Anonymous public choices remain local; no arbitrary delay or locale
  reset bypasses the actual bilingual UI.

## Remaining verification and human acceptance

The complete backend gate passed (1,329 tests / 8,421 assertions plus static and
OpenAPI checks). Final package-gate verification remains pending after review fixes. The single
consolidated Standards/Spec review found four actionable issues, all fixed and
confirmed by native reviewers; details are in [review](review.md). No per-ticket code review was run. Human review of the
[headline demo](verification.md), Arabic wording and browser paper presentation
remains required before Phase 7 is accepted. No push or merge is authorized.
