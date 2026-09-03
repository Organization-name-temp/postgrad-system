# 17 — Headline smoke, axe and phase close-out

**What to build:** The phase's demo, proved end to end and then written down.

**One comprehensive multi-flow smoke**, following the Phase-2 precedent (`admissions.spec.ts`
chained applicant → SA → Dean → interviewer → Student) rather than one smoke per flow. Phase 4.6 has
three headline flows and the Definition of Done asks for one smoke; the answer is to chain them,
because they genuinely are one story:

> Sign in as Student Affairs → open the screening queue → narrow it by Program and search an Arabic
> name **without its hamza** → page through the result → export it and assert the downloaded file's
> bytes → sign in as a Department Head → open **Analytics** → read the GPA Distribution for a
> Program → click the band below 3.0 → land on **exactly those Students, filtered** → export that
> list too.

That single chain exercises the query contract, Arabic normalisation, pagination, export, the Audit
Log entry, the Analytics section, a chart, drill-through, and the promise that a bar and the rows it
stands for are the same query.

**Axe across Analytics in all four combinations** — light/dark × EN/AR — the Phase-0.5 and Phase-4.5
discipline, now including chart content and the "View as table" disclosures.

**The suite is serial by config and must stay that way.** It shares one database and one set of
seeded accounts whose UI language is server-persisted; a parallel run flips another test's locale
mid-flow.

**Close-out** — bring the documents back into line with what was built:

- **ROADMAP §3's Phase 4.6 section** reconciled with reality, its Deferred list corrected to what
  was actually deferred.
- **HANDOFF.md** — the "Where the build is" table, and "Immediate next steps" repointed to Phase 5,
  carrying anything a fresh session cannot learn from the repo. In particular: what the seed now
  costs, and any surprise the migration produced.
- **The four new ADRs re-read against the code.** If an implementation diverged from a decision, the
  ADR is amended or superseded — never left quietly wrong. ADR-0025's zero-outbound-edge claim and
  ADR-0028's batch/single result parity are the two most worth verifying by hand.
- **CONTEXT.md** checked: `Report`, `Export` and the amended `Dashboard Card` should describe what
  shipped.
- **Anything found and not fixed is recorded**, in the ROADMAP's Deferred list, the way Phase 4.5
  recorded deptrac's missing `App` edges rather than silently carrying them.

**Blocked by:** every other ticket (01–16).

**Status:** ready-for-agent

- [ ] One Playwright smoke chains the full flow above, ending on the exported drill-through list
- [ ] The smoke asserts the **downloaded file's contents**, not merely that a download occurred
- [ ] The smoke asserts the drill-through row count equals the chart segment's figure
- [ ] Arabic search without diacritics is exercised inside the smoke, not only in unit tests
- [ ] axe passes on Analytics across light/dark × EN/AR, including charts and their table
      disclosures
- [ ] The Playwright suite remains **serial by config**
- [ ] The whole suite is re-runnable against a fresh seed
- [ ] ROADMAP §3's Phase 4.6 section matches what shipped, with an accurate Deferred list
- [ ] HANDOFF.md's build table and next steps are repointed to Phase 5, including the seed's
      measured cost
- [ ] ADR-0025–0028 re-read against the code; divergences amended or superseded, not left wrong
- [ ] CONTEXT.md's `Report`, `Export` and `Dashboard Card` entries match what shipped
- [ ] Anything found and not fixed is recorded rather than carried silently
- [ ] `composer gate` and `npm run gate` green on a fresh seed
