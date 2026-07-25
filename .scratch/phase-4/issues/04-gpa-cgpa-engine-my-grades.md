# 04 — GPA/CGPA engine + My Grades

**What to build:** A Student sees their academic record. My Grades groups their Courses by
Semester, shows each Semester's GPA beside its courses, and shows their CGPA prominently —
the one number the whole degree turns on. Each course shows its credit hours, Letter Grade,
and whether it earned its credit, so a Student can see why their CGPA and their earned hours
differ. A superseded earlier attempt is shown on the record but visibly marked as excluded
from the CGPA, so the rule is legible rather than leaving the numbers looking wrong. Before a
Student has any graded course the CGPA renders as a dash, not 0.00 — a new record must not
look like a catastrophic one.

Nothing is visible until the Department Head has finalized the Sheet: a Student never sees a
provisional grade that later changes.

This ticket closes SRS §4.7.2, which is blank, and ROADMAP §5's seven-item agenda. The rules
are settled in the spec; the ones that most often get implemented wrong are called out as
criteria below. Ownership of this read model is
[ADR-0020](../../../docs/adr/0020-grading-owns-gpa-read-model.md).

**Blocked by:** 03 (Sign, Department-Head review, finalize).

**Status:** ready-for-agent

- [ ] The GPA is `Σ(GPA points × credit hours) ÷ Σ(credit hours)` over the Student's CGPA-counting, non-superseded, graded Enrollments; the semester GPA is the same sum restricted to one Semester. Credit-weighted, **not** a plain mean — the 1-CH Preparatory courses must not weigh the same as a 3-CH Core course.
- [ ] Counting follows the `CourseStatusCounting` matrix Phase 3 already shipped; it is read, not reimplemented. Every letter from A+ to F counts, F at 0.00 included.
- [ ] Earning a Course's credit hours is a **separate** `≥ C+` test. A D counts fully in the CGPA denominator and earns nothing; the two tallies are independent and a test asserts a case where they disagree.
- [ ] Among a Course's for-credit Attempts only the chronologically latest enters the CGPA, ordered by the Semester's start date — **not** by (year, term), since Fall precedes the following Spring. A test crosses that year boundary.
- [ ] "Latest" is unconditional: a Retake with a *worse* grade lowers the CGPA. This has its own test, because "best attempt" is the intuitive-but-wrong reading.
- [ ] Two different elective Courses never supersede one another, so both count — FR-REG-006.4 needs no special code. A test proves it falls out of the by-Course grouping.
- [ ] Supersession is **derived at read time**, never stored — no superseded flag anywhere.
- [ ] Quality points accumulate in integer hundredths so no floating-point drift is possible; the quotient is rounded **half-up to two decimals exactly once**, and that single value is authoritative for display and for every threshold comparison. A test asserts a value that rounds *across* the 3.0 boundary and shows display and decision agreeing.
- [ ] With no CGPA-counting graded Enrollments the CGPA is **null**, not 0.00, and renders as a dash. Same for a Semester with no counted courses.
- [ ] The values are computed on demand and never stored or cached.
- [ ] `Grading\Public` ships the contract Phase 5's Academic Standing and Phase 6's Records will consume: a Student's CGPA, a Semester GPA, and the Grades for a set of Enrollments. A facade test drives it through the bound contract.
- [ ] A Student reads only their own grades; the endpoint is gated on `grades.view_own` and another Student's record is unreachable.
- [ ] Frontend My Grades: Semester sections with per-Semester GPA, a CGPA header, per-course credit hours / letter / earned state, and superseded attempts rendered as excluded. Bilingual EN + AR with correct RTL layout, and figures using tabular numerals.
- [ ] Pest feature tests at the API seam plus pure unit tests for the calculator — the calculator must be testable with no HTTP stack and no database, following the `AttendanceCalculator` precedent.
- [ ] Scribe `@urlParam` examples on new model-bound routes; `openapi.yaml` and the Orval client regenerated, both drift checks clean.
- [ ] `composer gate` and `npm run gate` both green; My Grades passes axe with no serious or critical violations.
