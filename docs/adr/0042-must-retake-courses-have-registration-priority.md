# Must-retake Courses have registration priority when offered

**Status:** accepted

Phase 4 exposed a below-C+ Preparatory/Core outcome as a visible must-retake marker but
deliberately did not make it a registration gate. That was sufficient before Phase 5 had
the progress and Standing rules needed to reason about a Student's outstanding academic
obligations. It does not, however, satisfy the SRS statement that the Student **must**
retake the same Course: without a gate, the Student can repeatedly choose unrelated
Courses while postponing an available obligation.

An unconditional block is also wrong. A Student cannot register for a Course that is not
offered, and several simultaneously offered obligations can exceed the Student's dynamic
Semester load ceiling.

**Decision: offered must-retake obligations have priority over unrelated for-credit
registration.** A Must-Retake Obligation is an effective below-C+ outcome in a
Preparatory or Core Course that has not been cleared by a later successful Attempt. For
the target Semester, registration considers only obligations with an eligible Course
Offering:

- if no obligation is offered, the Student may register for other Courses normally;
- if all offered obligations fit within the Student's dynamic load ceiling, the
  post-change schedule must include all of them before any unrelated for-credit Course;
- if they exceed the ceiling, the post-change schedule may contain only offered
  obligation Courses, selected up to the ceiling; and
- Audit Enrollment never selects or clears an obligation.

Load-ceiling and timetable conflicts do not give the Student or Advisor a discretionary
choice among obligations. The system selects the required feasible set deterministically:
Preparatory obligations first by earliest applicable termination/remediation checkpoint,
then origin time (effective failed Grade or Exemption Revocation), then Course code; Core
obligations follow by oldest failed Attempt then Course code. It walks that order once and
includes each Course only when an
eligible Offering fits the remaining load and timetable. It never skips a feasible
higher-priority Course to maximize the number of Courses, recognized credit hours, or load
utilization; unused capacity or fewer selected obligations is an accepted result. That
decision is at Course level. For each required Course, the Student may choose any eligible,
non-conflicting Offering; the Advisor may change the Offering but cannot replace the
required Course.

“Offered” is independent of unrelated Courses the Student has already placed in a proposed
or approved schedule. During an open registration or modification window, a conflicting
unrelated Course must be removed or moved to another Offering so the required Course can
be selected. Conflicts between required Courses use the deterministic priority above.
After every applicable modification window closes, this rule never auto-drops an existing
Enrollment; an obligation that could not be added remains outstanding for the next
Semester in which it is offered.

A full eligible Offering still counts as available. The normal Advisor approval becomes
a recommendation, after which the Department Head scoped to the Course's Department may
give final approval to exceed capacity for that Must-Retake Enrollment only. The override
requires a reason, is written to the Audit Log, and is revalidated when approved. It never
applies to an unrelated Course and never displaces an existing Enrollment.

Authorization is deliberately a Department Head Role gate plus scope to the Course's
Department. It does not reuse `registration.approve`, because that Permission belongs to
Advisors and Student Affairs and would expose the ordinary registration queue if granted
to Department Heads. It does not add a dedicated Permission, because ADR-0008 fixes the
Permission set to the SRS matrix. The narrowly routed exception endpoint exposes only
Advisor-recommended full Must-Retake cases in the acting Department Head's scope.

The exception is explicit in the Registration Request state machine. Ordinary requests
move `Pending → Approved | Rejected`, with Pending displayed as “Pending Advisor.” Advisor
recommendation of a request needing the capacity exception moves it to
`AwaitingCapacityOverride`; the required per-Offering Department Head decisions then move
the request to `Approved | Rejected`. Enrollments are minted atomically only on `Approved`,
exactly as in the ordinary path; the new state is not a separate override aggregate.

Capacity authority follows each full Offering rather than the Student or batch. If one
request needs exceptions from several Departments, each Course's scoped Department Head
records an independent, reasoned decision for that Offering and cannot decide another
Department's capacity. The request remains `AwaitingCapacityOverride` until all approve.
Any denial rejects the unchanged atomic request; silently trimming the denied mandatory
Course would violate the priority rule. Unanimous approvals still undergo one final
validation before all Enrollments are minted atomically.

An active override requirement is conditional on the Offering remaining full. Enrollment
reconciles capacity changes idempotently. If a normal seat becomes available, that
Offering no longer needs an active exception; any decision already recorded remains in
the append-only history. When no active exception remains, the Advisor's recommendation
is sufficient and the system automatically approves only after transactionally
revalidating the entire batch, including capacity. No seat is reserved between the
capacity change and that transaction, so a lost race leaves the request awaiting the
still-required override rather than over-enrolling without approval.

Reconciliation is bidirectional. If another requested Offering becomes full while the
request waits, a new active requirement is added for that Offering and routed to its
scoped Department Head before final approval. A decision already recorded for the same
request and Offering remains part of the workflow and is never duplicated. Thus the
approval set may grow with current capacity, but completed reviews are not discarded
merely because another line changed.

A later successful Attempt clears the obligation. An effective Exemption can instead
release it by proving that the Preparatory requirement did not apply, even when corrected
after the failed Attempt. The Attempt and Grade remain in academic history and CGPA. A new
Transfer or Waiver request cannot target the failed Course and therefore cannot be used to
bypass this priority rule. If the failure becomes effective while such a request is under
review, an idempotent Grade-event handler terminally rejects the request, records the
system transition, and notifies the Student and current reviewers.

The rule applies to initial registration, free add, late add, and Advisor modification,
and is revalidated at the operation's final approval boundary. Normal minimum-load rules
and their approved exceptions still apply; priority changes which Courses must be chosen,
not the Student's load limits.

A distinct Remediation Obligation created by a post-checkpoint Preparatory Exemption
Revocation shares this validator, including deadline-first Preparatory priority, conflict
handling, and the full-Offering capacity-exception workflow. It does not assert that an Attempt
exists and ends when its restored requirement is satisfied, a replacement Exemption closes
it as `ReleasedByExemption`, or its ADR-0037 remediation checkpoint is evaluated.
`AwaitingOffering` has no active registration target; scheduling
a lawful add opportunity activates the shared priority rule idempotently.
Enrollment owns this obligation and its reconciliation, so the registration validator
reads local owner state and does not introduce an `Enrollment → AcademicStanding` edge
([ADR-0043](0043-enrollment-owns-remediation-obligations.md)).
If replacement Exemption approval releases the obligation while its Course belongs to a
`Pending` or `AwaitingCapacityOverride` request, Enrollment atomically rejects that whole
unchanged batch. It preserves all capacity decisions and transition history, notifies the
Student and current reviewers, and requires a new request rather than silently removing a
line whose absence may change load or timetable validity.
Once the request has already become approved Enrollments, replacement Exemption approval
does not auto-drop, withdraw, or convert the released Course. The obligation ends, but the
Student's existing Enrollment follows normal modification and grading rules.

Before any Enrollment exists, an institution-controlled change that eliminates the
qualifying remediation opportunity causes Enrollment to reconcile the assignment: another
eligible same-Semester Offering first, then the earliest later lawful opportunity, else
`AwaitingOffering`. Full capacity remains handled by the exception path and a
Student-created conflict does not postpone the checkpoint. Once enrolled, the opportunity
was used and later schedule changes do not reassign it.

## Considered options

- **Availability-aware priority gate (chosen).** Makes “must retake” enforceable without
  trapping a Student when the Course is unavailable or when all obligations cannot fit.
  It adds offering-aware validation and can limit schedule choice for a Semester.
- **Keep the Phase 4 marker advisory.** Preserves maximum Student choice and requires no
  new validation, but leaves the normative “MUST retake” rule unenforced and permits
  indefinite postponement.
- **Block all other registration until every obligation is cleared.** Is simplest to
  explain and strongest about remediation, but can prevent any progress when an obligated
  Course is not offered or when obligations exceed the load ceiling.
- **Treat a full Offering as unavailable (rejected).** Preserves the normal hard capacity
  rule and needs no exception workflow, but lets capacity delay mandatory remediation.
- **Automatically exceed capacity (rejected).** Guarantees access with less workflow, but
  removes accountable academic review of the operational impact.
- **Reserve or displace seats (rejected).** Can preserve the headline capacity, but needs
  advance forecasting or harms an already enrolled Student; neither is justified here.
- **Reuse or add a Permission (rejected).** Reusing `registration.approve` grants a
  Department Head excessive ordinary-registration access; adding a dedicated Permission
  makes a narrow, fixed-role policy artificially delegable and expands ADR-0008's matrix.
- **Keep the request generically Pending or create a separate override aggregate
  (rejected).** Generic Pending hides whose action is required; another aggregate
  preserves the old enum but adds synchronization for a single exceptional approval
  stage. `AwaitingCapacityOverride` makes the owner visible in the existing workflow.
- **Require a now-unnecessary override or reserve returned capacity (rejected).** Keeping
  the approval stage is simpler but wastes Department Head attention after the exception
  disappears. Reservation reduces races but introduces new seat-hold state; transactional
  revalidation handles the race without that model.
- **Freeze capacity at Advisor recommendation or reject on newly full lines (rejected).**
  Freezing is a disguised reservation; immediate rejection wastes valid reviews. Adding
  the newly necessary scoped approval preserves both current capacity policy and work
  already completed on other Offerings.

## Consequences

- Phase 5 deliberately amends Phase 4's “visible marker, not a gate” scope decision; the
  Phase 4 record remains historically accurate.
- The shared validator accepts both Must-Retake and Remediation Obligations, preserving
  their different causes and end conditions while applying one registration rule.
- Releasing a Remediation Obligation rejects any affected non-terminal Registration Request
  rather than rewriting its atomic batch. Previously recorded capacity decisions remain
  historical facts.
- Already-created Enrollments are not rewritten when the obligation is released. Normal
  drop, withdrawal, Attempt, and Grade rules continue to apply.
- Deadline-first ordering within Preparatory obligations prevents either cause from
  consuming capacity ahead of a requirement with an earlier academic consequence. Equal
  checkpoints use the originating Grade/Revocation timestamp and then Course code; Core
  retains oldest-failure ordering after all Preparatory obligations.
- Enrollment owns this registration rule. It uses its pushed `earned` outcome
  ([ADR-0022](0022-grade-outcome-pushed-to-enrollment.md)) and Catalog's eligible Offering
  data without introducing an `Enrollment → Grading` dependency.
- Validation must report which offered Must-Retake Obligations are missing so the UI can
  explain the blocked operation and deterministic Course priority in English and Arabic;
  it must not prescribe one exact Offering when several eligible sections satisfy the
  required Course.
- A full Must-Retake Offering introduces an exceptional Advisor-recommendation → scoped
  Department Head-final-approval path. Ordinary registration retains Advisor final
  approval, and no generic capacity override is created. The exceptional endpoint is
  Role-and-scope gated and contributes neither a Permission nor a general queue entry.
- Capacity decisions are stored per full Offering. Multi-Department batches remain one
  atomic Registration Request, so they may wait on several Department Heads and one denial
  rejects the whole batch.
- Capacity reconciliation may retire active override requirements but never deletes their
  history. With no exception remaining, successful whole-batch transactional revalidation
  moves the already-recommended request directly to Approved. A newly full requested
  Offering adds a requirement before approval without duplicating earlier decisions.
- API, UI, notification, export, and tests treat `Pending` and
  `AwaitingCapacityOverride` as distinct non-terminal states. The existing stored
  `Pending` value remains unchanged; its more specific “Pending Advisor” wording is a
  display label, so no status-data migration is needed.
- Final termination reuses `Rejected` for every non-terminal Registration Request through
  Enrollment's idempotent Public closeout command. Batch lines, capacity decisions, and
  transition history remain visible; approved Enrollments are not changed.
- Tests must cover no offered obligation, all obligations fitting, obligations exceeding
  the ceiling, timetable conflicts, stable greedy ordering, accepted unused capacity,
  refusal of lower-priority
  substitution, an unrelated conflict not disguising an offered obligation, no automatic
  drop after modification closes, full-Offering override scoping/reason/audit/no
  displacement, multi-Department unanimous approval and one-denial rejection, Audit not
  satisfying the rule, gained-capacity automatic approval, newly full Offering routing,
  decision deduplication and lost capacity races, later successful Attempt clearing it, and
  revalidation at every final approval boundary. They also prove that a
  later Exemption releases the obligation without erasing the Grade, while new
  Transfer/Waiver requests cannot target the failed Course and in-review requests are
  automatically rejected when the failure becomes effective.
