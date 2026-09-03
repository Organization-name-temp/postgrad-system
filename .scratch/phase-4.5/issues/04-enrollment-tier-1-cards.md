# 04 — Enrollment Cards: My Registration, Advisee Requests

**What to build:** Two Cards covering the registration loop from both ends.

A **Student** lands and sees their current-semester registration at a glance: total
registered credit hours against the 6–12 limits, **which period is currently open**
(registration, add/drop, late-add, or withdrawal), and when it closes. The Card links to
whichever surface that period actually allows — the registration cart while registration is
open, My Schedule once it is not — so the Dashboard takes the Student to the action
available to them rather than to a screen that will refuse them.

An **Advisor** lands and sees the registration requests waiting on their approval: the
count, how many advisees they hold, and the three oldest pending requests by student name,
linking into the advisor queue. Until now the queue was discoverable only by opening it.

Everything here is **already derived and must be read, not recomputed**. Period state is
derived from the Semester's window dates on every read — the standing house rule — and the
credit-hour total and its limits come from the existing validator's configuration. This
Card displays those answers; it does not form its own opinion about them.

Scoping is the correctness risk. The Advisor Card must count **only their own advisees**,
resolved through the live advisee lookup Phase 3 introduced, so the Card follows advisor
reassignment the same way the queue itself does. A test asserting one Advisor's Card does
not count another's is not optional.

My Registration is a **persona Card and gates on the Student Role** — an administrator
inherits `courses.register_self` but has no Student anchor, which is precisely the case the
frontend's shared access helper already documents. Advisee Requests gates on the
`registration.approve` Permission, which Student Affairs legitimately holds too.

**Blocked by:** 01 — Dashboard foundation + My Academic Record Card.

**Status:** ready-for-agent

- [ ] A Student's Card shows total registered credit hours for the current Semester, and makes the 6–12 bounds legible rather than leaving the number unanchored.
- [ ] The Card names the currently open period and the date it closes, read from the existing derived period state.
- [ ] The Card's link targets the surface the open period allows, not a fixed destination.
- [ ] My Registration gates on the **Student Role**, so an administrator inheriting `courses.register_self` does not see it.
- [ ] A Student with no Enrollments in the current Semester, or with no period open, renders a meaningful empty state rather than a zero or an error.
- [ ] The Advisor Card shows pending request count, advisee count, and the three oldest pending requests by student name, linking into the advisor queue.
- [ ] The Advisor Card counts **only the acting Advisor's own advisees**, resolved through the live advisee lookup so it follows reassignment. A test proves one Advisor's Card excludes another's advisees.
- [ ] Advisee Requests gates on `registration.approve`; a user without it gets 403 from the endpoint.
- [ ] Both endpoints are read-only, return typed domain values with no pre-rendered display strings, and use the standard `{data, meta}` envelope.
- [ ] Bilingual EN + AR for all new strings, including every period name.
- [ ] Pest feature tests cover both endpoints: payload against the seeded world, 403 without the gate, the advisee-scoping assertion, an out-of-period Student, and the empty queue.
- [ ] Vitest + RTL covers each Card's non-trivial rendering, including the period-dependent link target.
- [ ] `openapi.yaml` and the Orval client regenerated and committed; both drift checks clean.
- [ ] No new migration, Permission, Role or module is introduced.
- [ ] `composer gate` and `npm run gate` both green; the Dashboard still passes axe with no serious or critical violations.
