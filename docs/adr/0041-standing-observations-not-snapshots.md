# Standing Observations record transitions, not current Standing

**Status:** accepted

Academic Standing is derived on demand, but Warning and termination notifications must be
sent once per real transition and recovery should be visible. Re-notifying on every Grade
or credit event causes duplicates; deduplicating solely by trigger evidence cannot reliably
recognize moves between Good, Warning, and Probation.

**Decision: AcademicStanding stores an append-only Standing Observation only when a
recalculation differs from the latest observed value.** The Observation records Student,
Standing, evidence fingerprint, observed time, and causing event reference. It is a
notification checkpoint and history entry, never the source for a current Standing read;
every API response recalculates from authoritative owner facades.

Appending an Observation emits `StandingChanged`. Entering Academic Warning, Academic
Probation, or Termination Pending notifies the Student and assigned Advisor. Moving to a
less severe Standing also notifies both. The initial Not Yet Assessed Observation sends no
notification. Termination Case notifications remain workflow-specific and are not replaced
by the general Standing message.

Relevant causes include finalized initial/correction/Incomplete Grade rounds, Incomplete
expiry, Withdrawal, an Exemption/Transfer/Waiver decision or Revocation, the second-Semester
Preparatory checkpoint, and termination dismissal or trigger clearance. Handlers are
idempotent. They and the manually run, idempotent
`academic-standing:reconcile {student?}` command invoke the same per-Student service. With
no Student argument, the command scans all active Students; with one, it performs targeted
recovery. It evaluates elapsed ordinary and remediation checkpoints, reconciles missing or
cleared Termination Cases, and appends any missing Standing Observation and notification.
It observes Grading's authoritative Incomplete state and never performs Grade conversion.
For a determinate remediation checkpoint, the service includes Case/Observation changes
and Enrollment's deadline-guarded Public evaluation command in one transaction; an
unresolved valid Incomplete leaves that checkpoint active.
Replacing an open Termination Case after `EvidenceChanged` uses this same service. If
Standing remains Termination Pending, it opens the new Case and sends its workflow
notification without appending a duplicate Standing Observation because the derived value
did not change.
The service aggregates every actionable trigger into one fingerprint and preserves the
one-non-terminal-Case-per-Student invariant during concurrent event or command runs.

## Considered options

- **Append-only Observations (chosen).** Supports exact transition and recovery messaging
  without making stored Standing authoritative. It adds historical rows and a recovery
  mechanism whose naming must not imply a mutable snapshot.
- **One event/command reconciliation service (chosen).** Keeps time-driven checkpoint runs
  and event-driven academic changes on identical trigger logic. A global manual scan costs
  more than targeted recovery but is acceptable for the localhost build.
- **Mutating during Standing reads (rejected).** Would notice elapsed time without a manual
  command but makes a GET request create Cases and notifications.
- **Separate checkpoint and Observation commands (rejected).** Narrows each invocation but
  duplicates ordering and trigger reconciliation or permits the records to diverge.
- **Evidence-keyed notification deduplication (rejected).** Avoids Standing history but
  couples semantics to notification payloads and cannot reliably detect recovery.
- **Notify on every adverse recomputation (rejected).** Has no checkpoint state but creates
  repeated warnings from unrelated or corrective events.

## Consequences

- No `current_standing` column or editable standing balance exists. Deleting Observations
  would lose history and notification checkpoints, not change the calculated Standing.
- Cross-module events consumed by AcademicStanding must be Public contracts; Internal event
  classes may not cross module seams.
- Because the localhost scope has no scheduler, routine time-based evaluation requires a
  human to run `academic-standing:reconcile`; targeted runs repair missed post-commit work.
- Tests prove repeated delivery is idempotent, unchanged recalculation emits nothing,
  global and targeted command runs reconcile the same results as events, elapsed checkpoints
  open a Case once, cleared triggers close it once, adverse transitions and recoveries
  notify once, stale remediation deadlines roll back every module's effects, and current
  reads ignore stale/missing Observations.
- Tests distinguish Case workflow notifications from Standing changes: an
  `EvidenceChanged` replacement sends the new Case notification while unchanged
  Termination Pending appends no Standing Observation.
- Concurrency tests prove simultaneous trigger events converge on one aggregate Case and
  one initial workflow notification rather than parallel Cases.
