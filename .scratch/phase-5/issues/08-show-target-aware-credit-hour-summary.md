# 08 — Show the target-aware Credit-Hour Summary

**What to build:** Give Students and authorized staff an authoritative, explainable view of progress through the admission-bound Curriculum without storing a stale progress snapshot or double-counting one Course target.

**Blocked by:** 07 — Revoke decisions and invalidate stale credit requests.

**Status:** complete

- [x] The AcademicStanding module derives progress from Identity, Catalog, Enrollment, and Grading Public DTOs without importing provider internals or copying their authoritative rows.
- [x] Each Curriculum category exposes Baseline Required, Adjusted Required, Attended, Earned, Transferred, Waived, Exempted, Satisfied, and Remaining CH.
- [x] Satisfied CH allocates effective Course-targeted decisions before Earned CH for the same target, then caps the category at Adjusted Required; Remaining CH is floored at zero.
- [x] Raw Earned and Exempted CH both remain visible after a corrected Exemption follows an Attempt, while only one contribution advances target-matched Satisfied progress.
- [x] A different elective may satisfy a category slot without superseding the failed Course's Grade or CGPA effect.
- [x] The Total row equals the category sums and retains the same cap/floor semantics.
- [x] Student, Advisor, and authorized oversight endpoints return owner-composed results; consumers do not reconstruct the arithmetic.
- [x] The Student progress screen and Credit-Hour Summary Dashboard Card clearly distinguish raw facts, Satisfied CH, and Remaining CH using design tokens and non-color status cues.
- [x] Any lists use the established paginated query/export contract and actor-readable filter allowlist.
- [x] Exhaustive unit matrices and HTTP feature tests cover decision types, Revocation, surplus credit, target collisions, elective slots, totals, permissions, OpenAPI/client generation, and EN/AR RTL rendering.
