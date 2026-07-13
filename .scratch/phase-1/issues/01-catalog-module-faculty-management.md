# 01 — Catalog module + Faculty management (tracer)

**Spec:** [.scratch/phase-1/spec.md](../spec.md) · ADRs [0002](../../../docs/adr/0002-modular-monolith-seams.md)/[0007](../../../docs/adr/0007-hand-rolled-psr4-modules.md)/[0008](../../../docs/adr/0008-rbac-enums-static-matrix.md)/[0010](../../../docs/adr/0010-catalog-curriculum-model.md)

**What to build:** Stand up the new **Catalog** module inside the modular monolith and prove the first enforced cross-module boundary, then deliver the thinnest complete slice through it: a manager can maintain **Faculties**. A holder of `catalog.manage` (Department Head, Dean, or admin) can create a Faculty with a code and English + Arabic names, rename it, and activate/deactivate it from a new admin screen; anyone signed in (`catalog.view`) can list Faculties. This ticket also establishes everything the later Catalog tickets copy: the module's `Public`/`Internal` split, the Catalog API conventions, the OpenAPI→Orval extension, the `catalog` frontend feature (+ its i18n namespace and boundaries entry), and the deptrac Catalog layer.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] A `Catalog` module exists following the Identity shape (`Public/` = the only cross-module surface; `Internal/` = models, actions, controllers, policies, migrations, enums), auto-discovered, with its routes served under `/api/v1`.
- [ ] deptrac gains a `Catalog` layer: `Catalog → Identity\Public` is allowed (it consumes the `Permission`/`Role` enums and Gates), `Catalog\Internal` is sealed from other modules; the boundary is asserted and `composer gate` (deptrac) is green.
- [ ] A `catalog.manage` holder can create a Faculty (code + `name_en` + `name_ar`), edit it, and activate/deactivate it via `/api/v1`; both names are required (422 if missing) and the code is unique (422/409 on duplicate).
- [ ] A `catalog.view` holder without `catalog.manage` can list and read Faculties but gets 403 on every mutation.
- [ ] Faculties are deactivated, never hard-deleted (no destructive delete path).
- [ ] A new `features/catalog` frontend feature (added to the `eslint-plugin-boundaries` ruleset) provides a `catalog.manage`-gated admin screen to list Faculties and create/edit/activate/deactivate them with EN + AR inputs, rendering through the Orval-generated client.
- [ ] `openapi.json` and the generated client are regenerated and committed; both gates fail on drift.
- [ ] Tests (test-first): Pest feature tests covering the Faculty endpoints, the representative `catalog.view`-allow / mutate-deny (403), and validation; the boundary assertion; a Vitest test only if the admin form carries non-trivial logic. `composer gate` + `npm run gate` green.
