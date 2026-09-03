# 03 — Admissions Cards: My Application, Screening Queue

**What to build:** Two Cards, one for each side of the admissions counter.

An **Applicant** lands on their dashboard and sees where their Application stands — the
current Application Status as a chip, what happens next or what they must do, and a link
into the full status timeline. Today the only way to learn any of this is to open the
application itself.

A **Student Affairs officer** lands and sees how much work is waiting: the count of
Applications pending screening, **how many of those are overdue**, and the three oldest by
name and age, each linking into the screening queue.

That second Card closes **SRS FR-ADM-005.5** — *"the system SHALL display pending
application count and aging on the Student Affairs dashboard"* — a requirement that has
been unbuilt since Phase 2 because there was no dashboard to display it on. It is the only
Card in the phase that converts demo polish into genuine specification coverage, and it is
the last thing to cut if anything gets cut.

The aging arithmetic is **already built and must be reused, not reimplemented**: Phase 2
ticket 11 shipped business-day aging (skipping the Egyptian Friday/Saturday weekend) and
the configurable screening SLA behind the overdue-flagging command. This Card reads the
same derivation. Overdue is a *count and a visual distinction* on the Card — it changes
nothing and blocks nothing, exactly as the existing flag-and-notify command does.

My Application is a **persona Card and gates on the Applicant Role**, not on
`application.view_status` — an administrator inherits that Permission but has no
Application, so a Permission gate would render a broken card. Ticket 01 established the
rule and the registry support; this ticket is its second consumer.

Rows reuse `<StatusChip>` so the Cards read continuously with the screens they link into.

**Blocked by:** 01 — Dashboard foundation + My Academic Record Card.

**Status:** ready-for-agent

- [ ] An Applicant's Card shows their current Application Status as a `<StatusChip>`, the next action, and a link into the status timeline.
- [ ] My Application gates on the **Applicant Role**, so an administrator inheriting `application.view_status` does not see it.
- [ ] An Applicant with no Application — possible between account creation and the first draft — renders the Card's empty state rather than an error.
- [ ] The Screening Queue Card shows the pending count, the overdue count distinguished from it, and the three oldest Applications with their age, each linking into the screening queue.
- [ ] Aging and the overdue threshold read the **existing** business-day and SLA derivation from Phase 2; the calculation is not duplicated.
- [ ] Overdue is presented as information only — nothing is blocked, changed, or auto-actioned.
- [ ] Screening Queue gates on `application.screen`; a user without it gets 403 from the endpoint, independently of what the client requested.
- [ ] Both endpoints are read-only, return typed domain values with no pre-rendered display strings, and use the standard `{data, meta}` envelope.
- [ ] An empty screening queue renders the shared empty state, not a bare zero.
- [ ] Bilingual EN + AR for all new strings, including the aging and overdue copy.
- [ ] Pest feature tests cover both endpoints: correct payload against the seeded world, 403 without the gate, the empty case, and that an overdue Application is counted as overdue.
- [ ] Vitest + RTL covers each Card's non-trivial rendering, including the empty and overdue states.
- [ ] `openapi.yaml` and the Orval client regenerated and committed; both drift checks clean.
- [ ] No new migration, Permission, Role or module is introduced.
- [ ] `composer gate` and `npm run gate` both green; the Dashboard still passes axe with no serious or critical violations.
