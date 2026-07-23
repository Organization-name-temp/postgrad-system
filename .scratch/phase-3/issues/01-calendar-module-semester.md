# 01 — Calendar module + Semester CRUD

**What to build:** The single authority for the academic term. A new supporting `Calendar` module (Public/Internal, auto-discovered provider, per [ADR-0018](../../../docs/adr/0018-calendar-module-semester.md)) owning the **Semester** — a Term (Fall/Spring) of one year carrying its five period dates. An administrator holding `calendar.manage` (SA / Dean / SysAdmin) can create and edit Semesters and see them listed with a derived state; nobody can delete one or create a duplicate. This is the foundation every later Phase 3 ticket reads from.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] New `Calendar` module with the `Public`/`Internal` seam and a thin auto-discovered service provider; deptrac gains `Calendar_Public`/`Calendar_Internal` layers and `App → Calendar\Public`. `Calendar` depends on no other domain module.
- [ ] `Semester`: a `Term` enum {Fall, Spring} living in `Calendar\Public`, plus year (unique together), bilingual display name derivable, and five typed date columns — start/end, registration opens/closes, add/drop end, late-add end, withdrawal deadline.
- [ ] Cross-field ordering invariant enforced on write (422 on violation): registration opens before semester start; registration closes ≤ add/drop end; add/drop end < late-add end < withdrawal deadline ≤ semester end.
- [ ] Duplicate (same Term + year) rejected (422/409). Semesters are never hard-deleted (no delete verb → 405); edit updates dates in place.
- [ ] "Registration open" / "running" / current-period are **derived from the dates**, never stored flags (the `AdmissionCycle::isOpen()` precedent). A `Calendar\Public` read facade exposes the current/open Semester and its period answers for later consumers.
- [ ] `/api/v1` CRUD under `ManageAcademicCalendar`; reads available to authenticated users who need the calendar. Scribe `@urlParam` examples on model-bound routes; openapi + Orval regenerated, drift-clean.
- [ ] `features/admin` Semesters screen (list with a derived-state `StatusChip`, create/edit form with the five date fields) in the calendar nav section beside Cycles. EN+AR.
- [ ] **Tests:** Pest API-feature for CRUD + ordering invariant + duplicate rejection + no-delete + RBAC; a targeted unit test for the date-ordering invariant and the derived-state/period logic. Vitest only if a component is non-trivial. Gates green.
