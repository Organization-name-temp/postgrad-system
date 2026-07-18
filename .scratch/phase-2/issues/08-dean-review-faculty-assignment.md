# 08 — Dean review + Dean↔Faculty assignment + admin assignment UI

**What to build:** An administrator assigns Deans to Faculties; each Dean then reviews **only** the Student-Affairs-approved applications for their own Faculty, approving them for interview or rejecting them. This introduces the first role-gated (admin-only) surface and the org-assignment mapping.

**Blocked by:** 07 — Application status machine + Student Affairs screening + status timeline

**Status:** ready-for-agent

- [ ] Identity owns a `faculty_dean_assignments` mapping (`user_id`, opaque `faculty_id` — no FK, unique per pair); `Identity\Public` exposes `facultiesForDean` / `deansForFaculty`.
- [ ] A minimal admin **"Assignments"** screen + endpoints **gated on the R09/R10 role** (not a permission — no new permission invented) let an admin assign/unassign a Dean (R07 user) to a Faculty; non-admins → 403. A role-gated **Administration** nav section is visible only to admins.
- [ ] The **Dean queue** lists `UnderDeanReview` applications whose program's faculty (resolved `application → program_id → facultyId` via `ProgramCatalog`) is among the Dean's assigned faculties; a Dean acting on an out-of-faculty application → **403**.
- [ ] Dean **approve-for-interview** transitions `UnderDeanReview → InterviewScheduled` (the concrete slot booking follows in ticket 09); **reject** → `Rejected` with reason. Both notify the applicant.
- [ ] Pest feature tests: assignment CRUD gated on admin role (403 for others), faculty-scoped queue, out-of-faculty action → 403, approve/reject transitions + notifications. Vitest for the assignment screen if non-trivial. All gates green.
