# 07 — Period-gated changes: add/drop, late-add, withdrawal

**What to build:** FR-REG-004, fully. Once a Student has an approved registration, the academic calendar governs what they can change and when: free add/drop in the first weeks, an add-only late-add request, and self-serve withdrawal until the deadline. Every gate is read from the Calendar Semester dates, so the same flow behaves differently as time moves.

**Blocked by:** 06 (needs approved Enrollments to change).

**Status:** ready-for-agent

- [ ] **Free add/drop** (semester start → add/drop end, and **only** for a Student with an approved Initial request for that Semester): direct actions, no advisor approval. Add validates eligibility + ceiling + capacity + clash (no External Electives — those need a request); drop **hard-deletes** the Enrollment (no trace) and is blocked from taking the load below the CH floor unless the first-semester exception applies. The Advisor is **notified, not asked**. Free add/drop is unavailable to a Student with no approved Initial request.
- [ ] **Late-add** (during the late-add window only): an add-only `RegistrationRequest` of `kind: LateAdd` through the advisor line-item flow from ticket 06 (no drops). Same validators.
- [ ] **Withdrawal** (late-add end → withdrawal deadline): self-serve with a confirmation, setting the Enrollment's `CourseStatus` to **W** (permanent, transcript-visible — W now becomes a reachable status), notifying the Advisor. **No CH-floor check** on withdrawal. After the deadline, all student-initiated changes are gone from the UI and rejected by the server.
- [ ] Notifications: `enrollment.adjusted` (advisor; payload action add/drop + course) and `enrollment.withdrawn` (advisor). Bilingual, dot-free aliases.
- [ ] `/api/v1` add / drop / withdraw endpoints (`courses.register_self`), each 403/409 outside its window; Scribe examples; openapi + Orval regenerated, drift-clean.
- [ ] `features/enrollment` My Schedule gains the period-gated action buttons **in context** (add/drop only weeks 1–2, withdraw until deadline) — buttons appear only in-window, the server stays authoritative — and shows the current period + what's allowed now. EN+AR, axe-clean.
- [ ] **Tests:** Pest API-feature driving each action inside and outside its window via **`travelTo`** against seeded Semester dates — free add/drop only with an approved request, drop CH-floor guard, no-external-free-add, late-add window, withdrawal → W + no floor, post-deadline lockout, and each notification row. Gates green.
