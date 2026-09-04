# Preparatory non-completion is checked after the second grading deadline

**Status:** accepted

The termination requirement says a Student must finish Preparatory Courses and the English
requirement within two consecutive main Semesters, but a Semester's Grades need not be
finalized by its end date, and an Incomplete has a valid resolution window extending into
the following Semester. Checking at `ends_at` would flag Students before their academic
outcomes exist; waiting for two course offerings could postpone the rule indefinitely.

**Decision: count the Student's enrollment Semester and the immediately following main
Semester, then evaluate Preparatory completion after the second Semester's Grade-Submission
Deadline.** Every Adjusted Preparatory requirement in the bound Curriculum Version must be
satisfied. English is one of those six Preparatory Requirement Roles, not a second logical
condition in addition to the set.

If an affected Preparatory Enrollment is still Incomplete at that checkpoint, the trigger
is undecided rather than active. Re-evaluate immediately when the Incomplete resolves or
expires: a result satisfying the requirement avoids the trigger; a failing result or Fail
Withdrawal activates it if a Preparatory requirement remains unsatisfied.

A Preparatory Exemption revoked after this ordinary checkpoint is a reliance exception.
The restored requirement affects the Credit-Hour Summary immediately, but is suppressed
from the Preparatory termination trigger through the Grade-Submission Deadline of the
first main Semester in which its Course has an eligible Offering and the Student can still
lawfully add it at Revocation time. The current Semester qualifies while registration,
add/drop, or late-add is open; after late-add closes it is skipped. At that remediation
checkpoint the requirement becomes actionable if still unsatisfied; a valid Incomplete
defers the determination under the same rule above. Other active termination evidence is
never suppressed by this window.

If no future eligible Offering exists, the Remediation Obligation is
`AwaitingOffering` and has no checkpoint. It affects Remaining CH and Graduation
Readiness immediately but cannot contribute termination evidence. Entry sends a once-only
notification to the Student, assigned Advisor, and scoped Department Head. When an
Offering creates a lawful enrollment opportunity, idempotent reconciliation assigns the
remediation Semester/deadline, activates registration priority, and notifies them again.
Enrollment owns and publishes this obligation/checkpoint; AcademicStanding consumes the
DTO as authoritative termination-timing evidence rather than recreating its history
([ADR-0043](0043-enrollment-owns-remediation-obligations.md)).
Local Offering mutations trigger reconciliation directly. Calendar's Public
`SemesterChanged` event triggers the same service for date changes, and
`enrollment:reconcile-remediation {student?}` is the idempotent recovery path.
While a remediation checkpoint remains unevaluated, a change to its Semester's
Grade-Submission Deadline extends that active checkpoint only when the new date is later;
an earlier edit never withdraws time already granted. Either edit appends the official and
retained effective dates to the obligation's history and notifies the Student, assigned
Advisor, and scoped Department Head. Evaluation freezes the deadline used for the resulting
decision.

Before an Enrollment exists for the restored Course, reconciliation also responds when an
institution-controlled Course, Offering, schedule, or Calendar change removes the
opportunity that justified the checkpoint. It first uses another eligible Offering in the
same Semester. If none remains, it selects the earliest later lawful opportunity; if none
exists, the obligation returns to `AwaitingOffering` with no checkpoint. Each real
opportunity/checkpoint transition preserves the former qualifying Offering evidence and
notifies the Student, assigned Advisor, and scoped Department Head. A full Offering remains
available through the capacity workflow, and a Student-created timetable conflict never
qualifies. Once an Enrollment exists, the opportunity has been used and is not reassigned.

Time passing emits no domain event. The manually run, idempotent
`academic-standing:reconcile {student?}` command therefore evaluates elapsed ordinary and
remediation checkpoints globally or for one Student, using the same per-Student service as
AcademicStanding's event handlers. It may open the missing Termination Case and append the
corresponding Standing Observation/notifications, but it only reads any authoritative
Incomplete state maintained by Grading.

For a determinate remediation result, AcademicStanding creates the Case/Standing effects
and calls Enrollment's idempotent Public evaluation command in one database transaction,
passing the obligation id and expected effective deadline. Enrollment rejects a stale
deadline so all effects roll back and the caller reloads after any concurrent extension.
Repeated evaluation of the same already-evaluated checkpoint is a no-op. A valid unresolved
Incomplete defers the result and does not close the obligation.

The restored Course is represented during that window by a Remediation Obligation, not a
Must-Retake Obligation, because no Attempt need exist. It shares the Must-Retake
deadline-first Preparatory registration priority, timetable treatment, and full-capacity
exception path. It ends when the Course satisfies the requirement, when a replacement
Preparatory Exemption atomically closes it as `ReleasedByExemption`, or when its checkpoint
is evaluated; an unsatisfied result then enters termination evidence. The distinct release
outcome records corrected applicability evidence rather than Course completion. Its
academic-change event clears matching evidence from a non-final Case under normal
reconciliation; it cannot reverse a final termination.

## Considered options

- **Second Grade-Submission Deadline with Incomplete deferral (chosen).** Waits for ordinary
  finalized outcomes and preserves the already-granted Incomplete resolution period. It may
  defer a termination determination beyond the nominal second Semester.
- **Second Semester end date (rejected).** Enforces earlier, but acts before the normal
  grading deadline and can incorrectly flag a valid Incomplete.
- **Two offered opportunities per Course (rejected).** Avoids unavailable-Course cases but
  replaces the two-consecutive-Semester rule with a variable deadline that may never arrive.
- **Immediate trigger after a late Exemption Revocation (rejected).** Keeps the derivation
  simplest, but gives the Student no opportunity to complete a requirement restored after
  they relied on an approved decision. Prohibiting late Revocation would instead preserve
  an invalid Exemption. The one-off Remediation Window preserves both correction and due
  opportunity.

## Consequences

- `AcademicStanding` consumes Semester chronology and Grade-Submission Deadlines through
  `Calendar\Public`; it does not reconstruct Calendar rules.
- Before the checkpoint, Preparatory non-completion alone does not create Termination
  Pending. After it, the result is derived from effective academic facts and may change when
  a deferred Incomplete is resolved.
- A post-checkpoint Exemption Revocation contributes a per-requirement remediation
  deadline. Until it closes, that restored requirement alone is excluded from termination
  evidence even though it already contributes to Remaining CH. The active deadline follows
  authoritative Calendar extensions but is monotonic and cannot move earlier; the evaluated
  deadline is immutable.
- Without a schedulable enrollment opportunity, `AwaitingOffering` has no deadline and
  cannot become termination evidence. Offering changes are reconciliation triggers rather
  than a reason to invent a fixed date.
- Loss of an institution-provided opportunity before Enrollment triggers deterministic
  same-Semester replacement, later-Semester reassignment, or `AwaitingOffering`; the prior
  assignment remains audit history. Existing Enrollments are not unwound.
- With no scheduler in the localhost scope, elapsed checkpoints become workflow state when
  `academic-standing:reconcile` is run; Standing reads do not perform hidden writes.
- A determinate remediation evaluation atomically freezes the Enrollment-owned obligation
  with its AcademicStanding Case/Observation effects; optimistic deadline matching prevents
  a stale evaluation from defeating a concurrent extension.
- Termination evidence identifies the Remediation Obligation id as its checkpoint source
  and includes the effective academic facts observed at evaluation. A later Revocation
  creates a new obligation/evidence version; dismissal of an older fingerprint cannot
  suppress it forever.
- Replacement Exemption approval closes an active or `AwaitingOffering` obligation in the
  same Enrollment transaction. It does not wait for an Offering or checkpoint because the
  restored requirement no longer applies.
- Tests cover the instant before, at, and after the second grading deadline, plus passing,
  failing, and expired Incomplete outcomes, including repeated global and targeted
  reconciliation.
