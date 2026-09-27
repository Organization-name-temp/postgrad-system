# 08 — Complete full manual refunds

**What to build:** Let Student Affairs record settlement of a cancelled request's full refund and let the Student inspect its evidence and request another copy after settlement.

**Blocked by:** 07 — Request and decide cancellation.

**Status:** complete

- [x] Student Affairs sees outstanding Transcript refund obligations and records one full manual refund for exactly the verified EGP payment, with reference, receipt, staff attribution, and completion time.
- [x] Completion transitions Refund Pending to Cancelled and releases the Student's open-request limit atomically; failed validation leaves the obligation outstanding.
- [x] No partial refunds, deductions, zero-payment obligations, online transfers, or financial ledger expansion. The application records external settlement and does not claim to initiate or prevent outside transfers.
- [x] Complete each obligation once; retries and concurrent completion attempts cannot record another refund or duplicate committed audit/notification effects.
- [x] Refund receipts remain private and earlier payment/cancellation evidence remains intact. Only the owning Student and Student Affairs/admin may read financial history or download receipts; Advisors and public recipients cannot.
- [x] Deliver staff refund queue/detail/completion UI and Student evidence/status view in EN/AR, including settled versus outstanding obligations; use shared private-upload/fetch behavior and generated API types.
- [x] Independently filterable refund queues have Support query/export behavior with identical scope/filters and no inaccessible financial field inference; all completion actions are audited and notify the Student in-app.
- [x] HTTP tests cover exact full amount, missing evidence, authority, private serving, duplicate/concurrent completion, open-request release, histories, Terminated Students, and side effects; meaningful frontend tests cover completion behavior.
- [x] Generated artifacts, RTL/LTR implications, boundaries, and affected gates pass; report any gate not run with its concrete reason.

## Verified completion

Acceptance is verified by the ticket-specific coverage, complete gates and resolved
consolidated review recorded in [the Phase 6 evidence](../verification.md).
