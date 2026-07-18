# 01 — Admissions module + Admission Cycle management

**What to build:** The new `Admissions` module dropped into the skeleton, and the **Admission Cycle** — the windowed intake object every Application will hang off. Student Affairs can create, edit, activate/deactivate, and set the open/close window of a Fall/Spring Admission Cycle (e.g. "Fall 2026") through a bilingual admin screen; whether a cycle is currently *open* is derived, not stored. This is the module tracer: it proves the new seam end-to-end before any application exists.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] New `Admissions` module (`Public`/`Internal` split, auto-discovered service provider, `/api/v1` routes), following the Identity/Catalog shape.
- [ ] `deptrac` gains an `Admissions` layer: `Admissions → Identity\Public` allowed, `Admissions\Internal` sealed from other modules, 0 violations; `composer gate` green.
- [ ] `AdmissionCycle` has `intake_term` (Fall/Spring enum), `year`, bilingual `name_en`/`name_ar`, `opens_at`/`closes_at`, `is_active`; `(intake_term, year)` is unique.
- [ ] Management endpoints (list, create, edit, activate/deactivate) require `calendar.manage` (Student Affairs / Dean); a public open-cycle lookup returns the currently-open cycle(s) with no auth (for the later registration screen).
- [ ] "Open" is derived (`is_active` AND `opens_at ≤ now ≤ closes_at`) — never a stored status column.
- [ ] A bilingual admin screen (new `features/admin`, nav gated on `calendar.manage`) lists cycles and creates/edits/activates/deactivates them; both EN + AR names required.
- [ ] Scribe regenerates `openapi.yaml`, Orval regenerates the typed client, drift-clean. Pest feature tests cover cycle CRUD, RBAC (manage vs view), and `(intake_term, year)` uniqueness. All gates green.
