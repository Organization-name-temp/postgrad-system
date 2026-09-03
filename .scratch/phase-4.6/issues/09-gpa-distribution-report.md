# 09 — GPA Distribution Report

**What to build:** A Department Head opens Analytics, picks a Program and a Semester, and sees how
the cohort's CGPA is actually spread — not an average, a distribution — with the band below 3.0
visible as its own bar. They click that bar and land on exactly those Students, filtered, and
export the list.

This is SRS §4.12's GPA Distribution report, and the first thing in the system to make Phase 4's
GPA read model answer a question about a population rather than a person.

- **Owned by Grading**, which owns the GPA/CGPA read model (ADR-0020) and already holds granted
  edges to Catalog, Identity, Enrollment and Calendar. Zero new deptrac edges.
- **Every figure comes from ticket 04's batched owner facade.** The distribution does not re-derive
  the arithmetic: ADR-0020 keeps the credit-weighted formula in one PHP calculator, and ADR-0028
  batches its inputs without cross-module SQL.
- **Bands follow the grading scale** (ADR-0021), and the rendering follows `design-system.md` §14:
  a sequential single-hue ramp for intensity rather than a categorical set, and axis and legend text
  always present so colour is never the only signal.
- **`null` is not zero.** A Student with no CGPA-counting graded Enrollment has a `null` CGPA and
  belongs in no band — they are excluded from the distribution and reported separately, never
  counted as 0.00. Getting this wrong would put every new Student in the failing band.
- **Gated on `grades.view_student`.** SRS §4.12 lists SA, Dean and Department Head; the Permission
  also reaches Advisor, Instructor and Council. That divergence is deliberate and recorded in
  ADR-0026: all of them may already open an individual Student's grades, so an aggregate discloses
  nothing new — and the Permission excludes Student and Applicant, which `standing.monitor` would
  not. Department Head scope narrows to their Department, Dean to their Faculty.
- **Drill-through lands on the oversight student list** that ticket 07 made filterable, carrying
  the band as a CGPA range filter.

**Blocked by:** 07 — Remaining lists on the contract; 08 — Analytics section + Application Pipeline
+ drill-through.

**Status:** ready-for-agent

- [ ] The Report renders a CGPA distribution across bands for a chosen Program and optional
      Semester
- [ ] Every figure is read from the ticket-04 aggregate; the credit-weighted arithmetic is not
      re-implemented here
- [ ] Students with a `null` CGPA are excluded from the bands and surfaced separately, never
      counted as 0.00
- [ ] Bands follow the ADR-0021 scale, rendered with §14's sequential ramp and always-present axis
      and legend text
- [ ] Parameters use the shared `<FilterBar>` and live in the URL
- [ ] The chart carries its "View as table" disclosure, and Vitest asserts the table matches the
      series
- [ ] Clicking a band navigates to the oversight student list filtered to that CGPA range, and the
      row count matches the band's figure
- [ ] Gated on `grades.view_student`, returning 403 without it
- [ ] A Department Head sees only their Department and a Dean only their Faculty
- [ ] Advisor sees only advisees; Instructor sees only the teaching roster; Student Affairs and
      Council are unscoped only as allowed by the existing Student-Grades screen; R09/R10 use the
      explicit override
- [ ] Every supported role has in-scope and out-of-scope rows proving filters only narrow its base
- [ ] Pest covers the authorized payload, the 403, the scoping, the `null`-CGPA case and the empty
      case
- [ ] `composer gate` and `npm run gate` green
