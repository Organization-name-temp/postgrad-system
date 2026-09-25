# 05 — Deliver the Transfer workflow

**What to build:** Let a Student request recognition of eligible external study through Advisor, Department Head, and Academic Affairs Council review while preserving source evidence and enforcing the lifetime Transfer cap.

**Blocked by:** 04 — Deliver the manual Exemption workflow.

**Status:** complete

- [x] Transfer reuses the established Draft, submit, return, resubmit, reject, transition-history, notification, and live-reviewer behavior without coupling request types incorrectly.
- [x] Submission is accepted only within the inclusive calendar-month window beginning at admission acceptance.
- [x] The request records the external institution, Course evidence, source credit hours, and external Grade exactly as printed.
- [x] The Advisor recommends first; the scoped Department Head verifies equivalency and records the normalized E-JUST Letter Grade; Council makes the final decision.
- [x] Only A+, A, B+, or B normalized outcomes qualify, with bilingual errors at all Grade and deadline boundaries.
- [x] Final approval atomically creates an immutable Transfer decision and revalidates the lifetime 9-CH cap under concurrency using target Curriculum Course hours.
- [x] Source hours remain evidence only and do not determine Transferred CH or bypass the cap.
- [x] Transfer never affects CGPA and never creates a synthetic Enrollment or Grade.
- [x] Student, Advisor, Department Head, and Council screens expose only their authorized actions and retain all attributed reviews.
- [x] PostgreSQL feature and race tests, component tests, OpenAPI/client generation, export behavior, audit, and EN/AR RTL coverage pass.
