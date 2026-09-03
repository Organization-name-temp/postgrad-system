# 06 — Admissions and Catalog lists on the query contract

**What to build:** Everything the screening queue gained in tickets 02 and 03, applied across the
rest of Admissions and the whole of Catalog. A Dean narrows their faculty-scoped review queue by
degree level and decision state and exports it. A catalog administrator filters Courses by
Department, level, credit hours, Research Type and active state, types `CSE5` and finds `CSE511`,
and exports the result — which is also the file ticket 16 will import back.

This is one of two mechanical migration batches. Nothing here is a new idea; the value is in
applying the same idea uniformly and proving each surface's authorization as it lands.

**Admissions collections:** the public open-Cycle lookup, applicant reschedule-slot picker,
interviewer assigned-work list, Dean review queue and decision worklist (both faculty-scoped), the
awaiting-interview worklist, booked interviews, interview-slot pool, Admission Cycles, and the
advisor and interviewer pickers.

**Catalog collections:** the Catalog tree, Courses and Course version history, Programs and Program
curriculum/version history, Departments, and Faculties.

**Export coverage:** `/dean/applications/export`, `/dean/decisions/export`,
`/applications/awaiting-interview/export`, `/interviews/export`, `/interview-slots/export`,
`/admission-cycles/export`, `/interviews/assigned/export`, `/catalog/tree/export`, `/courses/export`,
`/courses/{course}/versions/export`, `/programs/export`,
`/programs/{program}/curriculum/export`, `/programs/{program}/versions/export`,
`/departments/export`, and `/faculties/export`. The unauthenticated `/admission-cycles/open` lookup
is excluded to avoid adding an unauthenticated bulk-scraping route. The applicant-owned
`/application/interview/slots` picker is excluded to prevent exporting interviewer/capacity data
beyond the minimum needed to choose one permitted slot. `/dean/advisors` and
`/interview-slots/interviewers` are excluded to prevent staff-directory downloads. Tests prove all
four excluded export routes do not exist.

Two things to get right rather than copy blindly:

- **Scope stays a base constraint.** The Dean's Faculty scope is applied by the controller before
  any filter, and a filter naming another Faculty yields an empty page — never a 422, which would
  make the boundary probeable.
- **Bilingual search is per-resource.** Each collection declares which fields `q` searches, across
  both `_en` and `_ar`. For Courses that must include the code, because code-fragment matching is
  the single most common catalog search and is precisely what a word-based search cannot do.

Pickers adopt the contract too — the API keeps **one** response shape — but they get a type-ahead
rather than the full filter bar, and a 100-row default.

**Blocked by:** 02 — Query contract foundation; 03 — Export foundation; 04 — batched owner-facade
derivation used by derived queue values.

**Status:** ready-for-agent

- [ ] Every Admissions and Catalog collection named above returns the paginated envelope, including
      public/personal lookups, hierarchical Catalog reads, curricula, and version histories
- [ ] Each declares a filter allowlist, a sort allowlist and its `q` field list, actor-aware
      wherever a field is permission-sensitive
- [ ] Sort accepts comma-separated fields with independent descending markers; search normalizes
      Arabic on both sides
- [ ] Every endpoint appends its stable unique key to the effective sort unless already selected;
      adjacent pages neither duplicate nor omit tied rows
- [ ] Courses filter by Department, level, credit hours, Research Type and active state; `CSE5`
      matches `CSE511`
- [ ] Programs, Departments and Faculties filter and search bilingually
- [ ] Dean queues stay faculty-scoped with filters narrowing inside that scope only; an
      out-of-faculty filter value returns an empty page
- [ ] Work surfaces gain `<FilterBar>` with URL state; pickers gain a type-ahead and a 100-row
      default
- [ ] Every named export sibling above exists with a canonical column set, authenticated
      fetch-to-blob UI, and an Audit Log entry; all four excluded lookup/picker routes return 404
- [ ] The Courses export column set is the shape ticket 16 will accept as an import template
- [ ] Every migrated endpoint adds its row to the cross-module contract test dataset
- [ ] Existing Admissions and Catalog Pest tests are updated to the new envelope and still assert
      their original authorization behaviour
- [ ] `openapi.yaml` and the Orval client regenerated and committed; both `api:check` gates pass
- [ ] Every endpoint documents each `@queryParam`, preserves model-bound `@urlParam ... Example:`,
      and regenerates deterministic OpenAPI/Orval artifacts
- [ ] `composer gate` and `npm run gate` green
