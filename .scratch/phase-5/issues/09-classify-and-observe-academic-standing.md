# 09 — Classify and observe Academic Standing

**What to build:** Show every Student exactly one current Academic Standing derived from authoritative Grades and CGPA, and record only real Standing changes for history and once-only notifications.

**Blocked by:** 08 — Show the target-aware Credit-Hour Summary.

**Status:** complete

- [x] Standing is derived on demand and is exhaustive: Termination Pending takes precedence; otherwise no finalized CGPA-bearing Grade is Not Yet Assessed, CGPA below 3.00 is Academic Probation, CGPA at least 3.00 with any effective Grade below C+ is Academic Warning, and otherwise it is Good Standing.
- [x] Only a successful later Attempt in the same Course supersedes a failed Grade; a different elective satisfying the Curriculum leaves the original Grade relevant to Warning.
- [x] Current Standing reads never depend on or mutate a stored Standing snapshot.
- [x] An append-only Standing Observation is recorded only when the derived value actually changes; repeated events or reconciliation create no duplicate Observation or notification.
- [x] Initial Not Yet Assessed does not notify; adverse transitions and recovery notify the Student and current Advisor once in both languages.
- [x] Academic Probation supplies the configured minimum-CH Semester ceiling, including Audit, through the AcademicStanding Public contract for Enrollment to enforce without duplicating classification logic.
- [x] Event handlers and the global/per-Student academic-standing reconciliation command share one idempotent application service; GET requests remain side-effect free.
- [x] Student and authorized staff views plus the Academic Standing Dashboard Card expose the current classification, explanation, and relevant observation history.
- [x] Unit and PostgreSQL feature tests cover all boundaries, supersession rules, event/command equivalence, authorization, notifications, OpenAPI/client generation, and EN/AR RTL behavior.
