# 02 — View current unofficial Transcripts

**What to build:** Let Students, live Advisors, and Student Affairs view a current bilingual unofficial Transcript with academic history, authoritative GPA, and browser printing.

**Blocked by:** 01 — Preserve and recover Student Identification Type.

**Status:** complete

- [x] Introduce Records with discovered provider/routes and explicit Public dependency edges, and a Records frontend feature composed by the app; preserve existing module and feature boundaries.
- [x] Students read their own, Advisors read live advisees', and Student Affairs reads all current Transcripts; preserve administrator override and mixed-Role permission union. Reassignment removes former Advisor access; other Roles gain no Transcript access from the deferred PDF permission.
- [x] Terminated Students retain historical read access without lifecycle mutation. Missing required identity is explicitly represented as unresolved rather than guessed.
- [x] Header shows admission-time Student names, University ID, Identification Type/number, Faculty, Department, Program, degree level, admission term, and lifecycle status through owner Public contracts.
- [x] Include approved Enrollments grouped by Semester, all Attempts, recorded CH/classification, finalized Grades only, and applicable AU/W/I/FW or In Progress; pending Registration Requests and draft/under-review marks are excluded.
- [x] Use Grading's authoritative current Grades, contribution/supersession facts, Semester GPA, and CGPA. Null GPA displays as a dash; later corrections and Incomplete resolution update the unofficial view without Records recalculating rules.
- [x] EN/LTR and AR/RTL views and browser printing show Course code/title, CH, classification, Grade/status, and non-CGPA-contributing Attempt indicators using theme/design tokens.
- [x] Contact details, financial records, review notes, and termination-case evidence are excluded. Scoped independently filterable Student pickers use Support pagination, readable-field allowlists, and audited export siblings.
- [x] PostgreSQL HTTP tests cover owner/non-owner and live/former Advisor scopes, other Roles/admins, finalized versus provisional marks, statuses, retakes, CH snapshots, null GPA, and owner changes. Focused frontend tests cover non-trivial behavior.
- [x] Generated OpenAPI/client artifacts are synchronized, EN/AR/print implications are verified, and affected gates pass; report any gate not run with its concrete reason.

## Verified completion

Acceptance is verified by the ticket-specific coverage, complete gates and resolved
consolidated review recorded in [the Phase 6 evidence](../verification.md).
