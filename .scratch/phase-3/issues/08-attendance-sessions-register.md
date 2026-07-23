# 08 — Attendance: Class Sessions + register + audit-at-risk

**What to build:** The attendance register ([ADR-0019](../../../docs/adr/0019-attendance-session-register.md), a deliberate SRS extension). Each Offering's weekly meeting times generate **Class Sessions** across its Semester; the assigned **Instructor** gets a My Offerings list, opens an Offering's roster, cancels sessions that don't happen, and marks who was present. A Student's attendance percentage is always derived, and the one consequence anywhere is a visible flag when an Audit enrollment falls below 75%.

**Blocked by:** 06 (needs Enrollments for a roster to mark).

**Status:** ready-for-agent

- [ ] `ClassSession` rows auto-generated from an Offering's meeting rows across the Semester start→end dates. The assigned Instructor can **cancel** a session (a cancelled session never counts in any denominator — this is also the holiday answer; no holiday calendar).
- [ ] Attendance mark per (session, active Enrollment): binary present/absent, correctable. Withdrawn (W) enrollments keep their historical marks but drop off future sessions. No late/excused taxonomy.
- [ ] Attendance percentage is **derived on demand** (present ÷ non-cancelled sessions recorded to date), never stored. The **audit-at-risk** flag: an Audit Enrollment whose derived percentage first crosses below 75% raises a chip and one `attendance.audit_at_risk` notification to the Student, fired on the **downward crossing** (recompute around each register save), never on every mark, and never an automated status change.
- [ ] Authorization **by assignment** — only the Offering's assigned Instructor may generate/cancel sessions and mark attendance; others read-only/forbidden. No new permission; the §3.2 matrix is untouched (the Phase 2 interviewer precedent).
- [ ] `/api/v1` endpoints for the instructor's offerings, an offering's sessions + roster, cancel-session, and mark-attendance; Scribe examples; openapi + Orval regenerated, drift-clean.
- [ ] `features/enrollment` (or an instructor area within it): My Offerings → a roster grid with session columns, present/absent toggles, cancel-session, per-student derived % and an audit-at-risk chip. Students see their own % on My Schedule (from ticket 06). EN+AR, axe-clean.
- [ ] **Tests:** targeted unit for session generation from meeting rows + cancellation arithmetic and the percentage + crossing-detection; Pest API-feature for generation, cancel, mark/correct, derived %, the at-risk notification firing once on the crossing, and assignment-based authorization. Sparse Vitest for the register grid. Gates green.
