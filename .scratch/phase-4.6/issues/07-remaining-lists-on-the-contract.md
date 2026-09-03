# 07 — Calendar, Enrollment, Grading, Identity and Notifications lists on the contract

**What to build:** The second and final migration batch, after which **every collection endpoint in
the system speaks one contract**. An Advisor filters their advisee list by CGPA range and finds the
students who need them before registration opens. An Instructor filters a roster by Course Status
and attendance percentage. A Department Head filters the grade-sheet queue by Semester, kind and
state and sorts by how long a Sheet has waited. Student Affairs filters the oversight student list
by Program and CGPA and exports it.

**Calendar:** Semesters, and a Semester's Course Offerings.
**Enrollment:** the advisor registration queue, registration requests, Offering rosters, the
on-behalf student list, personal schedules, and the eligible-course lists.
**Grading:** the instructor's Offerings and Sheets, the Department Head's review queue, outstanding
Incompletes, the oversight student list, and My Grades.
**Identity:** Faculty–Dean and Department-Head assignments, and the instructor, advisor, dean and
department-head pickers.
**Notifications:** already paginated — aligned to the shared envelope so there is no second shape
left in the API.

**Export coverage:** `/semesters/export`, `/semesters/{semester_id}/offerings/export`,
`/registration/queue/export`, `/offerings/{offering}/register/export`, `/offerings/mine/export`,
`/grading/offerings/export`, `/grading/department-head/sheets/export`,
`/grading/incompletes/export`, `/grading/students/export`,
`/admin/faculty-dean-assignments/export`, and `/admin/department-head-assignments/export`.
Personal collections (`/registration/requests`, `/registration/schedule`,
`/registration/eligible-courses`, per-student/on-behalf eligible-course and schedule reads,
`/grading/my-grades`, `/notifications`) are excluded because they duplicate a single person's
already-visible record without serving a staff workflow. Instructor/advisor/dean/department-head and
on-behalf-student pickers are excluded to prevent directory extraction. Tests prove every excluded
`/export` route is absent.

Three things that are genuinely new here rather than copied from ticket 06:

- **Derived-value filters land for the first time**, which is why this batch depends on ticket 04.
  CGPA range and attendance percentage use batched owner-facade results, with filtering and sorting
  completed before in-memory slicing — never a per-row facade loop or post-pagination filter.
- **Filterable-if-and-only-if-readable stops being theoretical.** CGPA is the permission-sensitive
  field the rule was written for: an Instructor who may list their roster but not read CGPAs must
  not be able to send `cgpa_min` and read `meta.total`. The allowlist here is genuinely a function
  of the actor, and the negative case must be asserted.
- **The personal one-screen lists** — My Grades, my schedule, my Offerings, my registration, and
  the corresponding personal eligibility/notification collections —
  adopt the envelope but gain **no visible filter control**. One shape in the API, proportionate UI.

**Blocked by:** 02 — Query contract foundation; 03 — Export foundation; 04 — Batched owner-facade
derivation + result parity.

**Status:** ready-for-agent

- [ ] Every remaining collection endpoint returns the paginated envelope; **no unpaginated
      collection remains anywhere in the API**
- [ ] Each declares filter, sort and `q` allowlists, actor-aware where fields are
      permission-sensitive
- [ ] CGPA range filtering and sorting use ticket 04's batched owner facade; filtering and sorting
      finish before in-memory page slicing, with truthful totals and page boundaries
- [ ] Attendance percentage filtering works the same way
- [ ] Student lookup supports partial University-ID matching, and every bilingual search normalizes
      Arabic on both sides
- [ ] Academic Standing is not a Phase 4.6 filter; it remains deferred to Phase 5
- [ ] A user who may list a set but not read CGPAs cannot filter or sort by it — asserted as a
      **negative** test, not only a positive one
- [ ] Advisor, Department Head and Instructor scopes stay base constraints; out-of-scope filter
      values return empty pages
- [ ] Every named export sibling exists with `<FilterBar>` URL state, authenticated Axios
      fetch-to-blob, and an Audit Log entry; every named exclusion returns 404
- [ ] Notifications is aligned to the shared envelope
- [ ] Every migrated endpoint adds its row to the cross-module contract test dataset, which now
      covers the whole API
- [ ] Existing Pest tests across these modules updated to the new envelope with their original
      authorization assertions intact
- [ ] `openapi.yaml` and the Orval client regenerated and committed; both `api:check` gates pass
- [ ] Sort is comma-separated and multi-column; every endpoint documents each `@queryParam`, keeps
      model-bound `@urlParam ... Example:`, and regenerates deterministic OpenAPI/Orval artifacts
- [ ] `composer gate` and `npm run gate` green
