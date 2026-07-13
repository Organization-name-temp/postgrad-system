# Catalog model: Category on the Curriculum Entry, Courses department-owned

**Status:** accepted

The naive catalog model gives each `Course` an intrinsic `category` column (Preparatory /
Program Core / Research Elective) and a single owning Program. That model breaks against
two facts of the E-JUST domain. First, the four university language/culture **Preparatory**
courses (PRE401–404, BR-14) are **shared by every Program** — they cannot belong to one.
Second, "Core" and "Elective" are **relative to a Program**: BR-23/24 distinguish an
*internal* elective (from the student's own Program) from an *external* one (another
Program's course), and architecture §5 has Enrollment asking Catalog *"is this Course an
internal elective for the student's Program?"* — a question that only has an answer
**per Program**. A single intrinsic `category` cannot express a Course that is Core for its
home Program and simultaneously an external elective for another.

We therefore model the placement of a Course into a Program under a Category as a
first-class link — the **Curriculum Entry** (`program_courses`: program, course, category,
ordinal). Category lives on the link, never on the Course. A **Course** is a standalone
teachable unit owned by a **Department** via a **nullable** `department_id`, where `NULL`
means university-wide (the shared Preparatory courses, which belong to no Department). See
[CONTEXT.md](../../CONTEXT.md) for the resolved glossary (`Course`, `Course Category`,
`Curriculum`, `Curriculum Entry`).

## Considered options

- **Category on the Curriculum Entry; Course department-owned, nullable (chosen).**
  Uniformly handles shared Preparatory courses and per-Program Core/Elective
  classification, and is exactly the shape the Phase 3 internal-vs-external-elective
  question needs. Cost: a join table plus seeded curriculum links, and a nullable FK whose
  `NULL` carries the meaning "university-wide."
- **Category intrinsic to the Course + single home Program (rejected).** Fewer tables, but
  forces Preparatory courses to be special-cased as shared, cannot represent a Course that
  is Core in one Program and an elective in another, and pushes internal/external elective
  onto a home-Program comparison that the shared prep courses would violate.
- **A seeded "University Requirements" Department so `department_id` is never null
  (rejected).** Keeps the FK non-null but injects a synthetic org unit that surfaces as a
  phantom node in the tree and the Department dropdowns.

## Consequences

- The **tree** (FR-PCM-002) is a read model built by walking Program → its Curriculum
  Entries grouped by Category; per-Category "(N CH)" labels come from the Program's stored
  required-CH targets, not from summing linked courses.
- Editing a Program's Curriculum (adding/removing entries) is a **Program change** and is
  captured by version history ([ADR-0011](./0011-catalog-version-snapshots.md)).
- A Course's `NULL` `department_id` must be handled everywhere a Department is displayed
  (course detail, admin course list) as "university-wide," and validation must not require
  a Department for Preparatory language courses.
- Phase 3 (Enrollment) reads Curriculum Entries to classify a registration as internal or
  external elective; the cross-module `CatalogService` facade that exposes this is built
  then, against that real caller, not speculatively now.
