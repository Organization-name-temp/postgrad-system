# 10 — Calculate Graduation Readiness and underload exceptions

**What to build:** Tell Students whether they are academically ready to graduate and allow only the two approved below-minimum schedules that complete the relevant remaining work.

**Blocked by:** 08 — Show the target-aware Credit-Hour Summary; 09 — Classify and observe Academic Standing.

**Status:** ready-for-agent

- [ ] Graduation Readiness is derived from complete Curriculum satisfaction, CGPA of at least 3.00, and absence of actionable Termination Pending evidence; it never mutates Student lifecycle state.
- [ ] The Public result includes readiness, total Remaining CH, nullable CGPA, Approaching Graduation, and a closed typed blocker set for remaining requirements, unavailable CGPA, below-minimum CGPA, and Termination Pending.
- [ ] Academic Warning alone does not block readiness or create a Good Standing requirement.
- [ ] Approaching Graduation is true only at 1–5 Remaining CH when the proposed for-credit schedule satisfies every remaining Course target or category slot; it remains visible independently of CGPA and termination blockers.
- [ ] Enrollment permits that completing schedule below 6 CH and rejects partial, Audit-based, zero-remaining, or 6+-remaining attempts to use the exception.
- [ ] The first-semester exception applies only in the Student's enrollment Semester and only to a 1–5 CH schedule containing every remaining non-exempt Preparatory requirement.
- [ ] With no remaining Preparatory requirement, the normal 6-CH minimum applies; neither exception changes the 12-CH maximum.
- [ ] Student progress UI presents typed blockers and the completing-schedule explanation without reconstructing backend rules.
- [ ] Registration UI mirrors the server result for guidance while the backend remains authoritative.
- [ ] Boundary matrices, Enrollment HTTP workflows, generated contracts, component tests, and bilingual RTL behavior cover both exceptions and every blocker combination.
