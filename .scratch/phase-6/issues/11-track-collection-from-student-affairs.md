# 11 — Track collection from Student Affairs

**What to build:** Let Student Affairs record handover of an issued copy awaiting collection and let the Student see that delivery is complete.

**Blocked by:** 10 — Issue official copies with public QR verification.

**Status:** ready-for-agent

- [ ] Student Affairs sees Ready for Collection requests and records handover time and staff member under existing official-issuance authority, preserving administrator override.
- [ ] Collection atomically moves Ready for Collection to Completed and releases the open-request limit. Digital requests cannot use collection actions and already-completed requests cannot create another handover.
- [ ] Deliver collection queue/action and owning Student delivery history/status in EN/AR, with attributed transitions, audit entry, and once-only collection notification.
- [ ] Service details retain owner/Student Affairs access; Advisor issuance-history access does not disclose request processing or financial history.
- [ ] Delivery does not change the immutable issuance content, public token, or validity; do not introduce postal delivery, couriers, or cancellation after issuance.
- [ ] Queue/export scope and filters follow the Support contract; retries and concurrent handover submissions preserve a single committed delivery completion.
- [ ] HTTP/frontend tests cover authority, wrong states/method, duplicate completion, open-request release, snapshot immutability, Terminated Students, histories, and side effects.
- [ ] Generated API artifacts, RTL/LTR presentation, boundaries, and affected gates pass; report any gate not run with its concrete reason.
