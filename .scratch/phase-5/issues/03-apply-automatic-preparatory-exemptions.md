# 03 — Apply automatic Preparatory Exemptions at admission

**What to build:** Turn accepted admission evidence into immutable automatic Preparatory Exemption decisions before first registration, with a safe recovery path when post-commit handling is missed.

**Blocked by:** 01 — Bind Students to their admission Curriculum Version; 02 — Assign semantic Preparatory Requirement Roles.

**Status:** complete

- [x] Admissions publishes an immutable Public acceptance-evidence snapshot containing the facts needed for exemption evaluation without exposing its models.
- [x] Enrollment idempotently creates approved automatic Exemption decisions against the Student's bound Curriculum Entries for qualifying language, same-Department E-JUST MSc-to-PhD, and E-JUST BSc evidence.
- [x] The same-Department rule uses structured previous Program evidence, and every automatic rule resolves semantic Roles rather than Course IDs.
- [x] Automatic decisions are standalone academic decisions, not Enrollments, and retain their source evidence and causative acceptance.
- [x] Repeated event handling creates no duplicate decisions, history, or notifications.
- [x] A per-Student reconciliation command produces the same result after a missed event and is safe to rerun.
- [x] Students can see effective automatic Exemptions and their reasons before composing a first Registration Request.
- [x] Acceptance remains committed if a post-commit listener fails, and the recovery behavior is covered through PostgreSQL feature tests.
- [x] OpenAPI/client generation, bilingual notifications and UI, authorization, audit evidence, and module-boundary gates pass.
