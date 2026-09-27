# Phase 6 acceptance evidence

Review base: `2e9c16db11213f97a78261750a8d1d76337148d5`.
Branch: `phase-6/records-transcript`. No worktree, branch switch, merge, or push.

## Verification passes

1. Behavior was implemented test-first at HTTP/Public seams. Narrow Records tests passed
   72 cases / 662 assertions before the full gates; Identity acceptance/recovery tests
   passed 29 cases / 150 assertions. Focused Records frontend coverage passed 11 tests.
   Red/green regressions include spreadsheet string identifiers, omitted empty filters,
   admission-term/reason localization, Cairo-session timestamp preservation, and dark-theme
   browser printing. Checks during implementation were not code reviews.
2. `COMPOSER_PROCESS_TIMEOUT=0 composer gate` passed: 1,214 tests / 6,041 assertions,
   Pint, PHPStan, deptrac, and OpenAPI drift. The first default-timeout invocation stopped
   at Composer's 300-second limit without a failing test; the complete rerun disabled only
   that process timeout. `npm run gate` passed: generated-client drift, ESLint,
   Prettier, TypeScript, all 86 Vitest files / 528 tests, and all 66 serial Playwright
   tests (17.7 minutes). The full Transcript journey passed in 3.5 minutes.
3. One consolidated Standards/Spec review of the complete Phase 6 diff found four
   Standards and two Spec gaps. Narrow regressions passed; the backend rerun passed
   1,216 tests / 6,066 assertions and all static/drift checks. The frontend rerun passed 88 Vitest files / 534 tests and all 66 serial
   Playwright tests (18.4m), including the complete A4 watermark/print matrix. See [review.md](review.md).

## Acceptance coverage by ticket

| Ticket | Executable evidence |
| --- | --- |
| 01 Identity | Admissions `FinalDecisionTest`, Identity `StudentIdentificationRecoveryTest`, owner `/me` and Dashboard tests: both identifier types, atomic acceptance, accepted matching evidence, idempotence, unresolved evidence, frozen admission identity. |
| 02 Current Transcript | `CurrentTranscriptTest`, `TranscriptDocument.test.tsx`: scopes/live assignment/role unions, finalized-only results, every status/Attempt, recorded CH/classification, authoritative effective GPA, null dash, bilingual header and current rendering. |
| 03 Decisions/CH | `CurrentTranscriptTest`, document tests: effective Exemption/Transfer/Waiver details, revoked exclusion, no evidence leakage, full owner summary, Exemption-plus-earned target accounting. |
| 04 Setup | `FeeConfigurationTest`, `FeeForm.test.tsx`: exact positive EGP, required bilingual instructions, staff/admin authority, no preapproval instruction disclosure, configuration audit. |
| 05 Requests | `TranscriptRequestTest`, `TranscriptExportTest`, `RequestActions.test.tsx`, serial browser journey: snapshots, approval/rejection, open limit, private queues/exports, attributed transitions/audit/notifications, Terminated services. |
| 06 Payment | `TranscriptPaymentTest`, action tests, browser journey: retained receipt versions/decisions, correction, exact captured fee, private downloads, repeat/wrong-state refusal. |
| 07 Cancellation | `TranscriptCancellationTest`, concurrency/action tests: every cancellable stage, pause, exact restoration, verified/unverified decision paths, one obligation, ordering with payment/issuance. |
| 08 Refund | `TranscriptRefundTest`, concurrency/action tests, browser journey: full exact documented settlement, validation rollback, private evidence/history, once-only completion, open-limit release. |
| 09 Preview | `TranscriptIssuanceTest`, concurrency tests, `RequestPage.test.tsx`: full bilingual preview, unresolved readiness, complete-content invalidation, refresh, no premature issuance, actual owner-writer coherence. |
| 10 Issue/QR | `TranscriptIssuanceTest`, concurrency tests, issuance/history/public UI tests, browser journey: one immutable copy, frozen identity/academic content, scoped history, full approved public disclosure, genuine per-copy QR route, digital completion. |
| 11 Collection | Issuance/concurrency/action tests, browser journey: ReadyForCollection to Completed, attributed once-only handover, unchanged content/validity, open-limit release. |
| 12 Revocation | Issuance/Public UI tests, browser journey: reason/actor/time retained internally, exact reference/status-only public response, no stale academic rendering or automatic refund/reopening, preserved authenticated snapshot. |
| 13 Integration | Full gates, serial browser journey and `records-print.spec.ts`; real independent PostgreSQL sessions cover all required race orderings. |

The concurrency suite contains 11 controlled cases. It observes PostgreSQL lock waiting
rather than using timing sleeps: contributing Identity/Catalog/Grading writers, two open
submissions, duplicate certification, both certification/cancellation orderings, both
payment/cancellation orderings, duplicate full settlement, and duplicate handover.
ADR-0050 records the shared owner Public read-guard strategy, including insert/delete
protection and whole-transaction retry.

Browser acceptance uses explicitly fictional learning fee/instructions and sequential
requests settled through real digital delivery, full refund, and collection. The EN/AR ×
light/dark matrix checks RTL/LTR, serious/critical axe violations, print chrome removal,
table width and black printed text for current, official, valid public, revoked public,
and revoked authenticated views. Print screenshots are attached to the Playwright report.
No deferred server PDF, cryptographic signing, online gateway, postal, or email/SMS feature
is needed or claimed.

## Consolidated review

The one consolidated review is recorded in [review.md](review.md). All six findings
are resolved, with narrow regressions and complete affected gate reruns. All 13 ticket
acceptance checklists and verified phase status are complete.

## Re-verification failures and fixes

The first frontend rerun stopped with four unit-test failures: one 20-second interaction
timeout and three still-loading mocked API views. All four files passed unchanged in an
isolated one-worker run (20 tests). The installed Vitest default selected seven workers
on this shared host; limiting the suite to two workers preserved all assertions/timeouts
and passed all 88 files / 534 tests. This limits runner contention without changing app
behavior or weakening acceptance.

A stronger portrait-paper print check then reproduced an over-wide CH Summary at A4
width. Scoped print-only fixed table layout, smaller token-based cell text/padding, and
wrapping retain every value and column; the real browser regression passed (15.3 seconds).
The complete print matrix checks the same A4 width; its complete frontend gate rerun passed.
The focused browser also waits for the authenticated heading before reading record data,
rather than mistaking a cold session loader for a missing document.

The next serial browser rerun exposed three five-second assertion deadlines while
session/data loading remained visible (Dashboard, Department Heads, enrollment).
Functional polling now permits 20 seconds; Dashboard setup waits for authenticated
content and its locale save before context closure. All content/access/a11y assertions
remain. The three originally failing tests passed together (4.4 minutes), including
the full four-user enrollment loop. The subsequent full gate rerun used a fresh seed.

The subsequent full frontend gate passed all 534 unit tests and 65 browser tests;
the Transcript journey exposed a serious axe contrast finding on faint HTML watermark
text. A decorative bilingual SVG image with an empty alternative retains the graphic
alongside the readable official heading. The focused image-contract test failed before
the fix and passed afterward; TypeScript and the complete Transcript journey passed
(3.5 minutes), including axe, image loading, A4 print, public revocation, refund and
collection. Arabic official/revoked A4 screenshot artifacts were inspected. No axe
rule or content assertion was disabled. The complete gate rerun passed afterward.

## Final reproducible verification

- Backend: `COMPOSER_PROCESS_TIMEOUT=0 composer gate` — Pint, PHPStan, deptrac,
  1,216 tests / 6,066 assertions, and OpenAPI regeneration/drift passed.
- Frontend: `npm run gate` — client regeneration/drift, lint, format, typecheck,
  88 Vitest files / 534 tests, and 66 serial Playwright tests passed.
- Real PostgreSQL concurrency: 11 controlled cases passed within the backend gate.
- Browser: current, official, valid public, revoked public and revoked authenticated
  views passed EN/AR × light/dark, RTL/LTR, serious/critical axe, A4 width, print contrast,
  chrome removal and SVG watermark checks. Playwright report contains per-combination screenshots.
- One consolidated review: four Standards and two Spec findings, all resolved.
- Required checks unrun: none. External blockers: none.

The complete demo is `frontend/e2e/records.spec.ts`: after migrating/seeding the localhost
fixture, run `npm run e2e -- e2e/records.spec.ts` from `frontend/`. It uses seeded learning
accounts and clearly fictional fee/instructions; requests close via real workflow actions.

## Implementation commits

```text
ce1ef25 feat(identity): preserve and recover admission identification type
9a4cd83 feat(records): add bilingual transcripts and service request foundation
cbbcf28 feat(records): scope searchable request queues and audited exports
a1743de feat(records): retain private payment evidence and verify exact fees
04ba239 feat(records): serialize cancellation and settle full private refunds
8325cfa feat(records): certify coherent immutable transcripts and manage copy validity
5645c78 feat(records): deliver bilingual transcript service and public QR screens
4ca9024 test(records): verify workflow races, exports and bilingual printing
015f418 test(records): cover revoked bilingual access and print matrix
e8d06ec docs(records): record complete acceptance gate evidence
d97cb96 fix(records): resolve consolidated contract and bilingual presentation review
36d3f33 fix(records): fit portrait printing and bound test runner contention
2593537 test(e2e): await authenticated data and saved language preferences
7e53ded fix(records): render the bilingual watermark as a decorative graphic
```

The following documentation commit records verified ticket and phase completion.
