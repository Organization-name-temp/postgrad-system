# Students bind to the Curriculum version effective at admission

**Status:** accepted — extends [ADR-0011](0011-catalog-version-snapshots.md) and the Student
anchor in [ADR-0013](0013-acceptance-handoff-student-anchor.md)

Program Curricula are editable and versioned, while Phase 5 must decide which Courses,
categories, and credit totals define one Student's progress. Reading the current Curriculum
would let an unrelated Catalog edit change existing Students' Credit-Hour Summaries and
graduation readiness. Mixing current unfinished requirements with historical completed
ones would produce a degree plan that never existed as a coherent Curriculum.

**Decision: admission binds each Student to the Program Curriculum version effective when
the Student is admitted.** Identity retains the opaque Catalog version reference on the
Student anchor. Catalog exposes that historical Program snapshot—including Curriculum
Entries, categories, credit requirements, and Preparatory Requirement Roles—through its
Public facade. Enrollment uses it for eligibility and Exemption/Transfer/Waiver targets;
AcademicStanding uses it for the Credit-Hour Summary. Phase 6 Records consumes the same
version when composing the Transcript.

Changing the Program's current Curriculum never migrates an existing Student implicitly.
A future explicit curriculum-migration workflow may bind a Student to a later version, but
that workflow is outside Phase 5; direct editing of the version reference is forbidden.

## Considered options

- **Admission-time Curriculum version (chosen).** Protects the Student's agreed degree
  requirements and reuses Catalog's append-only aggregate snapshots. It requires a new
  cross-module historical read and a backfill for existing Student anchors.
- **Always-current Curriculum (rejected).** Simplifies reads, but Catalog edits silently
  rewrite existing Students' requirements and academic progress.
- **Current unfinished plus historical completed requirements (rejected).** Accommodates
  change without explicit migrations, but creates hybrid Curricula that are difficult to
  explain, reproduce, or audit.

## Consequences

- The acceptance mint resolves and stores the admitted Program's current Program-version
  id in the same transaction as the Student anchor.
- Existing Students receive a deterministic backfill: choose the latest Program Version
  whose `changed_at` is at or before `admitted_at`; when none exists, choose the earliest
  available Program Version. Record whether the binding was an admission-time match or the
  earliest-version fallback so the approximation is auditable.
- Academic decisions target Courses as they appear in the bound snapshot. Later Catalog
  renames or category changes do not reinterpret those decisions.
- A deactivated current Program or Course does not invalidate the historical Curriculum
  governing an already-admitted Student.
