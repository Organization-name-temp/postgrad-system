# 03 — Program management + per-Category credit requirements

**Spec:** [.scratch/phase-1/spec.md](../spec.md) · [ADR-0010](../../../docs/adr/0010-catalog-curriculum-model.md) · BR-13…18

**What to build:** A manager can maintain **Programs** under a Department. A `catalog.manage` holder can create a Program with a degree level (MSc/PhD), code, English + Arabic names, and its required credit hours **per Category** (Preparatory / Program Core / Research Elective), defaulting to 6 / 6 / 9 (total 21) but editable; they can edit it and activate/deactivate it. Deactivating a Program leaves it in place with no cascading effect (BR-18). Anyone with `catalog.view` can view Programs including their per-Category required CH.

**Blocked by:** 02 — Department management (a Program lives under a Department).

**Status:** ready-for-agent

- [ ] A `catalog.manage` holder can create a Program under a Department with `degree_level` ∈ {MSc, PhD}, code, `name_en`, `name_ar`, and per-Category required CH (defaults 6/6/9, total 21, editable); edit + activate/deactivate supported.
- [ ] Both names required; code unique; `degree_level` constrained to the enum (invalid → 422).
- [ ] Deactivating a Program does not delete it or cascade — it stays readable and simply not active (BR-18).
- [ ] A `catalog.view` holder can view Programs and their per-Category required CH; mutations return 403 without `catalog.manage`.
- [ ] Admin UI: a Program create/edit form (Department picker, degree-level select, per-Category CH fields) plus a list, with EN + AR inputs.
- [ ] `openapi.json` + generated client regenerated and committed.
- [ ] Tests (test-first): the targeted **unit** test for the fixed three `CourseCategory` values and the default 6/6/9 split (pure domain data); Pest feature tests (defaults applied, degree-level validation, RBAC allow/deny). All gates green.
