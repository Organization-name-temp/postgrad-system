# 05 — Curriculum composition

**Spec:** [.scratch/phase-1/spec.md](../spec.md) · [ADR-0010](../../../docs/adr/0010-catalog-curriculum-model.md)

**What to build:** A manager can compose a **Program's Curriculum** — the set of **Curriculum Entries** that place Courses into the three Categories. From the Program edit page, a `catalog.manage` holder can add a Course to a Program under a chosen Category (Preparatory / Program Core / Research Elective) and remove it. Because Category lives on the Curriculum Entry (not the Course), the **same Course can appear in multiple Programs under different Categories** — a shared Preparatory Course in every Program, or one Program's Program Core Course offered as another Program's Research Elective. Anyone with `catalog.view` can see a Program's Curriculum grouped by Category.

**Blocked by:** 03 — Program management, and 04 — Course management (a Curriculum Entry links a Program to a Course).

**Status:** ready-for-agent

- [ ] A `catalog.manage` holder can add a Course to a Program under one of the three Categories (creating a Curriculum Entry) and remove it, from the Program edit page.
- [ ] The same Course can be placed in multiple Programs under different Categories, verified end-to-end (e.g. a shared Preparatory Course across Programs; a Core Course of one Program as a Research Elective of another).
- [ ] A Curriculum Entry is unique per (Program, Course, Category); the API prevents duplicates (422/409).
- [ ] A `catalog.view` holder can see a Program's Curriculum (its Courses grouped by Category); editing returns 403 without `catalog.manage`.
- [ ] The Program edit page shows three Category sections, each with an autocomplete Course-picker to add and remove entries.
- [ ] `openapi.json` + generated client regenerated and committed.
- [ ] Tests (test-first): Pest feature tests (add/remove, multi-Program placement, uniqueness, RBAC) plus a Vitest test of the Curriculum editor's add/remove behavior. All gates green.
