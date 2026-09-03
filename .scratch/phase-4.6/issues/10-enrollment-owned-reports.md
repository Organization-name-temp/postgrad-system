# 10 — Enrollment Summary, Course Registration Summary and Attendance Analytics

**What to build:** The three Reports Enrollment owns, shipped together because they share one
aggregate surface and one owner.

- **Enrollment Summary** — Students per Program, Faculty and degree level. A Student Affairs
  officer answers a ministry return without counting by hand.
- **Course Registration Summary** — Enrollments per Course Offering against capacity. A Department
  Head plans next term's Offerings from evidence rather than memory, and sees which Offerings are
  full and which will not run.
- **Attendance Analytics** — attendance percentage across Offerings, at-risk Students, and session
  cancellation rates. Not in SRS §4.12 at all: the register is an ADR-0019 extension, its data has
  been accumulating since Phase 3, and nothing has ever read it in aggregate.

**Enrollment owns all three, including Enrollment Summary — and that is a decision, not an
accident.** "Students per Program" reads most naturally off the Student anchor, which Identity
owns. But **Identity is the one module with no outbound edges**, so it cannot reach Catalog to
resolve a Program, and it must not gain that edge for a view. Enrollment already reads Catalog,
Identity and Calendar, so it is the correct home. This is recorded in ADR-0026; do not "fix" it by
moving the Report to Identity.

Two further points:

- **Attendance Analytics is scoped exactly as the ordinary register surface** — an Instructor sees
  owned Offerings, an Advisor sees advisees, and R09/R10 use the existing administrative override.
  Department Head, Student Affairs, Dean, and Council receive no new attendance surface. Attendance
  remains **flag-and-inform only**; nothing here changes a Course Status, and the at-risk figure is
  the same derived 75% threshold the Phase 3 register already uses. It is not recomputed.
- **All three gate on `grades.view_student`**, which ADR-0026 records as doing the work of a "may
  read student academic records" Permission that §3.2 does not have. Its holders exclude Student and
  Applicant, and each Report's gate is at least as strong as the list it drills into — Course
  Registration Summary is only counts per Offering, but it drills into a roster.

For Enrollment Summary and Course Registration Summary, the base scope is explicit: Advisor sees
advisees, Instructor sees assigned teaching rosters/Offerings, Department Head sees assigned
Departments, Dean sees assigned Faculties, Student Affairs and Council use their existing unscoped
Student-Grades view, and R09/R10 use the administrative override. Parameters only narrow that set.

**Blocked by:** 07 — Remaining lists on the contract; 08 — Analytics section + Application Pipeline
+ drill-through.

**Status:** ready-for-agent

- [ ] Enrollment Summary reports Students per Program, Faculty and degree level, owned by
      Enrollment and reaching Catalog and Identity through already-granted edges
- [ ] Course Registration Summary reports Enrollments per Offering against capacity for a chosen
      Semester, distinguishing full Offerings from under-subscribed ones
- [ ] Attendance Analytics reports attendance percentage per Offering, at-risk Students against the
      existing 75% threshold, and session cancellation rates
- [ ] The at-risk threshold and attendance percentage are read from the existing derivation, not
      recomputed; no Course Status is changed by anything here
- [ ] Attendance Analytics is scoped to Instructor-owned Offerings, Advisor advisees, and the R09/R10
      override; Department Head, Student Affairs, Dean, and Council receive 403
- [ ] Enrollment Summary and Course Registration Summary scope Advisor to advisees, Instructor to
      assigned rosters/Offerings, Department Head to Departments, Dean to Faculties, Student Affairs
      and Council to their existing unscoped view, and R09/R10 to the administrative override
- [ ] All three gate on `grades.view_student` and return 403 without it
- [ ] All three take parameters through the shared `<FilterBar>` with URL state
- [ ] Each chart carries its "View as table" disclosure with a Vitest series-match assertion
- [ ] Each drills into the corresponding filtered list, whose row count matches the segment figure,
      and that list exports
- [ ] No new deptrac edge is added
- [ ] Pest covers each Report's authorized payload, 403, scoping and empty case
- [ ] Every supported role has in-scope and out-of-scope rows proving filters only narrow its base
- [ ] `composer gate` and `npm run gate` green
