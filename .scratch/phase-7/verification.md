# Phase 7 verification and human demo

Branch: `phase-7-academic-reports-bilingual`.
Fixed consolidated-review baseline: `b3a3ae7b71538c36230985270de2b73bc2f92088`.
Detailed chronological evidence and prerequisite dispositions:
[tracker](tracker.md). Presentation coverage/defects: [audit](bilingual-audit.md).

## Reproduction

Start PostgreSQL with `docker compose up -d`. From `backend/`, prepare the fictional
fixture with `php artisan migrate:fresh --seed --force`; do not reseed during a
browser suite. Serve Laravel and Vite normally, or let Playwright manage their
configured ports. If another app owns 5173, run the frontend gate with
`PLAYWRIGHT_WEB_PORT=5177 PLAYWRIGHT_API_PORT=18001` and a
`SANCTUM_STATEFUL_DOMAINS` value containing `localhost:5177`. The test config
requires the selected servers to start on those exact ports, so it cannot reuse
a foreign app. All seeded accounts below use password `password`.

1. Sign in as Advisor `r03@ejust.test`. Open Analytics → Academic Standing.
   Search `Phase 7 report`; the isolated owner-derived cohort contains three
   Students. Switch to Arabic and back; the URL and populated criteria persist.
2. Show the exact chart table and use keyboard Enter on **Termination Pending**.
   The Student List contains the same three Students. Download the full filtered
   workbook; verify its rows and string Student identifiers. Follow a Student's
   authorized Grades link, then return to the bookmarked Report.
3. Open Graduation Readiness with the same search. Use **Not academically ready**
   to drill into the same cohort. Verify typed owner blockers, null versus numeric
   CGPA and the export. This is advisory readiness, not formal graduation.
4. Open Termination with the same search. Observe **three Cases / three Students**.
   Drill through **Flagged: awaiting Advisor assessment**, export the selection,
   and follow **Open authorized review workflow**. The oversight List contains no
   private narrative or evidence. The operational queue remains independently
   authorized and scoped; no Report read makes a workflow transition.
5. Compare Student `r02@ejust.test` (no Analytics Report gate), Dean
   `r07@ejust.test` (faculty scope) and Student Affairs `r05@ejust.test` (authorized
   oversight). Report filters only narrow the server-derived population.
6. For each Report, review EN and AR in both themes at narrow/wide widths. Print
   A4 portrait: title, effective named criteria, chart and exact data table remain
   readable; navigation/actions disappear. Compare monochrome presentation too.
7. Inspect the documented existing-surface audit, especially Applicant populated
   forms, Catalog curriculum/history dialogs, academic review queues and Records
   payment/evidence actions. Check Arabic copy naturally with a human reader.
8. Retain the Records service demo: review both official preview languages before
   certification, inspect watermark/seal and unmirrored bearer QR, then revoke.
   Public valid access shows the issued snapshot; revoked access shows reference
   and status only; an invalid token shows no private snapshot.

## Native verification

- All three academic Reports: independent four-state serial Report/List/export/
  keyboard/axe/print journeys passed during implementation.
- Headline: `e2e/phase7-headline.spec.ts` passed, with workbook readback and exact
  membership agreement, EN/AR continuity and authorized detail/review.
- Existing-surface core matrix: all nine groups passed their four states.
- Public auth 4, Catalog details 4 and workflow details 24 passed, including
  390/1280px page fit and populated form continuity.
- Records service/invalid verifier acceptance slice: six tests passed in 5.0m.
  Actual current, official, valid-public and revoked A4 PDFs generated in all four
  states. In the latest expanded fixture, current EN uses one page and current
  AR uses two; public revoked uses one and full issued snapshots use two.
  Native EN/AR paper inspection includes continuation headers, watermark, seal,
  QR placement and dark-theme black text.
- Backend gate: passed completely (1,329 tests / 8,421 assertions plus static
  and generated OpenAPI checks).
- Expanded owner presentation checks passed: Admissions 4.6m and seven remaining
  correction/Enrollment/headline/Records checks in 15.2m.
- Frontend gate: API/static stages and 121 files / 676 unit tests passed in the
  isolated full attempt; 178 serial browser tests passed and one Records print
  locale assertion failed. Its reload-preference wait is fixed; affected browser
  verification and the complete final gate remain pending.
- Consolidated Standards/Spec review: one native run, four actionable findings
  fixed and confirmed resolved. [Review evidence](review.md) includes separate
  axes, requirements, test-first fixes and verification.

## Human acceptance

Native verification is not human acceptance. Phase 7 remains unaccepted until
its headline demo, Arabic wording and browser paper presentation receive the
required human review. No push, merge, deployment or external runner is authorized.
