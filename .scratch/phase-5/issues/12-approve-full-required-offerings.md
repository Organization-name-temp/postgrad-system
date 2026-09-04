# 12 — Approve full required Offerings through capacity override

**What to build:** Let a required retake proceed through accountable per-Offering Department Head capacity decisions without exposing ordinary registration authority, reserving seats, or silently changing the Student's batch.

**Blocked by:** 11 — Enforce Must-Retake registration priority.

**Status:** ready-for-agent

- [ ] A full eligible Offering remains available for an active obligation and can be included in the atomic Registration Request.
- [ ] Advisor recommendation moves a request needing an exception from Pending to Awaiting Capacity Override; ordinary requests retain their existing direct Approved/Rejected path.
- [ ] Every full Offering creates one active requirement routed only to the Department Head scoped to that Course's Department.
- [ ] Capacity authority uses Department Head Role plus Course scope, requires a reason, is audited, and exposes neither the ordinary registration queue nor a new Permission.
- [ ] A multi-Department request waits for every active requirement; any denial rejects the whole unchanged batch and unanimous approval performs final whole-batch revalidation before atomically creating Enrollments.
- [ ] When a normal seat returns, idempotent reconciliation retires the active requirement while preserving its decision history and auto-approves only if no exception remains and final revalidation succeeds.
- [ ] When another requested Offering becomes full, reconciliation adds its own scoped requirement without duplicating any existing decision.
- [ ] The workflow never reserves or displaces a seat; a lost capacity race leaves the request awaiting the still-required decision.
- [ ] Student, Advisor, and Department Head UI makes the current action owner, per-Offering decisions, reasons, and unchanged-batch outcome visible in EN/AR and LTR/RTL.
- [ ] HTTP, concurrency, reconciliation, scope, audit, OpenAPI/client, component, and representative browser tests cover approval, denial, capacity return, newly full lines, and final revalidation failure.
