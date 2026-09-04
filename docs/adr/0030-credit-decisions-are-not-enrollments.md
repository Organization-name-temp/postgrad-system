# Exemptions, Transfers, and Waivers are not Enrollments

**Status:** accepted — supersedes the representation of `Transfer`, `Waive`, and
`Exempted` as Course Statuses in SRS §4.4.5 and Appendix D

An Enrollment means a Student taking a Course Offering in a Semester. An Exemption
releases a Preparatory requirement, a Transfer recognizes study at another institution,
and a Waiver recognizes prior study without requiring the Course to be taken again. None
has a PRS Course Offering, Instructor, meeting schedule, attendance record, or Grade Sheet.
Representing them as special Enrollments would therefore require invented or nullable
academic facts and weaken the domain meaning of Enrollment.

**Decision: Enrollment owns Exemptions, Transfers, and Waivers as three standalone,
course-targeted record types, not as Enrollments.** An Exemption targets a required
Preparatory Course; a Transfer maps a course studied outside E-JUST to an equivalent Course
in the Student's Curriculum; and a Waiver targets the Curriculum Course whose content a
prior E-JUST Course covers. External study is never eligible for a Waiver, so that workflow
cannot bypass the Transfer grade, credit-hour, or timing limits. At most one approved
Exemption, Transfer, or Waiver may satisfy a given
Curriculum Course for a Student. Transfer and Waiver approval cannot duplicate credit
already earned through an Enrollment. A corrected Preparatory Exemption is the narrow
exception: it may be approved after a successful Attempt because it records that the
requirement did not apply, not extra credit. The Credit-Hour Summary and Transcript compose
these records beside real Enrollments.

Credit decisions do not provide a general escape from a failed Course. Once a Student has
an effective failed for-credit Attempt in a Course, a new Transfer or Waiver request cannot
target that Course. A corrected Exemption is intentionally different: it proves that the
Preparatory requirement did not apply and may therefore release the requirement and its
Must-Retake Obligation after an Attempt. That release never deletes the Attempt or Grade,
which remains in academic history and CGPA.

If a finalized initial or correction Grade creates that failed Attempt while a Transfer
or Waiver request is under review, an idempotent handler terminally rejects the request.
The append-only transition identifies the Grade event as its cause, and the Student plus
the reviewers currently involved are notified. The request cannot remain in a queue or
race a final approval after its target becomes ineligible.

Manual workflow records are also kept separate from effective academic decisions. An
Approved `ExemptionRequest`, `TransferRequest`, or `WaiverRequest` atomically creates the
corresponding immutable decision and links it to that request; non-approved requests never
enter academic accounting. Automatic Exemptions create decisions directly and therefore do
not manufacture a Student request merely to share a table shape.

An effective decision is immutable but not irrevocable. The same final authority may append
a reasoned `Revocation`; the original decision is retained and stops contributing only
because that later fact exists. Replacement evidence takes a new request through the full
workflow. Automatic-Exemption reconciliation follows the same append-only rule rather than
deleting or mutating decisions.

Revoking a Preparatory Exemption after the Student's ordinary two-Semester completion
checkpoint restores the requirement immediately for credit accounting, but not as an
immediate termination trigger. The Student receives a Remediation Window through the
Grade-Submission Deadline of the first main Semester in which the Course has an eligible
Offering and the Student can still lawfully add it at Revocation time. The current
Semester qualifies only through late-add. During it, a distinct Remediation Obligation
shares the Must-Retake registration enforcement without falsely asserting a prior Attempt.
With no future eligible Offering, it remains `AwaitingOffering` without termination
eligibility until a real add opportunity is scheduled. Other termination evidence is
unaffected.
Enrollment owns that durable obligation beside the Revocation and exposes its state and
checkpoint through `Enrollment\Public`; AcademicStanding is a consumer, not a co-owner
([ADR-0043](0043-enrollment-owns-remediation-obligations.md)).
If a replacement Preparatory Exemption is approved while the obligation is active or
`AwaitingOffering`, the same Enrollment transaction closes it as
`ReleasedByExemption`. That terminal outcome records that corrected evidence removed the
requirement; it does not claim the Course was completed. The resulting academic-change
event lets AcademicStanding clear the exact trigger from any non-final Termination Case.
A final termination is never reversed by this later fact.
If the released Course appears in a `Pending` or `AwaitingCapacityOverride` Registration
Request, that same transaction terminally rejects the entire unchanged request with a
system-attributed reason. It preserves its transitions and all per-Offering capacity
decisions, then notifies the Student and current registration reviewers. Silently removing
the line could change load, timetable, and the submitted batch without consent.

Replacement Exemption final approval first acquires Identity's transaction-scoped Student
guard, then revalidates `Active` before changing Enrollment records. Final Council
termination uses the same guard and Identity-first lock order. Exemption-first clears the
evidence that Council must revalidate; termination-first makes approval ineligible. Neither
workflow may commit against the other's stale snapshot.

An already-created, in-progress Enrollment is different. Replacement Exemption approval
does not drop, withdraw, or convert it; the Exemption releases the requirement rather than
undoing the Student's academic commitment. The Student may use the ordinary drop or
withdrawal workflow while available, otherwise the Enrollment continues and its eventual
Attempt and Grade retain their normal history and CGPA effects. Raw Exempted and later
Earned CH may therefore coexist, while ADR-0035 excludes that Course's Earned CH from the
target-matched Satisfied operand before applying the category cap.

## Considered options

- **Standalone course-targeted records (chosen).** Each workflow records its own evidence
  and approvals without pretending that the Student attended a local Offering.
- **Synthetic Enrollments with special Course Statuses (rejected).** This reuses the
  Appendix-D matrix, but forces records with no genuine Offering or Semester through APIs
  and invariants built for courses actually taken at E-JUST.
- **One generic credit-adjustment record (rejected).** It reduces schema, but obscures the
  distinct evidence, eligibility rules, approval paths, and accounting effects of the
  three decisions.
- **Distinct `ReleasedByExemption` outcome (chosen).** Preserves why the remediation ended
  without pretending the Student completed the Course. A generic satisfied state is
  smaller but erases that distinction.
- **Reject an affected pending Registration Request (chosen).** Preserves the atomic batch
  and forces validation of a new schedule. Automatically removing the released Course is
  faster but can invalidate load/timetable rules or silently alter consent; retaining it
  creates ambiguous credit and Grade effects for an exempted requirement.
- **Preserve an existing in-progress Enrollment (chosen).** Respects the already-approved
  academic commitment and ordinary drop/withdrawal rules. Automatic removal is simpler for
  accounting but may discard study the Student wants; blocking corrected evidence delays a
  valid Exemption.
- **Permit a corrected Exemption after earned credit (chosen).** Records accurate
  applicability evidence without granting progress twice because satisfaction is
  target-aware. Applying the duplicate-credit rejection uniformly is simpler but makes a
  valid correction impossible after the unnecessary Course was completed.
- **Serialize with final termination by commit order (chosen).** Gives effect to the first
  valid transaction and rejects stale work. Unconditional Council or Exemption precedence
  would be deterministic but could discard a valid correction or unsupportedly terminate.

## Consequences

- `Transfer`, `Waive`, and `Exempted` leave the closed `CourseStatus` set and its counting
  matrix. The remaining statuses describe only real Enrollments.
- Enrollment exposes approved Exemptions, Transfers, and Waivers through its public facade
  for `AcademicStanding` and later Records; consumers do not read Enrollment tables.
- Public accounting facades expose effective decisions, not workflow requests. Review
  screens follow the request and its append-only transitions separately.
- Transcript placement is a rendering concern and does not turn one of these records into
  an Enrollment or Grade.
- Credit-Hour Summary reads treat a decision as effective only when it has no Revocation;
  history and audit surfaces show both facts.
- A post-checkpoint Preparatory Exemption Revocation records its remediation deadline.
  Credit accounting changes immediately, while termination eligibility for that restored
  requirement waits through the first offered main Semester that still presents a lawful
  add opportunity at Revocation time.
- Approval validates the target against the Student's Curriculum and rejects another
  approved decision for the target. Transfer and Waiver also reject an already-earned
  Enrollment; a corrected Preparatory Exemption may coexist with one, but its Course's
  Earned CH is excluded from target-matched satisfaction while effective.
- Replacement Preparatory Exemption approval atomically closes an active or
  `AwaitingOffering` Remediation Obligation as `ReleasedByExemption`. Subsequent
  AcademicStanding reconciliation clears matching non-final termination evidence but
  never reinstates a finally terminated Student.
- The same approval transaction rejects every non-terminal Registration Request containing
  the released Course. Rejection and notification handling are idempotent, and capacity
  reviews remain audit history rather than being deleted.
- The transaction never changes an already-created Enrollment. If it continues, its
  attendance, Attempt, and Grade remain ordinary academic facts; target-aware satisfaction
  allocation rather than destructive mutation prevents duplicate curriculum progress.
- Submission rejects a new Transfer or Waiver whose target already has an effective failed
  for-credit Attempt. A corrected Exemption may still target such a Preparatory Course or
  one already passed; approval releases its requirement without erasing the historical
  Attempt, Grade, raw Earned CH, or CGPA effect.
- Finalized initial and correction Grades can automatically reject an in-review Transfer
  or Waiver request. Event handling is idempotent, and the system transition and resulting
  notifications are retained for audit.
- Replacement Exemption approval and final termination acquire the Identity-owned Student
  guard first and revalidate under that lock; concurrency cannot produce both an approved
  correction and a final termination from the old evidence.
- Final termination invokes Enrollment's idempotent Public closeout command in its atomic
  transaction. Every non-terminal Credit Decision Request becomes system-rejected with its
  evidence and review history preserved; effective decisions remain immutable and
  unaffected.
- A Transfer retains source credit hours as evidence but recognizes the target Curriculum
  Course's hours. Final approval transactionally enforces the 9-CH lifetime cap over
  effective Transfers; Revocation removes its target hours from that total.
- A Waiver requires a prior E-JUST Course at level 400+ completed at C+ or higher and a
  Department Head-recorded coverage of at least 80%. An official E-JUST transcript may
  prove a legacy result absent from PRS; external study remains ineligible for Waiver.
