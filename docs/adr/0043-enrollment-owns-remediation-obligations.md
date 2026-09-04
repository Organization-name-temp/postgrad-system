# Enrollment owns Remediation Obligations

**Status:** accepted

A post-checkpoint Preparatory Exemption Revocation creates a Remediation Obligation whose
facts span two concerns. It controls registration priority, Course Offering availability,
timetable handling, and capacity exceptions, but its deadline also controls when the
restored requirement becomes termination evidence. Placing it in the wrong module risks a
dependency cycle: `AcademicStanding` already consumes `Enrollment\Public` to calculate
Standing, Credit-Hour Summary, Graduation Readiness, and termination triggers.

**Decision: Enrollment owns Remediation Obligations and exposes immutable DTOs through
`Enrollment\Public`.** Enrollment creates the obligation with the Exemption Revocation,
tracks `AwaitingOffering` versus its assigned remediation Semester/checkpoint, reconciles
Course Offering changes, emits the once-only notifications, and applies the shared
registration-priority and capacity-exception workflow. AcademicStanding reads the Public
DTO to suppress or activate the Preparatory termination trigger; it never writes the
obligation or asks Enrollment to read AcademicStanding state.

When AcademicStanding reaches a determinate remediation result, it opens one database
transaction for the Case/Standing effects and calls Enrollment's idempotent Public
`markRemediationEvaluated(obligationId, expectedEffectiveDeadline)` command. Enrollment
changes only its own record. The command succeeds as a no-op when that exact checkpoint was
already evaluated, but rejects an active deadline mismatch so the whole transaction rolls
back and AcademicStanding can reload after a concurrent Calendar extension. A valid
unresolved Incomplete is not determinate and leaves the obligation active. This preserves
the one-way dependency while making termination evidence and registration-obligation
closure atomic.

Enrollment also owns the non-checkpoint terminal path. Approval of a replacement
Preparatory Exemption atomically closes any matching active or `AwaitingOffering`
obligation as `ReleasedByExemption`. This outcome is distinct from Course satisfaction and
records that corrected evidence proved the requirement did not apply. The published
academic-change event causes AcademicStanding to clear matching evidence from a non-final
Termination Case; it never reverses final termination.
That same transaction terminally rejects each `Pending` or `AwaitingCapacityOverride`
Registration Request containing the released Course, with a system-attributed cause. The
request and capacity-review history remain intact, and the Student plus current reviewers
are notified. Enrollment never silently removes a line from the submitted atomic batch.
Already-created Enrollments are not part of that rejection: approval closes the obligation
but leaves each in-progress Enrollment unchanged for ordinary drop/withdrawal or grading.
Before these approval effects, Enrollment acquires Identity's transaction-scoped Student
guard and revalidates `Active`. Final termination uses the same Identity-first order, so
commit order resolves the race without partial obligation or request changes.

Activation is event-driven and recoverable. Enrollment reconciles synchronously after its
own Course Offering create/activation/relevant update operations. Catalog publishes a
minimal Public `CourseChanged` event after a Course activation-state change; its payload
contains only the Course id, and Enrollment re-reads authoritative `Catalog\Public` data.
Calendar publishes a
minimal Public `SemesterChanged` event after relevant Semester creation or date changes;
the payload identifies the Semester and does not copy dates, which Enrollment reads from
`Calendar\Public`. If an assigned checkpoint has not been evaluated, reconciliation updates
it to the later of its effective deadline and the Semester's current Grade-Submission
Deadline. Reconciliation appends a Calendar-deadline-observed transition containing both
the official date and retained effective checkpoint and notifies the Student, assigned
Advisor, and scoped Department Head; thus extensions apply, but earlier edits cannot
withdraw time already granted. Once evaluated, that checkpoint's decision deadline is
frozen. The idempotent command
`enrollment:reconcile-remediation {student?}` performs the same calculation globally or
for one Student after any missed post-commit event.

Until an Enrollment exists, reconciliation also replaces a qualifying opportunity lost to
an institution-controlled Course, Offering, schedule, or Calendar change. It prefers
another eligible Offering in the assigned Semester, then the earliest later lawful
opportunity, else `AwaitingOffering`. The previous qualifying Offering ids, Semester, and
deadline remain transition evidence, and the Student, assigned Advisor, and scoped
Department Head are notified. Full capacity and Student-created conflicts do not count as
loss. An existing Enrollment proves the opportunity was used and freezes this assignment.

The obligation is durable workflow state rather than a recomputed projection. Its origin
Revocation, restored Curriculum Course, state, assigned Semester/deadline when present,
qualifying Offering evidence, activation/satisfaction/evaluation timestamps, and causative transitions must remain
auditable. An active deadline may extend with Calendar but cannot move earlier, and its
official observations and effective values remain in the transition history; Offering
changes do not rewrite the originating facts.

## Considered options

- **Enrollment ownership (chosen).** Keeps the obligation beside its originating credit
  decision, Offering reconciliation, and registration enforcement while preserving the
  one-way `AcademicStanding → Enrollment` dependency. It makes Enrollment expose one DTO
  whose deadline has a termination consumer.
- **AcademicStanding ownership (rejected).** Places termination timing beside termination
  rules, but Enrollment would need to read it to enforce registration priority, creating
  the reverse half of a module cycle or requiring duplicated state.
- **Derive the obligation on every read (rejected).** Avoids a new authoritative record,
  but cannot reliably preserve which opportunity assigned the deadline, once-only
  notification checkpoints, or historical behavior after Calendar/Offering edits.
- **Recovery command without events (rejected).** Has fewer Public contracts but leaves
  `AwaitingOffering` stale until a human runs it. The event plus command pair gives prompt
  activation and explicit repair without a scheduler.
- **Embed Course state in `CourseChanged` (rejected).** Avoids one Catalog read but copies
  authoritative eligibility data into an event payload that can become stale. The id-only
  signal matches Calendar's established reconciliation pattern.
- **Post-commit evaluation event (rejected).** Avoids a cross-module transaction but can
  leave an already evaluated obligation active in registration until delivery or repair.
- **Enrollment evaluates the academic result (rejected).** Keeps obligation changes local
  but introduces `Enrollment → AcademicStanding` knowledge and the reverse half of a cycle.
- **Generic satisfied outcome for a replacement Exemption (rejected).** Uses fewer states
  but falsely implies Course completion and loses the reason registration pressure ended.
- **Silently revise an affected Registration Request (rejected).** Avoids resubmission but
  changes the Student's atomic batch and can create a new load or timetable result without
  consent or review.
- **Auto-drop an existing Enrollment (rejected).** Aligns the schedule immediately with the
  released requirement but bypasses ordinary modification rules and may remove study the
  Student chose to continue.

## Consequences

- `Enrollment\Internal` owns the Remediation Obligation table, transitions, Offering
  reconciliation, and registration integration. Its Public facade returns single-Student
  and batched DTOs under ADR-0028.
- `AcademicStanding\Internal → Enrollment\Public` remains the only dependency direction;
  no `Enrollment → AcademicStanding` edge is added.
- Credit-Hour Summary continues to react directly to the effective Revocation. The
  obligation DTO supplies only remediation state and termination timing, not duplicated
  credit balances or Standing values.
- AcademicStanding's reconciliation command reads the authoritative obligation DTO to
  evaluate an elapsed remediation checkpoint. For a determinate result it atomically opens
  or clears a Termination Case, records any Standing effect, and invokes Enrollment's
  deadline-guarded Public evaluation command; Enrollment remains the only writer of the
  obligation and its evaluated transition.
- Replacement Preparatory Exemption approval and `ReleasedByExemption` closure occur in one
  Enrollment transaction, together with idempotent rejection of affected non-terminal
  Registration Requests. Registration cannot continue to enforce an obligation whose
  underlying requirement has been released; request and capacity-review history is kept.
- Existing in-progress Enrollments remain unchanged. If one produces earned credit, raw
  Earned and Exempted CH coexist; ADR-0035 excludes that Course's Earned CH from the
  target-matched Satisfied operand before applying the category cap.
- Replacement Exemption approval cannot race final termination on stale state: both use the
  Identity-owned Student guard, and all Enrollment effects roll back if lifecycle
  revalidation fails.
- Reconciliation may replace active qualifying Offering evidence only before Enrollment;
  it preserves the prior assignment, emits one transition/notification per real change,
  and selects the same-Semester/later-Semester/awaiting result deterministically.
- The `CourseChanged` and `SemesterChanged` listeners, local Offering hooks, and command
  share one application service. Repeated local changes, event delivery, or command runs
  produce no duplicate transition or notification.
- Tests prove Revocation creation is atomic, reconciliation is idempotent, active deadlines
  follow extensions but reject shortening with one transition and notification per real
  Calendar edit, evaluated deadlines remain frozen, Offering edits preserve history,
  Course deactivation promptly reselects or suspends unused opportunities,
  registration consumes the owner record, duplicate evaluation is a no-op, stale-deadline
  evaluation rolls back all module effects, valid Incompletes remain active, replacement
  Exemption approval closes the obligation and rejects each affected pending request
  exactly once, lost institution-provided opportunities reassign without altering existing
  Enrollments, and
  AcademicStanding treats the DTO as authoritative for trigger timing.
