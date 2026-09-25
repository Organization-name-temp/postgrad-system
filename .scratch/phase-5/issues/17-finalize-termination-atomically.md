# 17 — Finalize termination atomically through Council

**What to build:** Let the Academic Affairs Council make the final termination decision while atomically changing Student lifecycle and closing every open academic request without rewriting historical study or allowing a corrected Exemption to race stale evidence.

**Blocked by:** 07 — Revoke decisions and invalidate stale credit requests; 16 — Review Termination Cases through Advisor and Department Head.

**Status:** complete

- [x] Only Council may terminate or dismiss a Case that has completed Advisor and Department Head stages; Council cannot act on an earlier stage.
- [x] Identity owns an explicit Active/Terminated Student Lifecycle Status and a transaction-scoped per-Student guard exposed through a narrow Public contract.
- [x] Council finalization acquires the Identity Student guard before any module-owned lock and revalidates lifecycle, Case state, and current authoritative trigger evidence.
- [x] Council dismissal records its reason and exact fingerprint, closes the Case, removes the Termination Pending readiness block, and leaves lifecycle Active.
- [x] Final termination atomically records the Council decision and sets lifecycle to Terminated without removing the Student Role.
- [x] The same transaction invokes Enrollment's idempotent closeout command to system-reject every non-terminal Registration Request and Credit Decision Request with the Termination Case as cause.
- [x] Closeout includes Draft, Returned, Pending, and Awaiting Capacity Override states, preserves evidence, reviews, transitions, and capacity decisions, and notifies the Student and current reviewers exactly once.
- [x] Existing Enrollments and effective Exemption, Transfer, and Waiver decisions remain unchanged after termination.
- [x] A Terminated Student retains authorized historical reads but every new academic mutation is rejected consistently by backend lifecycle gates and reflected in frontend action availability.
- [x] Replacement Exemption approval and final termination use the same guard/order: Exemption-first clears the trigger and blocks stale termination; termination-first blocks all Exemption, obligation, request, and notification changes.
- [x] Failures in lifecycle update, request closeout, notification persistence, or final Case transition roll back the entire finalization rather than leaving partial termination.
- [x] Council, Student, and oversight UI shows final outcome, attributed reason, preserved history, and read-only lifecycle state in EN/AR and LTR/RTL.
- [x] PostgreSQL feature and race tests cover Council authorization, dismissal, idempotent retries, every open request state, historical access, mutation rejection, both concurrency commit orders, rollback, OpenAPI/client generation, and the representative end-to-end termination journey.
