# 06 — Configuration Cards: Semester & Open Windows, Catalog Summary

**What to build:** Two Cards about how the system is *configured*, rather than about a
queue of work.

A user who manages the academic calendar lands and sees the **active Semester** — which of
its windows are currently open (registration, add/drop, late-add, withdrawal), and the next
date due — linking into the Semester management screen. The seeded world runs several
Semesters at different points in their lifecycle at once, so "which one is live and what is
open right now" is a genuinely useful answer and not a decoration.

A user who manages the catalog lands and sees the counts of **active Programs and Courses**,
linking into the catalog. It answers "is the catalog populated?" without a navigation.

Openness is **always derived** from the Semester's window dates and never stored — the rule
[ADR-0018](../../../docs/adr/0018-calendar-module-semester.md) established and the house
"derived, never stored" convention. This Card reads that derivation.

**These two Cards are sequenced before the Administration ticket because they are members of
the administrator's Card set.** An administrator's dashboard is defined as the Administration
Card plus these two, so ticket 07 cannot land without them. They are Tier 2 by demo value but
must exist by Tier 3.

Both gate on Permissions already in the matrix — `calendar.manage` and `catalog.manage` —
which under the §3.2 matrix belong to Student Affairs / Dean and Department Head / Dean
respectively, and which administrators inherit. Nothing is added to the matrix.

**Blocked by:** 01 — Dashboard foundation + My Academic Record Card.

**Status:** ready-for-agent

- [ ] The Calendar Card names the active Semester, states which of its windows are currently open, and gives the next date due, linking into Semester management.
- [ ] Window openness is read from the existing derived period state, never from a stored flag.
- [ ] When no Semester is currently active, the Card renders a meaningful empty state rather than an error or a blank.
- [ ] The Calendar Card gates on `calendar.manage`; a user without it gets 403 from the endpoint.
- [ ] The Catalog Card shows active Program and Course counts and links into the catalog.
- [ ] The Catalog Card gates on `catalog.manage`; a user without it gets 403 from the endpoint.
- [ ] Counts are computed with aggregate queries, not by materialising collections and counting them in PHP.
- [ ] Both Cards are registered as members of the **admin Card set**, so ticket 07 can compose them without further change.
- [ ] Both endpoints are read-only, return typed domain values with no pre-rendered display strings, and use the standard `{data, meta}` envelope.
- [ ] Bilingual EN + AR for all new strings, including every window name.
- [ ] Pest feature tests cover both endpoints: payload against the seeded world, 403 without the gate, and the no-active-Semester case.
- [ ] Vitest + RTL covers each Card's non-trivial rendering.
- [ ] `openapi.yaml` and the Orval client regenerated and committed; both drift checks clean.
- [ ] No new migration, Permission, Role or module is introduced.
- [ ] `composer gate` and `npm run gate` both green; the Dashboard still passes axe with no serious or critical violations.
