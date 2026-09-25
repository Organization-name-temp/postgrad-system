# 07 — Revoke decisions and invalidate stale credit requests

**What to build:** Preserve academic corrections as attributed Revocations and prevent stale Transfer or Waiver reviews from becoming effective after the Student receives a failed Grade in the target Course.

**Blocked by:** 05 — Deliver the Transfer workflow; 06 — Deliver the Waiver workflow.

**Status:** complete

- [x] The same final authority that approved an Exemption, Transfer, or Waiver may append a Revocation only with a required reason and valid scope.
- [x] Revocation makes the decision ineffective without editing or deleting the original decision, request, evidence, reviews, or transition history.
- [x] A replacement decision requires a new request through the complete workflow; no revoked decision is silently reactivated.
- [x] Revoking a Transfer immediately frees its target Curriculum Course hours from the lifetime 9-CH cap.
- [x] A new Transfer or Waiver request is rejected when an effective failed Attempt already exists for its target Course; Exemption remains the deliberate exception.
- [x] A newly finalized initial or correction Grade that creates the failure idempotently system-rejects each in-review Transfer/Waiver request for that Course and notifies the Student and current reviewers.
- [x] Concurrent final credit approval and Grade finalization cannot commit an ineligible effective decision or partial workflow state.
- [x] Student and authority screens distinguish effective, revoked, rejected, and replacement records with attributed reasons in EN/AR and LTR/RTL.
- [x] API feature, event retry, concurrency, authorization, audit, OpenAPI/client, and component tests pass.
