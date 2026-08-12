# 07 — Administration Card + the admin Card set

**What to build:** A System Administrator signs in and lands on a page **about running the
system** — counts of Users and role assignments, and recent Audit Log activity — alongside
the two configuration Cards from ticket 06, and **nothing else**. No screening queue, no
advisee requests, no review queue, no persona Cards.

This ticket closes the one deliberate exception in the composition rule. R09 and R10 hold
**every** Permission in the matrix, which means a naive permission-union dashboard would
render every Card in the system stacked on one page. Holding every Permission is an
*authorization fact*, not a description of an administrator's job: it exists so an admin can
step into any surface when needed, not so their home screen becomes ten other people's work
queues.

Ticket 01 built the admin branch in the registry and left the admin set empty, so an
administrator has been landing on a correct but deliberately blank dashboard throughout
tickets 03–06. This ticket fills it. Nothing built in tickets 03–06 needs revisiting — the
Cards those tickets registered were never admin-set members, which is exactly why the branch
shipped first.

The Audit Log summary is a **read of existing append-only entries**, surfaced as recent
activity with a link. It is not the Phase 7 Audit Log *Report*: a Card is fixed, scoped and
summary, while a Report is filterable, parameterised and cross-cutting. The
[CONTEXT.md](../../../CONTEXT.md) entries from ticket 01 draw that line, and this Card must
stay on the Card side of it — no filters, no date range, no export.

The Administration Card gates on the **R09/R10 Role**, not on a Permission. This is the same
role-gated pattern the admin assignment surfaces have used since Phase 2, and it reuses the
existing admin-role helper rather than introducing a gate mechanism.

**Blocked by:** 01 — Dashboard foundation; 06 — Configuration Cards (its two Cards are
members of the admin set).

**Status:** ready-for-agent

- [ ] The Administration Card shows User and role-assignment counts and recent Audit Log activity, linking into the administration surfaces.
- [ ] The Card gates on holding the **R09 or R10 Role**, reusing the existing admin-role helper. No new Permission is added to the matrix.
- [ ] An administrator's dashboard renders the Administration Card plus the two configuration Cards from ticket 06, and **no other Card** — not the screening queue, advisee requests, review queue, or any persona Card.
- [ ] A non-administrator holding `catalog.manage` or `calendar.manage` still sees those configuration Cards; admin-set membership adds a Card to the admin dashboard without removing it from anyone else's.
- [ ] The Audit Log summary is a bounded recent-activity read with no filtering, date range, or export — it is a Card, not the Phase 7 Report.
- [ ] The endpoint is read-only, returns typed domain values with no pre-rendered display strings, and uses the standard `{data, meta}` envelope.
- [ ] The endpoint is authorized on the server for the R09/R10 Role; every other role gets 403.
- [ ] An empty Audit Log renders the shared empty state rather than an error.
- [ ] Bilingual EN + AR for all new strings, including audit action labels rendered from typed values rather than server-supplied text.
- [ ] Pest feature tests cover the endpoint's payload, the 403 for every non-admin role, and the empty-log case.
- [ ] Vitest + RTL asserts the administrator's Card set is **exactly** the Administration Card plus the two configuration Cards — asserting the absence of the queue and persona Cards as firmly as the presence of these three.
- [ ] `openapi.yaml` and the Orval client regenerated and committed; both drift checks clean.
- [ ] No new migration, Permission, Role or module is introduced.
- [ ] `composer gate` and `npm run gate` both green; the landing page still passes axe with no serious or critical violations.
