# 15 — Detect termination evidence and reconcile Cases

**What to build:** Detect current Preparatory and D/F termination evidence, represent it in one deterministic Case per Student, and replace or clear the Case whenever authoritative evidence changes before a final decision.

**Blocked by:** 09 — Classify and observe Academic Standing; 14 — Reconcile remediation opportunities and deadlines.

**Status:** ready-for-agent

- [ ] Ordinary Preparatory non-completion becomes actionable only after the second consecutive main Semester's Grade-Submission Deadline beginning with the enrollment Semester.
- [ ] A post-Revocation Preparatory requirement becomes actionable only at its assigned remediation checkpoint; Awaiting Offering cannot trigger termination.
- [ ] A valid unresolved Incomplete at either checkpoint leaves evaluation indeterminate until resolution or expiry and is never converted by AcademicStanding.
- [ ] The Grade trigger requires current effective D or F Grades in at least two distinct Courses; D+ does not qualify, repeated Attempts in one Course count once, and a later successful Attempt in that Course clears it.
- [ ] English is evaluated as one semantic Preparatory Requirement Role rather than a separate termination condition.
- [ ] A transactional uniqueness invariant permits at most one non-terminal Termination Case per Student, aggregating every currently actionable trigger component.
- [ ] The canonical fingerprint is order-independent and versions each Preparatory requirement, checkpoint source, and effective Grade, Withdrawal, Exemption, and Revocation evidence including explicit no-Attempt; D/F evidence includes Course and effective Grade IDs.
- [ ] Reconciliation closes the Case as Trigger Cleared when no actionable trigger remains and immediately removes the Termination Pending readiness block.
- [ ] A changed but still-actionable fingerprint atomically closes the old Case as Evidence Changed and opens a fresh Advisor-stage Case; earlier reviews remain history and neither fingerprint is suppressed.
- [ ] A dismissed fingerprint remains suppressed only while exactly unchanged; later authoritative evidence may create a new Case.
- [ ] Case creation, replacement, and clearing update derived Standing/Observations and once-only notifications atomically without duplicating an unchanged Termination Pending Observation.
- [ ] Determinate remediation evaluation invokes Enrollment's idempotent Public command with obligation ID and expected effective deadline in the same transaction; an exact retry is a no-op and deadline mismatch rolls back all Case/Standing effects for reload.
- [ ] Event handlers and global/per-Student academic-standing reconciliation use the same service, while Standing and readiness GETs remain side-effect free.
- [ ] Student-facing Case evidence and status plus authorized queues explain the complete aggregate trigger set in EN/AR and LTR/RTL.
- [ ] Unit, HTTP feature, concurrency, retry, Incomplete, fingerprint, notification, OpenAPI/client, and component tests cover every trigger and transition boundary.
