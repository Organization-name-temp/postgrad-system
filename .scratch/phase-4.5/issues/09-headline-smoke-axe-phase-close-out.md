# 09 — Headline smoke, axe, and phase close-out

**What to build:** The phase's capstone — the one test that proves the whole design holds
against a real browser, plus the documentation that closes Phase 4.5 out.

A new `dashboard.spec.ts` signs in as five accounts in turn and asserts, for each, **exactly
which Cards render and which do not**. Absence is asserted as firmly as presence, because
the risk this phase carries is not "does a count render" — it is "**does the right person see
the right Cards**", which is a security-adjacent property that nothing else in the suite
covers end-to-end.

Of the five, **`multi@` is the single most important assertion in the phase.** It is the one
account holding two Roles, and its dashboard must show the advisee queue *and* the ungraded
Sheets on one page, **with no role switcher anywhere on screen**. That one assertion is the
entire composition rule made visible; if it passes, ADR-0024 is real.

This is also the only seam where the **server gate and the client composition are proved to
agree**. The component tests from earlier tickets mock the auth hook and therefore test the
mirror; the Pest tests prove the authority. Only a real login exercises both at once —
[ADR-0014](../../../docs/adr/0014-client-validation-mirrors-server-rules.md) makes the client
an explicit mirror of server rules, and verifying a mirror means testing both faces.

axe runs on the Dashboard across **light/dark × EN/AR**, not one combination, because the
dashboard is now the most-viewed screen in the application and the one every user meets first.

The suite is **serial by configuration and must stay that way** — it shares one database and
one set of seeded accounts whose UI language is server-persisted, so a parallel run would flip
another test's locale mid-flow. A five-login spec is correct, just budget the wall-clock.

Close-out completes the documentation set ticket 01 began. The ROADMAP gains its Phase 4.5
section in the established §3 format, and its Phase 7 section gains the note that three of its
eight reports — Academic Standing, Graduation Readiness and Termination — depend on read
models Phase 5 has not built, which is the fact that produced this phase in the first place.
HANDOFF.md is stale on two points and must be corrected: it records Phase 4 as awaiting its
review gate on a branch though it merged to `main`, and it lists Phase 5 as the immediate next
step.

**Blocked by:** 02 — Seeder; 03 — Admissions Cards; 04 — Enrollment Cards; 05 — Grading
Cards; 07 — Administration Card; 08 — Remaining Cards.

**Status:** ready-for-agent

- [ ] `dashboard.spec.ts` signs in as a Student persona, `r03@`, `r04@`, `multi@` and `r09@`, asserting each account's Card set — presence **and** absence.
- [ ] The `multi@` assertion proves the union: advisee and instructor Cards on one page, and **no role-switcher control present anywhere in the UI**.
- [ ] The `r09@` assertion proves the admin set: the Administration and configuration Cards, and explicitly not the queue or persona Cards.
- [ ] The Student assertion proves a persona Card renders with a real CGPA value, not a placeholder.
- [ ] The spec runs against a fresh seed and is re-runnable — it depends on ticket 02's seeded accounts, not on data it creates itself.
- [ ] axe runs on the Dashboard across light/dark × EN/AR with no serious or critical violations in any combination.
- [ ] The Playwright suite remains **serial by configuration**; nothing in this ticket makes it parallel.
- [ ] Every existing Phase 0–4 smoke still passes unchanged, including the Phase-0 headline smoke that asserts the dashboard heading across the Dashboard's move and rewrite.
- [ ] ROADMAP.md gains a §3 Phase 4.5 section in the established format, recording what the phase built and its Deferred list.
- [ ] ROADMAP.md's Phase 7 section records that Academic Standing, Graduation Readiness and Termination depend on Phase 5's read models, and that Phase 7 keeps its position after Phases 5 and 6.
- [ ] HANDOFF.md is corrected: Phase 4 is merged to `main`, and the immediate next steps reflect Phase 4.5 rather than Phase 5.
- [ ] The full inventory of twelve Cards is present and each one is reachable by at least one seeded account.
- [ ] No new migration, Permission, Role or module was introduced across the whole phase.
- [ ] `composer gate` and `npm run gate` both green on a fresh seed, with the complete Playwright suite passing.
