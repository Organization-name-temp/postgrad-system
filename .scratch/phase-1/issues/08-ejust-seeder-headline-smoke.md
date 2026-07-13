# 08 — E-JUST seeder + headline smoke (capstone)

**Spec:** [.scratch/phase-1/spec.md](../spec.md) · FR-PCM-002

**What to build:** The phase capstone — realistic data and the end-to-end proof. A seeder populates a credible bilingual E-JUST catalog, the headline Playwright smoke exercises the browse flow against the real stack, and the Phase 0 catalog **placeholder** is retired now that the real Catalog exists. When this lands, Phase 1 is demoable end-to-end and ready for the manual review gate.

**Blocked by:** 06 — Version history, and 07 — Program tree browse & Course detail (the capstone seeds and demos the complete Phase-1 surface, so both the versioning hooks and the browse UI must be in place first).

**Status:** ready-for-agent

- [ ] A seeder populates a realistic bilingual catalog: all three Faculties; **FoE → Computer Engineering → MSc & PhD fully built** from the FR-PCM-002 example (Preparatory PRE401–404 + DEP501/502, 2 Program Core, ≥3 Research Elective, genuine EN + AR names), with the shared PRE401–404 linked into every Program; other Departments/Programs seeded lighter.
- [ ] The seeded catalog renders correctly in the browse tree — Faculty grouping, expandable, searchable — in both English and Arabic.
- [ ] One Playwright smoke (the phase's headline flow) against the real stack (Postgres + this seeder): sign in → open the catalog → browse the tree → expand to a Course → open its detail → toggle EN↔AR (RTL flip) → `@axe-core` reports no serious/critical violations.
- [ ] The Phase 0 `GET /api/v1/demo/catalog-management` placeholder endpoint and its permission-gated dashboard widget are removed/superseded, and the Phase 0 headline smoke is updated so no gate references the removed placeholder.
- [ ] `composer gate` + `npm run gate` (including the new smoke + axe) are green; the phase's manual review gate can begin.
