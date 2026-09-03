# 05 — Grading Cards: My Grade Sheets, Sheets Awaiting Review

**What to build:** Two Cards covering the grading round from both ends.

An **Instructor** lands and sees their teaching load and what it owes: this Semester's
Course Offerings, how many of their Grade Sheets are **not yet Finalized**, and a flag when
an Offering's **Grade-Submission Deadline has already passed**. Each row links into that
Offering's Sheet. Today an Instructor has no way to learn any of this without opening the
grading surface and reading it off.

A **Department Head** lands and sees their review queue: the number of Grade Sheets awaiting
their review and which Offerings they belong to, linking into the review screen. Instructors
blocked on a Head become visible to that Head on sign-in.

The Grade-Submission Deadline flag is a **nudge, not a gate** — the glossary is explicit
that past it an Offering with no Finalized Sheet is flagged and *nothing is blocked or
changed*. This Card is a second surface for the existing flag, and it must not acquire an
opinion the domain does not have.

The Department Head Card's scoping carries a case that is easy to miss and must be tested:
a Head assigned **university-wide** — the nullable-department scope
[ADR-0010](../../../docs/adr/0010-catalog-curriculum-model.md) makes possible — reaches the
department-less **Preparatory** Sheets, and a department-scoped Head does not. The Card must
count exactly what the existing review queue would show that Head, including this case, and
must count nothing for a Department with no Head assigned.

Both Cards gate on Permissions, not Roles: `grades.enter` for the Instructor Card — which
Student Affairs also holds, and legitimately sees for central entry — and `grades.finalize`
for the review Card. Authority over a *particular* Sheet remains assignment-based inside the
controller, exactly as the grading routes already work; the Permission opens the Card, the
assignment scopes its contents.

**Blocked by:** 01 — Dashboard foundation + My Academic Record Card.

**Status:** ready-for-agent

- [ ] The Instructor Card shows this Semester's Offerings, the count of Sheets not yet Finalized, and links into each Offering's Sheet.
- [ ] An Offering past its Grade-Submission Deadline with no Finalized Sheet is visibly flagged, and the flag changes and blocks nothing.
- [ ] My Grade Sheets gates on `grades.enter`; a user without it gets 403 from the endpoint.
- [ ] The Department Head Card shows the count of Sheets awaiting review and the Offerings they belong to, linking into the review screen.
- [ ] A **university-wide** Department Head's Card includes the department-less Preparatory Sheets; a department-scoped Head's Card does not. Both cases are covered by a test.
- [ ] A Department with no Head assigned contributes to nobody's Card, and this does not error.
- [ ] Sheets Awaiting Review gates on `grades.finalize`; contents are scoped by the existing Department-Head assignment, not by the Permission alone.
- [ ] Both endpoints are read-only, return typed domain values with no pre-rendered display strings, and use the standard `{data, meta}` envelope.
- [ ] An Instructor with no Offerings this Semester, and a Head with an empty queue, both render the shared empty state rather than a zero.
- [ ] Bilingual EN + AR for all new strings, including the overdue-deadline copy.
- [ ] Pest feature tests cover both endpoints: payload against the seeded world, 403 without the gate, the overdue flag, the university-wide versus department-scoped Head cases, and both empty cases.
- [ ] Vitest + RTL covers each Card's non-trivial rendering, including the flagged state.
- [ ] `openapi.yaml` and the Orval client regenerated and committed; both drift checks clean.
- [ ] No new migration, Permission, Role or module is introduced.
- [ ] `composer gate` and `npm run gate` both green; the Dashboard still passes axe with no serious or critical violations.
