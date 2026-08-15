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

Three things that are genuinely new here rather than copied from ticket 06:

- **Derived-value filters land for the first time**, which is why this batch depends on ticket 04.
  CGPA range and attendance percentage become real `WHERE` and `ORDER BY` clauses through the batch
  and aggregate facade methods — not a PHP loop behind a pagination that would then be a lie.
- **Filterable-if-and-only-if-readable stops being theoretical.** CGPA is the permission-sensitive
  field the rule was written for: an Instructor who may list their roster but not read CGPAs must
  not be able to send `cgpa_min` and read `meta.total`. The allowlist here is genuinely a function
  of the actor, and the negative case must be asserted.
- **The four personal one-screen lists** — My Grades, my schedule, my Offerings, my registration —
  adopt the envelope but gain **no visible filter control**. One shape in the API, proportionate UI.

**Blocked by:** 02 — Query contract foundation; 03 — Export foundation; 04 — Aggregate facades +
parity test.

**Status:** ready-for-agent

- [ ] Every remaining collection endpoint returns the paginated envelope; **no unpaginated
      collection remains anywhere in the API**
- [ ] Each declares filter, sort and `q` allowlists, actor-aware where fields are
      permission-sensitive
- [ ] CGPA range filtering and sorting work through the ticket-04 aggregates, in SQL, with
      pagination that reflects the true matching set
- [ ] Attendance percentage filtering works the same way
- [ ] A user who may list a set but not read CGPAs cannot filter or sort by it — asserted as a
      **negative** test, not only a positive one
- [ ] Advisor, Department Head and Instructor scopes stay base constraints; out-of-scope filter
      values return empty pages
- [ ] Work surfaces gain `<FilterBar>` with URL state and an `/export` sibling with an Audit Log
      entry; the four personal lists gain the envelope and no filter control
- [ ] Notifications is aligned to the shared envelope
- [ ] Every migrated endpoint adds its row to the cross-module contract test dataset, which now
      covers the whole API
- [ ] Existing Pest tests across these modules updated to the new envelope with their original
      authorization assertions intact
- [ ] `openapi.yaml` and the Orval client regenerated and committed; both `api:check` gates pass
- [ ] `composer gate` and `npm run gate` green
