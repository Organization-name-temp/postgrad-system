# 03 — Enrollment module + Course Offering CRUD

**What to build:** "What actually runs this semester." A new `Enrollment` domain module, and within it the **Course Offering** — one Catalog Course taught in one Semester, carrying its Instructor, an optional seat capacity, and weekly meeting times ([ADR-0017](../../../docs/adr/0017-offering-based-registration.md)). An administrator holding `calendar.manage` populates a Semester with Offerings; this is the record Phase 4 grading's Instructor→Course link is born from. Builds the `CourseCatalog` cross-module read facade as its first consumer.

**Blocked by:** 01 (Semester must exist), 02 (the course read DTO includes research_type).

**Status:** ready-for-agent

- [ ] New `Enrollment` module (Public/Internal, auto-discovered provider). `Enrollment\Public` ships **empty (routes only)** — Phase 4 builds the roster surface against grading. deptrac gains `Enrollment_Internal → {Identity, Catalog, Calendar, Notifications}\Public` and `App → Enrollment\Public`; every Internal stays sealed.
- [ ] Catalog grows its long-deferred cross-module read facade (ADR-0012): a `CourseCatalog` reader (by id / ids → a Course summary DTO with code, bilingual names, level, total CH, research type, active flag). `CourseCategory` and `CourseLevel` move from Catalog `Internal` to `Public`. All references are opaque ids + DTOs, no FK.
- [ ] `CourseOffering`: belongs to a Semester (Calendar) + references a Catalog course id (no FK); an instructor (users ref), an optional integer capacity, and ≥1 weekly meeting rows (weekday + start/end). Unique (semester, course) enforced now (relaxable to sections later). An Offering's own meeting rows must not self-overlap (422) — via a single interval-overlap support seam. Deletable only while unreferenced by enrollments/sessions.
- [ ] A new instructor-list endpoint returns R04 users for the picker (the Phase 2 advisors-list precedent, via `Identity\Public::usersByRole`).
- [ ] `/api/v1` Offering CRUD under `ManageAcademicCalendar`. Scribe `@urlParam` examples (snake_case nested params); openapi + Orval regenerated, drift-clean.
- [ ] `features/admin` per-Semester Offerings screen: list; create/edit with a Catalog course picker, instructor picker, capacity, and a weekly meeting-slot editor (plain time inputs, no new deps). EN+AR.
- [ ] **Tests:** Pest API-feature for Offering CRUD + unique-per-semester + self-overlap rejection + delete-only-when-unreferenced + RBAC + the CourseCatalog facade reads; targeted unit for the interval-overlap seam. Gates green.
