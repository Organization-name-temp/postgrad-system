# 06 — Program selection + Catalog read facade + qualification + finalize

**What to build:** The applicant browses E-JUST's Programs, picks one primary and optionally one alternative (validated against a degree-qualification rule), reviews a summary, and **finalizes** — moving a complete application into Student Affairs review. This ticket builds the first cross-module read into Catalog.

**Blocked by:** 05 — Document upload

**Status:** ready-for-agent

- [ ] A **`Catalog\Public` `ProgramCatalog` read facade** returns program-summary DTOs (id, code, `name_en`/`name_ar`, `DegreeLevel`, department + faculty names) and `null` for a missing id — **no Eloquent model crosses the seam, no foreign key** from Admissions to `programs` (ADR-0012). `deptrac` `Admissions → Catalog\Public` allowed; internals sealed.
- [ ] Program selection **reuses the Phase-1 catalog tree** for display; the applicant stores `primary_program_id` and optional `alternative_program_id` as opaque references, each validated to exist.
- [ ] The **qualification rule** (order BSc < MSc < PhD; target level must be same-or-one-step-up: BSc→MSc, MSc→MSc·PhD, PhD→PhD) rejects an unqualified program (422, bilingual message); `applying_for_degree` must match the primary program's `DegreeLevel`.
- [ ] A review/confirmation summary precedes finalize.
- [ ] **Finalize** requires a primary program + all required and conditionally-required documents; on success moves `EmailVerified → UnderSAReview` and sets `finalized_at`; missing prerequisites → 422.
- [ ] Pest feature tests: facade returns summaries / null; qualification pass + fail; finalize gating (missing program / missing conditional doc); state reaches `UnderSAReview`. Vitest for the picker if non-trivial. All gates green.
