# 04 — Deliver the manual Exemption workflow

**What to build:** Let a Student correct or supply missing Preparatory exemption evidence through a complete, auditable Student–Advisor–Department Head workflow that creates an immutable effective decision.

**Blocked by:** 03 — Apply automatic Preparatory Exemptions at admission.

**Status:** ready-for-agent

- [ ] A Student can create, edit, view, and delete or abandon only their own Draft manual Exemption request for a Preparatory target in the bound Curriculum.
- [ ] Submission locks the evidence, records an append-only transition, and routes the request to the Student's current Advisor.
- [ ] The Advisor may recommend, return with a required reason, or reject only requests for current advisees.
- [ ] Return makes the request editable; resubmission preserves but invalidates earlier reviews and starts review again against the revised evidence.
- [ ] The scoped Department Head may make the final approve, return, or reject decision only after Advisor recommendation.
- [ ] Final approval atomically creates a separate immutable effective Exemption decision; Approved and Rejected requests are terminal.
- [ ] Corrected evidence may approve an Exemption after either a failed or successful Attempt without deleting the Enrollment or Grade.
- [ ] Student and reviewer queues follow the existing paginated, actor-aware query contract and expose audited export siblings where required.
- [ ] Student and reviewer screens, histories, reasons, notifications, validation, and empty states work in English/Arabic and LTR/RTL.
- [ ] API feature tests cover every transition, live Advisor scope, Department scope, stale action attempts, idempotency, and generated contracts.
