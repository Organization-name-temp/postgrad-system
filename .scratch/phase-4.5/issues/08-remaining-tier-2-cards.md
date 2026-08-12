# 08 — Remaining Cards: Admission Decisions, Outstanding Incompletes

**What to build:** The last two Cards, completing the inventory of twelve.

A **Dean** lands and sees the number of Applications awaiting their decision, **scoped to
their own Faculty**, linking into the decision queue. Admissions decisions are the last step
before an Applicant becomes a Student, and until now nothing told a Dean that applicants
were waiting on them.

An **Instructor** lands and sees their **outstanding Incompletes** and the nearest
resolution deadline. This one earns its place by preventing real harm: an unresolved
Incomplete converts to a **Fail Withdrawal** when its deadline passes, and that conversion is
the single state-changing scheduled command in the system. A Card that surfaces the nearest
deadline gives the Instructor a chance to resolve it first.

The Incomplete deadline is **derived, never stored** — the following Semester's start plus
the configured offset — so correcting a Semester's start date moves every outstanding
deadline with it. The Card reads that derivation and must never cache or denormalise it. It
must also handle the case the conversion command already handles explicitly: an Incomplete
whose following Semester **does not exist yet** has no computable deadline, and the Card must
show the Incomplete without inventing one.

Faculty scoping on the Dean Card must reuse the existing faculty-scoped queue derivation, so
the Card and the queue can never disagree about what a Dean is responsible for, and one
Dean's Card never counts another Faculty's Applications.

Both Cards extend modules whose Card surfaces already exist, which is why this ticket waits
on tickets 03 and 05 rather than on 01 alone — it adds to their controllers and feature
directories rather than racing them.

**Blocked by:** 03 — Admissions Cards; 05 — Grading Cards.

**Status:** ready-for-agent

- [ ] The Dean Card shows the count of Applications awaiting decision, linking into the decision queue.
- [ ] The count is scoped to the acting Dean's own Faculty, reusing the existing faculty-scoped queue derivation. A test proves one Dean's Card excludes another Faculty's Applications.
- [ ] Admission Decisions gates on `applicant.decide`; a user without it gets 403 from the endpoint.
- [ ] The Incompletes Card shows the count of the Instructor's outstanding Incompletes and the nearest resolution deadline, linking into the resolution surface.
- [ ] The deadline is read from the existing derivation and is never stored or cached.
- [ ] An Incomplete whose following Semester does not yet exist is shown **without** a fabricated deadline, and does not error the Card.
- [ ] Outstanding Incompletes gates on `grades.enter`; a user without it gets 403 from the endpoint.
- [ ] Both endpoints are read-only, return typed domain values with no pre-rendered display strings, and use the standard `{data, meta}` envelope.
- [ ] Both empty cases render the shared empty state rather than a zero.
- [ ] Bilingual EN + AR for all new strings, including the deadline and no-deadline copy.
- [ ] Pest feature tests cover both endpoints: payload against the seeded world, 403 without the gate, the Dean faculty-scoping assertion, the missing-following-Semester case, and both empty cases.
- [ ] Vitest + RTL covers each Card's non-trivial rendering, including the no-deadline state.
- [ ] `openapi.yaml` and the Orval client regenerated and committed; both drift checks clean.
- [ ] No new migration, Permission, Role or module is introduced.
- [ ] `composer gate` and `npm run gate` both green; the landing page still passes axe with no serious or critical violations.
