# 12 — Richer Dashboard Cards

**What to build:** Phase 4.5's twelve Cards each answer "how many are waiting for me". None answers
"is that normal", "is it getting worse", or "how did we do against last term". This ticket gives
each Card the numbers its role actually cares about.

- A **Student** sees their CGPA against the 3.0 graduation minimum and its trend across Semesters,
  not just the current figure.
- An **Advisor** sees how many advisees sit below the threshold alongside their pending requests —
  the part of the job that is not a queue.
- An **Instructor** sees the grade spread of the Offering they most recently finalized.
- A **Student Affairs officer** sees this Cycle's applications against the last, so a count has
  context.
- A **Department Head** sees their review queue's age, not only its size.

**The rule, from the ADR-0024 amendment, is mechanical and must not be softened:** a Card may carry
headline figures, a term-over-term delta, and **at most one chart — over data already scoped to the
acting user**. **A Card takes no parameter.** The moment a Card would need one, it has become a
Report and belongs in Analytics, where it can be gated, scoped and drilled into properly. This test
exists because with Analytics now next door, every Card acquires a reason to grow, and the Card/
Report line erodes one Card at a time unless the rule is checkable in review.

Two things from ADR-0024 are unchanged and still load-bearing:

- **A Card introduces no domain logic of its own.** A figure on a Card is read from the derivation
  that already owns it — now often a ticket-04 batch method — never recomputed and never cached.
- **Card payloads carry typed domain values only**, never pre-rendered display strings, so the SPA
  keeps rendering bilingually. The single recorded exception to that rule is the export endpoint,
  which has no client to render for; a Card is not an exception.

The composition rule is untouched: still the union of a user's Cards, still no role switcher, still
an explicit administration set.

**Blocked by:** 04 — Aggregate facades + parity test; 05 — Chart primitive + View as table.

**Status:** ready-for-agent

- [ ] Cards gain headline figures and term-over-term deltas where the comparison is meaningful
- [ ] Cards that carry a chart carry **one**, over data scoped to the acting user, using the
      `shared/ui` primitive with its "View as table" disclosure
- [ ] **No Card takes a parameter** — asserted in review and by the absence of any parameter in
      every Card endpoint's signature
- [ ] Every figure is read from an existing derivation or a ticket-04 batch method; nothing is
      recomputed or cached
- [ ] Card payloads remain typed domain values only, with no pre-rendered display strings
- [ ] The Dashboard remains a permission-composed union with no role switcher, and the
      administration set is unchanged
- [ ] Cards still fetch and fail independently; one Card erroring leaves the grid intact
- [ ] `DashboardPage` tests still assert which Cards render **and which do not**, including the
      multi-role union and the administrator set
- [ ] axe passes on the landing page across light/dark × EN/AR with the new chart content
- [ ] `composer gate` and `npm run gate` green
