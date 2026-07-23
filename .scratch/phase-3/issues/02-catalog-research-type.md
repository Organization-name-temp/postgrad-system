# 02 — Catalog: Research Type on Course

**What to build:** The Phase 1 deferral, landed. A Catalog manager can set a Course's **Research Type** — PBL, Seminar, or neither — when creating or editing it, so that level-700 research courses can later be matched to the right degree at registration (BR-25). A small, standalone Catalog vertical slice.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] Catalog `Course` gains a nullable `research_type` enum {PBL, Seminar} column (+ model cast, fillable, factory, resource). No validation coupling it to level — a Course may carry it or not; the Phase 3 classifier simply won't match a level-700 Research Course slot without it.
- [ ] The value is included in the Course create/edit full-replace payload and rendered on the Course resource, under the existing `catalog.manage` (write) / `catalog.view` (read) guards.
- [ ] Setting or changing `research_type` is captured by the existing `CatalogVersionRecorder` full-aggregate snapshot, exactly like every other Course field (no new versioning path).
- [ ] Frontend `features/catalog/courses` form gains a Research Type select (PBL / Seminar / none), EN+AR labels; the value shows on course detail. No new deps.
- [ ] Scribe/openapi + Orval regenerated, drift-clean (mind the populated-`@response`-example rule).
- [ ] **Tests:** Pest API-feature asserting research_type round-trips on create/edit and appears in a new version snapshot; RBAC unchanged. Vitest only if the form change is non-trivial. Gates green.
