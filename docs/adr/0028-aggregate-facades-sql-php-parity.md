# Derived values use batched owner facades and one authoritative calculator

**Status:** accepted

"Derived, never stored" is this project's house rule. Period state, attendance percentage,
Incomplete deadlines, grade supersession, and GPA/CGPA are recomputed from authoritative rows, so
they cannot drift. [ADR-0020](0020-grading-owns-gpa-read-model.md) accordingly puts GPA arithmetic in
`Grading`'s `GpaCalculator`, behind `GradingRecords`.

Phase 4.6 needs derived values for cohorts: GPA Distribution, derived filters and sorts, and richer
Dashboard Cards. Calling a single-record facade once per row would create N+1 cross-module reads.
Implementing the arithmetic again in cross-module SQL would violate both the single-owner rule and
[ADR-0012](0012-cross-module-reads-by-id-and-dto.md), because module tables may not be joined behind
another module's facade.

## Decision

Each owning module extends its existing `Public` facade with batch methods such as
`cgpaForMany(array $studentIds)`. A batch operation:

1. obtains candidate IDs and immutable DTOs from other modules through their `Public` facades, in
   bounded batches;
2. queries only tables owned by the calculating module, in bulk;
3. invokes the existing owner-local PHP calculator for each candidate;
4. filters and sorts the completed derived result before slicing the requested page.

Stored-column filters and sorts remain database queries and paginate in SQL. Only a request involving
a derived value materializes its already-authorized candidate set in memory. This is an accepted
localhost-build tradeoff: pagination metadata remains truthful because filtering and sorting happen
before slicing, while the arithmetic and module boundaries remain authoritative.

Batch behavior is proved against the existing single-record facade over the deterministic seeded
cohort. The parity test covers accumulation and rounding, supersession across Semesters, and the
`null` versus `0.00` distinction. It compares two call shapes that share one calculator; it does not
authorize a second SQL implementation of the academic rule.

## Considered options

- **Batched owner-facade computation (chosen).** Avoids N+1 reads, preserves module seams, and keeps
  one implementation of each academic rule. It deliberately materializes scoped candidates for
  derived filtering in this localhost build.
- **A cross-module SQL implementation (rejected).** It would join another module's tables and
  duplicate academic arithmetic, contradicting ADR-0012 and ADR-0020.
- **A stored read model (deferred).** An indexed projection would scale further, but introduces a
  refresh path and drift risk that ADR-0005 intentionally excludes. It may later replace the
  internal batch strategy without changing the facade.
- **Refuse derived filters (rejected).** This would remove much of Phase 4.6's analytical value.

## Consequences

- Consumers request derived values from their owner and never reimplement them.
- Candidate acquisition is batched and scoped before calculation; no per-row cross-module calls are
  allowed.
- Derived filters and sorts occur before in-memory pagination. Stored fields continue to filter,
  sort, and paginate in SQL.
- Tests assert batch-versus-single result parity over the seeded cohort and verify truthful totals,
  ordering, and page boundaries after derived filtering.
- Phase 5 consumes the same facades for Academic Standing and Credit-Hour Summary rather than
  re-deriving their inputs.
- A future stored projection is an optimization behind this interface, not a contract change.
