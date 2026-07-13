# 04 — Course management (credit-hour integrity)

**Spec:** [.scratch/phase-1/spec.md](../spec.md) · [ADR-0010](../../../docs/adr/0010-catalog-curriculum-model.md) · FR-PCM-003, BR-26

**What to build:** A manager can maintain **Courses** — standalone teachable units. A `catalog.manage` holder can create a Course with a unique code, English + Arabic names, a level (400/500/600/700), and a Lecture / Tutorial / Lab / Total credit-hour breakdown; they can edit it and activate/deactivate it. The system guarantees credit-hour integrity: it rejects a Course whose `Lecture + Tutorial + Lab ≠ Total` and a duplicate code. A Course is owned by a Department **or** marked university-wide (no Department — the shared Preparatory language/culture Courses). Courses are deactivated, never hard-deleted. Anyone with `catalog.view` can view Courses. A Course carries **no Category of its own** (Category is assigned per Program in ticket 05).

**Blocked by:** 02 — Department management (a Course's optional owner is a Department; needs the Department picker + "university-wide" option). Can run in parallel with 03.

**Status:** ready-for-agent

- [ ] A `catalog.manage` holder can create a Course with a unique `code`, `name_en`, `name_ar`, `level` ∈ {400,500,600,700}, and `lecture_ch`/`tutorial_ch`/`lab_ch`/`total_ch`; edit + activate/deactivate supported.
- [ ] The API rejects `lecture + tutorial + lab ≠ total` (422) and a duplicate code (422/409); both names required.
- [ ] A Course may be owned by a Department **or** marked university-wide (no Department); both round-trip, and the detail/UI renders the university-wide case explicitly.
- [ ] Courses are deactivated, never hard-deleted (forward-compatible with "no delete once enrollment history exists", FR-PCM-003.3).
- [ ] A `catalog.view` holder can list and read Courses; mutations return 403 without `catalog.manage`.
- [ ] Admin UI: a Course form (code, EN + AR names, level, L/T/Lab/Total, Department-or-university-wide) plus a list.
- [ ] `openapi.json` + generated client regenerated and committed.
- [ ] Tests (test-first): the targeted **unit** test for the credit-hour breakdown invariant (`L+T+Lab = Total`); Pest feature tests (CH-sum → 422, duplicate code, nullable/university-wide owner round-trip, deactivate-only, RBAC allow/deny). All gates green.
