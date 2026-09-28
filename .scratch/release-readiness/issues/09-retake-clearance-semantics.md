# 09 — Reconcile Grade supersession and trigger-clearance wording

**What to build:** The academic record has a precise accepted interpretation of a later same-Course Attempt for CGPA, earned credit, Must-Retake and Termination triggers.

**Blocked by:** None — can start immediately.

**Status:** needs-info

- [ ] Compare BR-53/55/58/61, ADR-0020/0038/0042 and current owner calculators; distinguish unconditional latest-Grade supersession from earning CH, satisfying a Course and clearing a particular trigger.
- [ ] Record an explicit outcome table for a D/F followed by D+, C, C+ and a worse Grade, plus passing a different elective; identify existing tests for each outcome before proposing changes.
- [ ] Resolve whether the “successful later Attempt” wording agrees with the effective D/F trigger and C+ satisfaction thresholds; amend ambiguous prose or create a bounded implementation ticket for an actual discrepancy.
- [ ] Any changed rule remains in its owning module, reaches consumers through Public contracts, and has test-first parity/Case/readiness regression evidence. This ticket does not assume a proven runtime bug.
