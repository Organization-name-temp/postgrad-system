# 07 — Request and decide cancellation

**What to build:** Let Students pause a pre-issuance request by asking to cancel, and let Student Affairs resume processing or stop it and create any verified-payment refund obligation.

**Blocked by:** 06 — Submit, correct, and verify payment evidence.

**Status:** ready-for-agent

- [ ] The owning Student may request cancellation from a non-terminal pre-issuance processing stage, including Ready for Issuance after verified payment; record the prior stage and enter Cancellation Pending.
- [ ] Pending cancellation pauses processing and payment verification. Repeated submission cannot create parallel cancellation reviews; access follows the service-record scopes and allows Terminated Students.
- [ ] Student Affairs approves or rejects with a mandatory reason. Rejection restores the exact prior stage; approval permanently stops issuance.
- [ ] Approved cancellation without verified payment becomes Cancelled and releases the open-request limit. Approved cancellation with verified payment becomes Refund Pending and creates exactly one full-amount Records refund obligation.
- [ ] Refund Pending remains open and blocks another Student request until ticket 08 settles it. Cancellation never invents verified payment from an uploaded receipt.
- [ ] Serialize cancellation submission/decision and payment verification on the request with post-lock revalidation; controlled transaction tests prove stale competing transitions cannot both commit.
- [ ] Define refusal of cancellation after issuance for the future issuance boundary; do not add post-issuance refunds or confuse cancellation with issuance revocation.
- [ ] Deliver Student cancellation controls and staff decision queues, visible pause/reason/prior-stage history, typed notifications, and audit entries in EN/AR.
- [ ] Queue/export access and filters respect service-record visibility; request timeline remains attributed, timestamped, and append-only.
- [ ] HTTP/frontend tests cover each cancellable stage, rejection restoration, paid/unpaid approval, duplicate decisions, concurrent verification, one-open-request behavior, authority, termination, and once-only side effects.
- [ ] Generated contract/client and affected gates pass; report any gate not run with its concrete reason.
