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
   Standards and two Spec gaps. Their fixes have narrow regression evidence; complete
   gate reruns remain pending. See [review.md](review.md).

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
are implemented, with narrow regression checks. Ticket completion and verified phase
status await the complete gate reruns after those fixes.
