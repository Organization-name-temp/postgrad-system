# 06 — Version history for Programs & Courses

**Spec:** [.scratch/phase-1/spec.md](../spec.md) · [ADR-0011](../../../docs/adr/0011-catalog-version-snapshots.md) · FR-PCM-001.3, FR-PCM-003.4

**What to build:** Every change to a Program or a Course is recorded as history. Each create / edit / activate / deactivate of a Program or Course — **including a Curriculum edit** — appends an immutable snapshot of the full aggregate (a Program's snapshot includes its Curriculum and its per-Category requirements; a Course's snapshot includes its fields). A manager can view the read-only version history of any Program or Course. This history is Catalog-owned domain content, deliberately **separate from the Identity Audit Log** (the security trail): it never flows through `AuditLogger`.

**Blocked by:** 05 — Curriculum composition (so Program snapshots capture the Curriculum, and Program + Course mutations all exist to hook).

**Status:** ready-for-agent

- [ ] Each create/edit/activate/deactivate of a Program or Course appends a snapshot with a `version_no` that increments per subject; the snapshot captures the full aggregate.
- [ ] A Program's Curriculum edit (adding/removing an entry) produces a new Program version whose snapshot reflects the changed Curriculum.
- [ ] A manager can retrieve the read-only version history of a Program or Course via `/api/v1`; the history cannot be edited or deleted through the API.
- [ ] Version history is Catalog-owned and does not pass through the Identity `AuditLogger` (distinct from the Audit Log).
- [ ] UI: a read-only version-history view/panel for a Program and for a Course showing the sequence of versions.
- [ ] `openapi.json` + generated client regenerated and committed.
- [ ] Tests (test-first): Pest feature tests (a version is created and `version_no` increments on each mutation type incl. a Curriculum edit; history is read-only; it is separate from the audit log). All gates green.
