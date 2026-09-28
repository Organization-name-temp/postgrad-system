# 02 — Manage User Roles through the administrator interface

**What to build:** System and Super Administrators find a User and change permitted Role assignments, with audited changes reflected in that User’s effective access.

**Blocked by:** 01 — Resolve administrator Role assignment boundaries.

**Status:** needs-info

- [ ] Implement only the accepted issue-01 rules; the Role set and Permission matrix remain fixed in code, and effective Permissions remain their union.
- [ ] Both assignment and removal enforce administrator authority on the API; non-administrators receive 403. Cover idempotent changes, invalid Roles, concurrent updates and each resolved lifecycle/delegation restriction.
- [ ] Retain actor, target and before/after Roles in an append-only Audit Log event. A failed mutation leaves assignments unchanged.
- [ ] Provide bilingual search, current Roles, reviewable changes and actionable errors; refresh permission/nav/Card composition after access changes.
- [ ] Regenerate OpenAPI and the client; prove the multi-role union and API denial after removal through meaningful tests and one serial browser journey.
- [ ] Backend and frontend affected gates pass; verify EN/AR, LTR/RTL and module/feature seams.
