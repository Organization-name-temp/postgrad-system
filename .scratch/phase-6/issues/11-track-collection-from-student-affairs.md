# 11 — Track collection from Student Affairs

**What to build:** Let Student Affairs record handover of an issued copy awaiting collection and let the Student see that delivery is complete.

**Blocked by:** 10 — Issue official copies with public QR verification.

**Status:** complete

- [x] Student Affairs sees Ready for Collection requests and records handover time and staff member under existing official-issuance authority, preserving administrator override.
- [x] Collection atomically moves Ready for Collection to Completed and releases the open-request limit. Digital requests cannot use collection actions and already-completed requests cannot create another handover.
- [x] Deliver collection queue/action and owning Student delivery history/status in EN/AR, with attributed transitions, audit entry, and once-only collection notification.
- [x] Service details retain owner/Student Affairs access; Advisor issuance-history access does not disclose request processing or financial history.
- [x] Delivery does not change the immutable issuance content, public token, or validity; do not introduce postal delivery, couriers, or cancellation after issuance.
- [x] Queue/export scope and filters follow the Support contract; retries and concurrent handover submissions preserve a single committed delivery completion.
- [x] HTTP/frontend tests cover authority, wrong states/method, duplicate completion, open-request release, snapshot immutability, Terminated Students, histories, and side effects.
- [x] Generated API artifacts, RTL/LTR presentation, boundaries, and affected gates pass; report any gate not run with its concrete reason.

## Verified completion

Acceptance is verified by the ticket-specific coverage, complete gates and resolved
consolidated review recorded in [the Phase 6 evidence](../verification.md).
