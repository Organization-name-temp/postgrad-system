# Cross-module reads: reference by ID, read through a Public DTO facade, no cross-module FK

**Status:** accepted

Admissions is the **first** module to read another module's data: FR-ADM-004 has an
Applicant pick a primary and optional alternative **Program**, which Admissions must
validate and later render on reviewer screens. The `Program` model lives in
`Catalog\Internal`, which `deptrac` seals from every other module. Rather than open that
seam, an Application stores `primary_program_id` / `alternative_program_id` as **plain
reference values with no database foreign key to `programs`**, and reads program data
only through a new **`Catalog\Public` read facade** (`ProgramCatalog`) that returns
**read DTOs** (id, code, `name_en`/`name_ar`, `DegreeLevel`, department + faculty names) —
never Catalog's Eloquent models. This is the pattern every later cross-module read
(Enrollment, Grading, Records) will follow.

This **amends [ADR-0010](./0010-catalog-curriculum-model.md)**, whose consequence note
anticipated the Catalog facade arriving in Phase 3 with Enrollment as its first consumer.
Admissions is the real first consumer, a phase earlier; the Phase 3 elective-classification
methods extend the same facade rather than introducing it.

## Considered options

- **Reference by ID + Public DTO facade, no cross-module FK (chosen).** Preserves the
  modular-monolith seam (ADR-0002) at both the PHP layer (deptrac-enforced) *and* the
  database layer (no FK coupling two modules' tables). Cost: no referential-integrity
  guarantee from the database — a `program_id` can dangle if a Program is hard-deleted, so
  the facade returns `null` for a missing Program and callers handle it. In practice
  Catalog is deactivate-only (BR-18), so Programs are never hard-deleted.
- **A real foreign key `applications.primary_program_id → programs.id` (rejected).**
  Deptrac wouldn't even catch it (it analyses PHP, not schema), which is precisely the
  danger: it silently couples the two modules' tables, so Catalog could no longer own its
  schema independently and a Catalog migration could break Admissions. The seam would erode
  invisibly.
- **Share the `Program` Eloquent model across the seam (rejected).** Simplest to write, but
  hands every consumer Catalog's full write surface and internal invariants, defeating the
  Public/Internal split entirely.

## Consequences

- The applicant-facing program **tree** for selection reuses the existing
  `GET /catalog/tree` HTTP endpoint (Phase 1) for display; the `ProgramCatalog` facade is
  for backend **validation, storing a valid id, and rendering program summaries** on
  reviewer screens.
- `DegreeLevel` (a `Catalog\Public` enum) crosses the seam so Admissions can check that an
  Applicant's previous degree qualifies for the selected Program (FR-ADM-004.4).
- Every future cross-module read copies this shape: opaque id on the consumer, a Public
  read DTO from the owner, no FK. The explicit *no-FK* is the point — record it so nobody
  "fixes" the missing constraint later.
- Phase 5 extends the same authority rule to change signals: Catalog's minimal Public
  `CourseChanged` event carries only the Course id after activation-state changes.
  Enrollment consumes the signal, re-reads the current Public DTO, and idempotently
  reconciles unused remediation opportunities rather than trusting copied Course fields
  ([ADR-0043](0043-enrollment-owns-remediation-obligations.md)).
