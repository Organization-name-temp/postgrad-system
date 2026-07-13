# 07 — Program tree browse & Course detail

**Spec:** [.scratch/phase-1/spec.md](../spec.md) · FR-PCM-002 · [ADR-0009](../../../docs/adr/0009-mui-ui-foundation-design-system.md)

**What to build:** The headline browse experience. Anyone signed in (`catalog.view`) can explore the interactive **University → Faculty → Department → Program → Category → Course** tree, grouped by Faculty, expanding and collapsing nodes and searching/filtering to find a Program or Course by name or code. Each Course node shows an active/inactive indicator via the shared `<StatusChip>` (never colour alone). Clicking a Course opens its full detail — code, both names, level, the Lecture/Tutorial/Lab/Total breakdown, owning Department (or "university-wide"), and which Programs and Categories it appears in. The whole thing works in English and Arabic (RTL). Served by a single tree read-model endpoint with client-side filtering.

**Blocked by:** 05 — Curriculum composition (the tree groups Courses under each Program's Categories, and Course detail lists a Course's Program/Category placements). Can run in parallel with 06.

**Status:** ready-for-agent

- [ ] `GET /api/v1/catalog/tree` returns the whole nested structure in one payload, with both names on every node and each Course's active status; requires `catalog.view`.
- [ ] A signed-in user can browse the tree grouped by Faculty, expand/collapse any node, and search/filter it (client-side) to locate a Program/Course by name or code.
- [ ] Each Course node shows an active/inactive indicator via `<StatusChip>` driven by a colocated catalog `statusToChip` map; the status is conveyed by more than colour.
- [ ] Clicking a Course opens its detail: code, both names, level, L/T/Lab/Total, owning Department (or "university-wide"), and the Programs/Categories it belongs to.
- [ ] Tree and detail render correctly in English and Arabic (RTL), with names shown per the active locale.
- [ ] `@mui/x-tree-view` added; `openapi.json` + generated client regenerated and committed.
- [ ] Tests (test-first): Pest feature test for the tree endpoint shape (Faculty grouping, bilingual names, Course status) + `catalog.view` access; Vitest tests for the tree + client-side filter (a query hides non-matching and reveals matching nodes) and the `statusToChip` map. All gates green.
