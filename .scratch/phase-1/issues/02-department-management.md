# 02 — Department management

**Spec:** [.scratch/phase-1/spec.md](../spec.md)

**What to build:** A manager can maintain **Departments** under a Faculty. A `catalog.manage` holder can create a Department beneath an existing Faculty with a code and English + Arabic names, edit it, and activate/deactivate it; anyone with `catalog.view` can see Departments associated with their owning Faculty. Reuses the module, API conventions, and admin-screen patterns established in ticket 01.

**Blocked by:** 01 — Catalog module + Faculty management (needs the module, the Faculty entity to nest under, and the admin/API patterns).

**Status:** ready-for-agent

- [ ] A `catalog.manage` holder can create a Department under a chosen existing Faculty (Faculty reference + code + `name_en` + `name_ar`), edit it, and activate/deactivate it; both names required, code unique (validation surfaced as 422/409).
- [ ] A `catalog.view` holder can list and read Departments (each associated with its Faculty); mutations return 403 without `catalog.manage`.
- [ ] Departments are deactivated, never hard-deleted.
- [ ] The admin UI lets a manager pick the owning Faculty from existing Faculties and manage Departments, with EN + AR name inputs.
- [ ] `openapi.json` + generated client regenerated and committed.
- [ ] Tests (test-first): Pest feature tests for the Department endpoints incl. RBAC allow/deny and validation. `composer gate` + `npm run gate` green.
