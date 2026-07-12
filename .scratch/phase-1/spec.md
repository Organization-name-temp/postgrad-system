# Spec — Phase 1: Catalog (Program & Course Management)

**Status:** ready-for-agent
**Feature slug:** phase-1
**Phase:** 1 of the [Build Roadmap](../../docs/ROADMAP.md) (SRS §4.3 — Program & Course Management)
**Runtime:** Laravel 12 / PHP 8.4 ([ADR-0006](../../docs/adr/0006-runtime-baseline-laravel-12-php-84.md))
**Sources:** [ROADMAP §Phase 1](../../docs/ROADMAP.md) · [SRS §4.3](../../docs/SRS.md) (FR-PCM-001/002/003) · [business-requirements](../../docs/business-requirements.md) (BR-13…18, BR-23/24/26) · [architecture.md](../../docs/architecture.md) · [CONTEXT.md](../../CONTEXT.md) · ADRs [0002](../../docs/adr/0002-modular-monolith-seams.md)/[0005](../../docs/adr/0005-localhost-learning-build.md)/[0007](../../docs/adr/0007-hand-rolled-psr4-modules.md)/[0008](../../docs/adr/0008-rbac-enums-static-matrix.md)/[0009](../../docs/adr/0009-mui-ui-foundation-design-system.md)/**[0010](../../docs/adr/0010-catalog-curriculum-model.md)**/**[0011](../../docs/adr/0011-catalog-version-snapshots.md)**

---

## Problem Statement

The PRS walking skeleton (Phase 0) can log people in and enforce who may do what, and Phase 0.5 gave it a themed, bilingual shell — but the system knows nothing about **what E-JUST actually teaches**. There are no Faculties, Departments, Programs, or Courses; nothing an Applicant can browse to decide where to apply, nothing a Student will later register against, nothing a Grade can attach to. Every downstream phase (Admissions, Enrollment, Grading…) depends on this academic catalog existing first.

From the point of view of the people who touch the system:

- **An Applicant** cannot see what postgraduate Programs E-JUST offers, how they are grouped by Faculty, or what a Program's Courses and credit-hour structure look like — so they cannot make an informed choice about where to apply.
- **A Student, Advisor, or Instructor** has no authoritative Course Catalog to consult — no way to look up a Course's code, credit hours, level, or which Programs it belongs to.
- **A Department Head or Dean** has no way to define or maintain their Programs and Courses — to set a Program's Curriculum, keep credit-hour rules correct, retire an outdated Program without disrupting anyone, or see how a Program has changed over time.
- **A developer** has no Catalog module to build the rest of the domain on, and no realistic academic data to develop and demo against.

## Solution

Build the **Catalog** module — the first real domain module dropped into the Phase 0 skeleton — owning the academic hierarchy and exposing it through `/api/v1`, with a bilingual MUI front end. Anyone signed in can **browse an interactive tree** (University → Faculty → Department → Program → Category → Course), search/filter it, and open any Course to see its full detail; the tree is grouped by Faculty (FoE, BAS, CSIT) exactly as the SRS pictures it. Department Heads and Deans can **create, edit, activate, and deactivate** Faculties, Departments, Programs, and Courses, **compose a Program's Curriculum** (placing Courses into the three Categories), and **see the version history** of every Program and Course change. The rules that make the catalog trustworthy are enforced server-side: a Course's `Lecture + Tutorial + Lab` must equal its `Total`, codes are unique, both language names are required, Programs and Courses are never hard-deleted (only deactivated), and every change is snapshotted.

The model deliberately reflects two domain truths sharpened during design ([ADR-0010](../../docs/adr/0010-catalog-curriculum-model.md)): a **Course carries no Category of its own** — its Category is assigned *per Program* by a **Curriculum Entry**, so the shared university Preparatory Courses live in every Program and a Program's own Core Course can be an external elective for another; and a **Course is owned by a Department or is university-wide** (nullable owner). Version history is recorded as **append-only aggregate snapshots** owned by Catalog, distinct from the Identity Audit Log ([ADR-0011](../../docs/adr/0011-catalog-version-snapshots.md)).

A realistic E-JUST **seeder** populates the tree — FoE → Computer Engineering → MSc & PhD fully built out from the FR-PCM-002 example, other Faculties lighter — so the demo and all later development have credible data.

## User Stories

**Browsing the catalog (any signed-in user — `catalog.view`)**

1. As an Applicant, I want to see all postgraduate Programs grouped by Faculty (FoE, BAS, CSIT) in a tree, so that I can decide which Program to apply to.
2. As any viewer, I want the tree to show the full hierarchy University → Faculty → Department → Program → Category → Course, so that I understand how a Program is structured.
3. As any viewer, I want to expand and collapse any node in the tree, so that I can focus on the part of the catalog I care about.
4. As any viewer, I want each Program to show its three Course Categories with their required credit hours (Preparatory 6 / Program Core 6 / Research Elective 9), so that I can see the shape of the degree at a glance.
5. As any viewer, I want to search and filter the tree by Course or Program name/code, so that I can find a specific item without hand-expanding everything.
6. As any viewer, I want each Course node to carry a visual status indicator (active / inactive) that never relies on colour alone, so that I can tell retired Courses from current ones accessibly.
7. As any viewer, I want to click a Course and see its full detail — code, both names, level, the Lecture/Tutorial/Lab/Total credit-hour breakdown, owning Department (or "university-wide"), and which Programs/Categories it appears in — so that I have the complete picture of that Course.
8. As an Arabic-speaking viewer, I want every Faculty/Department/Program/Course name shown in Arabic with the layout in RTL, so that I can read the catalog in my language.
9. As any viewer, I want to switch the whole catalog between English and Arabic at runtime, so that I can read names in either language without reloading.
10. As a Student or Advisor, I want to look up any Course in the catalog regardless of Program, so that I can check its details before a future registration decision.

**Managing Programs (Department Head / Dean / Admin — `catalog.manage`)**

11. As a Department Head, I want to create a Program with its code, English and Arabic names, degree level (MSc/PhD), and Department, so that a new degree offering exists in the system.
12. As a Department Head, I want each Program to carry its required credit hours per Category, defaulting to 6/6/9 (total 21) but editable, so that the bylaw structure is captured as data rather than assumed.
13. As a Dean, I want to edit a Program's attributes, so that I can correct or update a degree offering.
14. As a Dean, I want to deactivate a Program that is no longer offered, so that it stops appearing as available without being erased.
15. As a Department Head, I want deactivating a Program to have no effect on students already enrolled in it, so that retiring a Program never disrupts current students (BR-18).
16. As a Department Head, I want to reactivate a Program, so that I can bring a paused offering back.

**Composing a Program's Curriculum (`catalog.manage`)**

17. As a Department Head, I want to add a Course to a Program under one of the three Categories (a Curriculum Entry), so that the Program's structure lists the right Courses.
18. As a Department Head, I want to remove a Course from a Program's Curriculum, so that I can correct the Program's structure.
19. As a Department Head, I want to place the same Course in more than one Program (e.g. a shared Preparatory Course in every Program, or one Program's Core Course offered as another Program's Research Elective), so that Courses are reused faithfully instead of duplicated.
20. As a Department Head, I want to edit a Program's Curriculum from the Program's own edit page, so that structure and attributes are managed in one place.
21. As a Department Head, I want the Curriculum organised into the three Category sections, so that adding a Course means choosing its role in this Program explicitly.

**Managing Courses (`catalog.manage`)**

22. As a Department Head, I want to create a Course with a code, English and Arabic names, level, and a Lecture/Tutorial/Lab/Total credit-hour breakdown, so that a teachable unit exists to place into Programs.
23. As a Department Head, I want to mark a Course as owned by a Department or as university-wide (no Department), so that the shared Preparatory language/culture Courses are modelled honestly.
24. As a Department Head, I want the system to reject a Course whose Lecture + Tutorial + Lab hours don't equal its Total, so that credit-hour data is always internally consistent (FR-PCM-003.2).
25. As a Department Head, I want the system to reject a duplicate Course code, so that codes stay unique and unambiguous.
26. As a Dean, I want to edit and deactivate/reactivate a Course, so that I can maintain the Course inventory over time.
27. As a Department Head, I want Courses to be deactivated rather than deleted (and never hard-deleted once they have enrollment history), so that historical academic records are never orphaned (FR-PCM-003.3).

**Managing Faculties & Departments (`catalog.manage`)**

28. As an administrator, I want to create and edit Faculties (code + bilingual name), so that the top-level academic divisions exist.
29. As an administrator, I want to create and edit Departments under a Faculty (code + bilingual name), so that Programs and Courses have a home.
30. As an administrator, I want to deactivate a Faculty or Department, so that reorganisations are reflected without deleting history.

**Version history (`catalog.manage` to view; captured on every change)**

31. As a Dean, I want every create/edit/activate/deactivate of a Program or Course recorded as a new version, so that there is a complete history of catalog changes (FR-PCM-001.3, FR-PCM-003.4).
32. As a Dean, I want to view the version history of a Program or Course, so that I can see what changed and when.
33. As a Department Head, I want a Program's version to capture its Curriculum (which Courses in which Categories), so that curriculum changes are part of the history, not just scalar edits.
34. As a maintainer, I want the version snapshots to be append-only and owned by Catalog, distinct from the security Audit Log, so that the two concerns don't contaminate each other.

**Access control**

35. As a maintainer, I want everyone with `catalog.view` (Applicant through Council) to read the tree, Course details, and history views, so that the catalog is universally browsable.
36. As a maintainer, I want only holders of `catalog.manage` (Department Head, Dean, and the admin blanket) to create/edit/activate/deactivate and edit Curriculum, so that only authorised staff change the catalog (FR-PCM-001.1).
37. As a viewer without `catalog.manage`, I want mutation actions hidden or disabled in the UI while the API remains the real gate, so that the interface matches my authority without becoming the authority.
38. As a developer, I want every Catalog mutation checked on the API before it runs, so that authorization cannot be bypassed by calling the API directly.

**Developer & maintainer**

39. As a developer, I want the Catalog delivered as a new module with a `Public`/`Internal` split, so that it follows the modular-monolith pattern and later modules can depend on it through a facade.
40. As a maintainer, I want deptrac to enforce that Catalog only reaches Identity through `Identity\Public` and never exposes its own `Internal`, so that the first cross-module boundary is proven, not assumed.
41. As a developer, I want the whole tree available from a single read-model endpoint, so that the front end renders and searches it without chatty per-node requests.
42. As a developer, I want a realistic bilingual E-JUST seeder, so that I can develop and demo against credible academic data.
43. As a frontend developer, I want the Catalog endpoints reflected in the committed OpenAPI spec and the generated typed client, so that request/response types never drift from the server.
44. As a developer, I want the `catalog` feature added to the frontend boundary ruleset, so that it can't import another feature's internals.

## Implementation Decisions

**Module & boundaries** ([ADR-0002](../../docs/adr/0002-modular-monolith-seams.md), [ADR-0007](../../docs/adr/0007-hand-rolled-psr4-modules.md))
- New **`Catalog`** module (`backend/modules/Catalog/`) following the Identity shape: `Public/` = `Http/routes.php` + the read Resources the HTTP layer returns; `Internal/` = Models, Actions, Http/Controllers, Policies, Database/Migrations, and the domain Enums.
- **deptrac** gains a `Catalog` layer — the **first real cross-module edge**: `Catalog → Identity\Public` is allowed (it consumes the `Permission`/`Role` enums and Gates); `Catalog\Internal` is sealed from every other module.
- The cross-module **`CatalogService` facade** (architecture §5: *"is this Course an internal elective for the student's Program?"*) is **deferred to Phase 3**, built against Enrollment as its first real consumer. Phase 1 ships only the HTTP surface it needs.

**Domain model** ([ADR-0010](../../docs/adr/0010-catalog-curriculum-model.md)) — schema shape (decision-encoding sketch, from the design grill; column details are indicative, not prescriptive):
```
faculties          (id, code, name_en, name_ar, is_active)
departments        (id, faculty_id → faculties, code, name_en, name_ar, is_active)
programs           (id, department_id → departments, code, name_en, name_ar,
                    degree_level ∈ {MSc, PhD},
                    prep_required_ch     DEFAULT 6,
                    core_required_ch     DEFAULT 6,
                    elective_required_ch DEFAULT 9,
                    total_required_ch    DEFAULT 21,
                    is_active)
courses            (id, department_id → departments  NULLABLE (NULL = university-wide),
                    code  UNIQUE, name_en, name_ar,
                    level ∈ {400, 500, 600, 700},
                    lecture_ch, tutorial_ch, lab_ch, total_ch,   -- L+T+Lab = Total (invariant)
                    is_active)
curriculum_entries (id, program_id → programs, course_id → courses,
                    category ∈ {Preparatory, ProgramCore, ResearchElective},
                    ordinal,   UNIQUE(program_id, course_id, category))
catalog_versions   (id, subject_type ∈ {program, course}, subject_id,
                    version_no, snapshot JSONB, change_note,
                    changed_by → users, changed_at)
```
- **Category lives on `curriculum_entries`, never on `courses`.** A Course may have entries in several Programs under different Categories.
- **`courses.department_id` is nullable**; `NULL` means university-wide (the shared Preparatory language/culture Courses). Everything displaying a Department must render `NULL` as "university-wide" and validation must not demand a Department for those Courses.
- **Bilingual = paired `name_en` / `name_ar` columns, both required** across all four entities. The API returns both languages always; the SPA picks by active i18n locale (entity names are *content*, independent of the `Accept-Language` system-message negotiation from Phase 0).
- Domain **enums** — `DegreeLevel`, `CourseLevel`, `CourseCategory` — are Catalog-owned, modelled as PHP enums (same style as `Role`/`Permission`). `CourseCategory` is the natural Public candidate for the Phase-3 facade but nothing cross-module consumes it yet.
- **"University" is a synthetic tree root**, not a table; the tree read-model prepends it.

**Version history** ([ADR-0011](../../docs/adr/0011-catalog-version-snapshots.md))
- Every create / edit / activate / deactivate of a Program or Course appends one `catalog_versions` row with an incrementing `version_no`; the `snapshot` is the **full aggregate** (for a Program: its fields *plus its Curriculum Entries and category requirements*; for a Course: its fields). Editing a Program's Curriculum is therefore a versioned Program change.
- History is **read-only** through the API; the UI diffs two snapshots. This table is Catalog-owned and is **not** the Identity Audit Log and does not pass through `AuditLogger`. (A Catalog `catalog.manage` action *may* additionally write an Audit Log row — the "sensitive-data CRUD" the Audit Log foreshadows — but that is a separate, optional concern, not the content history.)

**API contract** (inherits Phase 0 conventions: success `{ data, meta }` via API Resources; errors native `{ message, errors }`; 422 validation, 403 unauthorized, 404 not found, 409 domain conflict)
- **`GET /api/v1/catalog/tree`** — the whole nested read model in one payload (University → Faculty → Department → Program → Category → Course), each node carrying both names and each Course its `is_active`. Powers the browse tree; **search/filter runs client-side** over this payload.
- **`GET /api/v1/courses/{code}`** — one Course's full detail plus its Curriculum Entries (which Programs/Categories it appears in) and its version history list.
- **CRUD resources** for `faculties`, `departments`, `programs`, `courses`: `GET` (list), `POST` (create), `GET /{id}`, `PATCH /{id}` (edit); **activate/deactivate** as explicit sub-actions (no hard `DELETE`).
- **Curriculum** under a Program: list its entries, add an entry (course + category + ordinal), remove an entry.
- **Versions**: `GET` the version list for a given Program or Course (read-only).
- **RBAC**: all reads require `catalog.view`; all mutations require `catalog.manage`. Gates are already registered from the §3.2 matrix (Phase 0) — **no new permissions** ([ADR-0008](../../docs/adr/0008-rbac-enums-static-matrix.md)).

**Frontend** ([ADR-0009](../../docs/adr/0009-mui-ui-foundation-design-system.md), design-system tokens/shell from Phase 0.5)
- New **`features/catalog`** feature; added to the `eslint-plugin-boundaries` ruleset.
- **Browse:** an interactive tree via **`@mui/x-tree-view`** (new dependency), client-side search/filter, Course nodes rendered with the shared `<StatusChip>` driven by a colocated catalog `statusToChip` map (active/inactive); a Course detail view.
- **Admin (gated by `catalog.manage`):** routed pages for Faculty / Department / Course list + create/edit forms; the **Program edit page hosts the Curriculum panel** — three Category sections, each with an autocomplete Course-picker to add/remove entries. A read-only version-history view per Program/Course.
- Bilingual `catalog` i18n namespace (EN + AR) for UI chrome; entity names come from the API in both languages.
- Endpoints are documented with Scribe and consumed through the Orval-generated TanStack Query client; `openapi.json` + generated client are committed and drift-checked (Phase 0 pipeline).

**Seeder**
- Realistic bilingual E-JUST data: all three Faculties present; **FoE → Computer Engineering → MSc & PhD fully populated** from the FR-PCM-002 example (Preparatory PRE401–404 + DEP501/502, two Program Core, ≥3 Research Elective, with genuine EN + AR names), the shared PRE401–404 linked into every Program; other Departments/Programs seeded lightly.

## Testing Decisions

**What makes a good test here:** assert **external, observable behavior only** — HTTP status codes and JSON bodies, `catalog_versions` rows as observable records via the history endpoint, the rendered tree/filter result, the RTL flip — never Models, Actions, or other module internals, which are swappable behind the `Public` facade ([ADR-0002](../../docs/adr/0002-modular-monolith-seams.md)/[0007](../../docs/adr/0007-hand-rolled-psr4-modules.md)). Tests are written **test-first** (Pest) per the roadmap DoD. Seams reuse the prior art Phase 0 established; **no new seams are introduced.**

**Confirmed seams:**

1. **Primary — HTTP API feature tests (Pest) against `/api/v1`** (prior art: [AuthorizationTest.php](../../backend/tests/Feature/Identity/AuthorizationTest.php), `RefreshDatabase` + `Sanctum::actingAs` + `User::factory()->assignRole()`). The highest backend seam; exercises routing → Sanctum → Gates → DB. Covers: `GET /catalog/tree` returns the nested shape with bilingual names and Course status; CRUD create/edit + activate/deactivate; `GET /courses/{code}` returns detail + Curriculum Entries + versions; **domain rules as observable responses** — `L+T+Lab ≠ Total` → 422, duplicate `code` → 422/409, missing `name_ar` → 422, hard `DELETE` unavailable / deactivate flips `is_active`, a Course placed in two Programs appears in both, an edit produces a new retrievable version (with `version_no` incremented and a Curriculum change captured); **RBAC** as a representative `catalog.view`-allow / mutate-deny (403) and a `catalog.manage`-allow — not every matrix cell.
2. **Lower — Pest unit, targeted** (prior art: [PermissionMatrixTest.php](../../backend/tests/Unit/PermissionMatrixTest.php)). Only the pure domain arithmetic that deserves a direct authoritative test rather than HTTP round-trips: the credit-hour breakdown invariant (`Lecture + Tutorial + Lab = Total`) and the fixed three-`CourseCategory` structure + default CH split (6/6/9 = 21). Kept minimal; if no value object materialises, this collapses into the API seam.
3. **Cross-stack — one Playwright smoke** (the phase's headline flow, per DoD; prior art: [headline.spec.ts](../../frontend/e2e/headline.spec.ts), reusing `login()` and `expectNoSeriousA11y()`). Against the **real stack** (Postgres + the Catalog seeder): sign in → open the catalog → browse the tree grouped by Faculty → expand to a Course → open its detail → toggle EN↔AR and observe the RTL flip → `@axe-core` reports no serious/critical violations on the tree and detail screens.
4. **Frontend — Vitest + RTL, sparingly** (DoD: non-trivial components only; prior art: [StatusChip.test.tsx](../../frontend/src/shared/ui/StatusChip.test.tsx), [SideNav.test.tsx](../../frontend/src/app/SideNav.test.tsx)). The components with real logic: the tree + client-side filter (a query hides non-matching nodes / reveals matches), the Curriculum editor (the picker adds and removes an entry), and the catalog `statusToChip` map (active vs inactive → the right tone/label).

**Boundary gate (structural, not a behavioral seam):** `deptrac.yaml` gains the `Catalog` layer with the rule `Catalog → Identity\Public` allowed and `Catalog\Internal` sealed; `deptrac analyse` runs in `composer gate`. This is the first phase where a cross-module rule bites.

**Modules under test:** Catalog (API feature tests + the targeted unit test); the frontend `catalog` feature and app shell via the Vitest components and the Playwright smoke.

## Out of Scope

Deferred per [ADR-0005](../../docs/adr/0005-localhost-learning-build.md) and the design grill; Phase 1 explicitly does **not** include:

- **CSV/Excel bulk import/export of Courses** (FR-PCM-003.5) — real parsing complexity, not the headline; a later polish phase.
- **`research_type` (PBL vs Seminar, BR-25)** — a registration rule with no Phase-1 consumer; modelled in **Phase 3 (Enrollment)**.
- **Course equivalencies** (architecture §4.1; feeds Transfer, BR-38) — modelled in **Phase 5 (Transfer & Waiver)**, where the consumer lives.
- **The cross-module `CatalogService` facade** — built in **Phase 3** against Enrollment; Phase 1 ships only the HTTP surface.
- **Internal vs external elective classification** (BR-23/24) — the *data* to support it exists (a Course's owning Department + its Curriculum Entries), but the *logic* is Phase 3.
- **Server-side full-text search / Laravel Scout** (architecture §6) — Phase 1 search is client-side over the tree payload; FTS is a documented later optimization.
- **"Revert to version N"** — Phase 1 records and read-only-views history only.
- **Global search bar and the notifications bell** — already deferred in Phase 0.5 to the phases that add their data.
- **Any use of the catalog by other domains** (registration, exemptions, grades) — those are Phases 2–7.

## Further Notes

- **Supersedes the Phase 0 demo:** the RBAC demonstration endpoint `GET /api/v1/demo/catalog-management` and its permission-gated dashboard widget were placeholders proving the `catalog.manage` gate. Phase 1's real Catalog endpoints supersede them; the demo endpoint/widget should be removed (or repointed) as the real catalog screens land, and the Phase 0 headline smoke updated accordingly.
- **New dependency:** `@mui/x-tree-view` (frontend). No new backend packages — version history is hand-rolled per [ADR-0011](../../docs/adr/0011-catalog-version-snapshots.md).
- **Glossary:** Phase 1 formalises/repairs the Catalog vocabulary in [CONTEXT.md](../../CONTEXT.md) — `Course` and `Course Category` were rewritten (Category is per-Program, not intrinsic) and **`Curriculum`** + **`Curriculum Entry`** added during the design grill. Use this vocabulary in code, tests, and UI.
- **Bilingual content vs system messages:** entity names are content (both languages always returned, SPA picks by locale); this is distinct from the Phase-0 `Accept-Language` negotiation that localises server *validation/error* messages — both apply here (a validation error is localised; the Course names are not).
- **Next step:** `/to-issues` to break this spec into vertical-slice implementation tickets under `.scratch/phase-1/issues/`, then `/tdd` red-green-refactor, then the manual review gate before Phase 2. This work builds off `main` on a `phase-1-catalog` branch (per the project's branch-per-phase pattern), only after the Phase 0.5 review gate merges.
