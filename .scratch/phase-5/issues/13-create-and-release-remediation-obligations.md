# 13 — Create and release post-Revocation Remediation Obligations

**What to build:** Give a Student whose Preparatory Exemption is revoked after the ordinary checkpoint a distinct, auditable remediation opportunity without inventing a failed Attempt, and end that pressure correctly when replacement evidence proves the requirement inapplicable.

**Blocked by:** 07 — Revoke decisions and invalidate stale credit requests; 12 — Approve full required Offerings through capacity override.

**Status:** complete

- [x] Revoking a Preparatory Exemption after the ordinary completion checkpoint atomically creates one Enrollment-owned Remediation Obligation linked to the Revocation and restored Curriculum Course.
- [x] The obligation is distinct from Must-Retake, does not imply an Attempt, and exposes immutable Public DTOs containing its cause, state, opportunity, checkpoint, and transition evidence.
- [x] When a lawful current or future opportunity is known, the obligation becomes active and participates in the shared Preparatory-first registration priority, timetable, and full-capacity workflow.
- [x] When no qualifying opportunity exists, it enters Awaiting Offering with no termination deadline while still affecting Remaining CH and Graduation Readiness.
- [x] Creation and every real state change notify the Student, current Advisor, and scoped Department Head once in both languages.
- [x] Approval of a replacement Preparatory Exemption atomically closes an active or Awaiting Offering obligation as Released By Exemption, distinct from Course satisfaction.
- [x] The same transaction system-rejects every Pending or Awaiting Capacity Override Registration Request containing the released Course, preserves the unchanged request and capacity-review history, and notifies the Student and current reviewers exactly once.
- [x] Existing Enrollments are never dropped, withdrawn, or converted by replacement Exemption approval; ordinary schedule and grading rules continue.
- [x] Replacement approval acquires the Identity-owned Student guard and revalidates active lifecycle before changing the decision, obligation, requests, or notifications.
- [x] Student and reviewer UI distinguishes Must-Retake, Remediation, Awaiting Offering, Course satisfaction, and Released By Exemption in EN/AR and LTR/RTL.
- [x] HTTP feature, idempotency, rollback, authorization, OpenAPI/client, component, and request-preservation tests pass.
