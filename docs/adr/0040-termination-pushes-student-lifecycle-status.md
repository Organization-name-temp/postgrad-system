# Final termination pushes Student lifecycle status to Identity

**Status:** accepted

AcademicStanding owns the Termination Case and final Council decision, but Enrollment and
later modules must block new academic actions for a terminated Student. Having each module
query `AcademicStanding\Public` would create a cycle because AcademicStanding already reads
Enrollment. Removing the Student Role would block too much, including access to the
Student's own historical record.

**Decision: Identity owns an operational Student Lifecycle Status on the Student anchor,
initially `Active`; final Council termination changes it to `Terminated` through an
`Identity\Public` command in the same transaction as the final Termination decision.** The
User retains the Student Role. The Termination decision remains authoritative in
AcademicStanding; the lifecycle value is the narrow projection other modules need for
operational gates.

Final termination and replacement Exemption approval serialize on the Identity-owned
Student anchor. `Identity\Public` exposes a transaction-scoped lock-and-read guard; both
workflows acquire it before any module-owned lock or write and then revalidate lifecycle
and authoritative termination evidence. If Exemption approval commits first, Council
revalidation observes the cleared trigger and cannot terminate on the stale Case. If final
termination commits first, Exemption approval observes `Terminated` and makes no change.
The two outcomes cannot both commit from stale snapshots.

Finalization also invokes Enrollment's idempotent Public
`rejectOpenRequestsForTermination(studentId, terminationCaseId)` command inside that same
transaction and after acquiring the Identity guard. Enrollment appends system-attributed
`Rejected` transitions to every non-terminal Registration Request and Credit Decision
Request, preserving all evidence, batch lines, reviews, and capacity decisions. The
Student and each request's current reviewers are notified. If any closeout or lifecycle
write fails, the Council decision, request transitions, and lifecycle change all roll back.
Existing Enrollments and effective credit decisions remain unchanged.

Terminated Students may authenticate and read their historical Grades, Credit-Hour
Summary, requests, decisions, and Transcript. They may not create or change Course
Registrations or Credit Decision Requests. Staff oversight and record issuance remain
available under their ordinary permissions and scopes.

## Considered options

- **Retain Role and push lifecycle status (chosen).** Preserves record access and keeps the
  dependency graph acyclic. It duplicates one final fact and requires an atomic update
  discipline.
- **Remove the Student Role (rejected).** Reuses authorization gates but also removes
  legitimate self-service history access and may leave the User roleless.
- **Query AcademicStanding from every consumer (rejected).** Keeps one stored fact but
  creates an `Enrollment ↔ AcademicStanding` cycle and spreads a basic eligibility check
  across modules.
- **Commit-order serialization (chosen).** Preserves whichever valid decision commits first
  and forces the second to use current facts. Fixed workflow precedence is simpler but can
  discard corrected evidence or approve unsupported termination.
- **Atomically reject open requests (chosen).** Removes impossible work from active queues
  while preserving its audit trail. Leaving requests open requires every reviewer action to
  rediscover termination and strands workflows indefinitely.

## Consequences

- `Identity\Public` exposes lifecycle status on Student DTOs and a command callable only by
  the AcademicStanding termination flow.
- It also exposes the narrow transaction-scoped Student lock-and-read guard used by final
  termination and replacement Exemption approval. Both acquire the Identity lock first;
  callers never receive or mutate the Identity model.
- Enrollment checks `Active` on every Student-initiated or on-behalf schedule mutation and
  Credit Decision Request mutation. Frontend disabling remains display-only.
- Finalization calls Enrollment's termination-closeout command after the Identity guard and
  before commit. The command is idempotent by Student/Termination Case and reuses each
  request's existing terminal `Rejected` state with an attributed system cause.
- The lifecycle status can change only with the corresponding final domain decision in the
  same transaction; no general-purpose administrator edit exists.
- Reinstatement, if ever required, needs an explicit future workflow and cannot be inferred
  from later remediation.
- Concurrency tests prove Exemption-first blocks stale final termination,
  termination-first blocks Exemption approval, and neither interleaving partially changes
  the Case, lifecycle, decision, obligation, or Registration Request.
- Tests prove Draft, Returned, Pending, and `AwaitingCapacityOverride` work is rejected
  exactly once with review history intact, while approved Enrollments and effective credit
  decisions are not modified.
