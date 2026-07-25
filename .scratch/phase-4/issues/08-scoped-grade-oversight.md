# 08 — Scoped grade oversight

**What to build:** Everyone with a legitimate interest in a Student's grades can see them, and
nobody sees further than their remit. An Advisor opens an advisee's record and sees their
grades, Semester GPAs, CGPA, outstanding Incompletes, and which Courses carry a must-retake
obligation — so they can advise on the actual record and plan the next registration around it.
A Department Head sees the Students of their own Department; a Dean their Faculty; Student
Affairs and the Council any Student.

Built deliberately **late**, after tickets 05 and 07, so the oversight view is built once with
Incompletes and must-retake markers already in it, rather than being extended twice.

Scoping reuses the Phase-3 machinery rather than inventing new guards: the Advisor scope comes
from the existing advisee lookup, and the R09/R10 administrative override applies with its
audit entry, per
[ADR-0015](../../../docs/adr/0015-administrative-override-of-scope-guards.md). The
permission is `grades.view_student`, already in the matrix since Phase 0 — no new permission.

**Blocked by:** 04 (GPA/CGPA engine + My Grades), 05 (Incomplete lifecycle), 07 (Grade-gated registration rules).

**Status:** ready-for-agent

- [ ] One id-addressable endpoint returns a Student's grades grouped by Semester with each Semester's GPA, their CGPA, their outstanding Incompletes with deadlines, and their must-retake obligations — reusing the ticket-04 read model rather than a second implementation of the arithmetic.
- [ ] It is gated on `grades.view_student`, and scoped per role: Advisor to their own advisees (via the existing advisee lookup, so an advisee reassignment is reflected immediately), Department Head to Students in their Department's Programs, Dean to their Faculty, Student Affairs and Council to any Student.
- [ ] An Instructor holding `grades.view_student` sees the Students on their own Offerings' rosters and no others.
- [ ] Every out-of-scope read is a 403, with a test per role. A Student cannot reach this endpoint for anyone, including themselves — they have their own.
- [ ] An R09/R10 administrator reads any Student unscoped, per ADR-0015, and that read follows the Phase-3 precedent for whether reads are audited (reads unscoped without a row; only writes audit). If any write surface is added here, it audits.
- [ ] Frontend: an advisee-grades view reachable from the Advisor's existing advising area, and a Student picker for the roles whose scope is broader than one list — reusing the existing picker component rather than a new one.
- [ ] Superseded attempts render as excluded, exactly as on the Student's own My Grades, so the two views cannot disagree about what counts.
- [ ] Any shared presentation extracted rather than duplicated between My Grades and this view — the Phase-3 precedent where the attendance cell was pulled out for reuse by two schedule views.
- [ ] Bilingual EN + AR with correct RTL layout.
- [ ] Pest feature tests cover each role's scope and its 403 boundary, the administrative override, and that the payload matches the Student's own view for the same data.
- [ ] Scribe `@urlParam` examples on new model-bound routes; `openapi.yaml` and the Orval client regenerated, both drift checks clean.
- [ ] `composer gate` and `npm run gate` both green; new screens pass axe with no serious or critical violations.
