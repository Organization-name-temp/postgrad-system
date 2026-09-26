# Spec — Phase 5: Academic Standing, Credit-Hour Summary, and Credit Decisions

**Status:** complete
**Feature slug:** phase-5
**Phase:** 5 of the Build Roadmap
**Scope:** SRS §4.5, §4.6, and §4.8; BR-31–43, BR-46, BR-56, and BR-60–62
**Primary decisions:** ADR-0029 through ADR-0043

---

## Problem Statement

The system can admit Students, enroll them in Course Offerings, finalize Grades, and calculate
GPA/CGPA, but it cannot yet explain whether a Student is progressing through their particular
Curriculum, apply prior-learning decisions, enforce mandatory remediation, classify Academic
Standing, or carry a possible termination through due process.

This leaves Students and academic staff without answers to consequential questions:

- Which Curriculum requirements have been satisfied, and how many credit hours remain in each
  category?
- Does an Exemption, Transfer, or Waiver apply, who must review it, and what happens if the
  decision is later corrected?
- Which failed Preparatory or Core Courses must be taken next, especially when load, timetable,
  or capacity constraints prevent taking every obligation?
- Is the Student in Good Standing, Academic Warning, Academic Probation, or Termination Pending?
- Is the Student academically ready to graduate, even though formal graduation is a later
  administrative workflow?
- When termination evidence exists, how is it reviewed without stale evidence, duplicate Cases,
  conflicting decisions, or partial lifecycle changes?

Several of these answers cross module boundaries. A solution that copies Grades, Curriculum
facts, Calendar dates, or Enrollment state would drift. A solution that stores derived Standing
or progress as authority would become stale. The phase therefore needs narrow Public contracts,
derived read models, durable workflow records only where history and concurrency require them,
and explicit recovery paths because this localhost build has no scheduler or durable queue.

## Solution

Build Phase 5 as one vertical academic-progress capability.

A new `AcademicStanding` module will calculate the Student's Credit-Hour Summary, Academic
Standing, Approaching Graduation result, and advisory Graduation Readiness from authoritative
Public data owned by Identity, Catalog, Enrollment, and Grading. Those values remain derived;
append-only Standing Observations record only meaningful changes and once-only notification
checkpoints.

Enrollment will own standalone Exemption, Transfer, and Waiver requests and their immutable
effective decisions, rather than representing prior learning as fake Enrollments. It will also
own Must-Retake and Remediation Obligations because they govern Course Offering selection,
registration priority, timetable handling, and capacity exceptions. Admissions will publish an
immutable acceptance-evidence snapshot so automatic Preparatory Exemptions can be created
idempotently before first registration.

Identity will bind each Student to the Curriculum Version effective at admission and own the
Student Lifecycle Status. Catalog will give Preparatory requirements semantic Roles. Calendar
and Catalog will publish minimal ID-only change signals; consumers will reread authoritative
Public DTOs instead of trusting copied event payloads.

Termination will be a durable, evidence-versioned workflow owned by AcademicStanding. One
non-terminal Termination Case per Student aggregates all actionable triggers and proceeds through
Advisor assessment, Department Head recommendation or dismissal, and Academic Affairs Council
final decision. Reconciliation will close cleared or changed evidence safely. Final termination
will atomically update Identity lifecycle state and reject open Enrollment-owned requests without
deleting their history or rewriting existing Enrollments and effective decisions.

The React SPA will expose Student, Advisor, Department Head, Council, Student Affairs, and
administrator views using generated OpenAPI types and hooks. All user-facing behavior will be
bilingual, direction-safe, permission-aware, and built from the existing design system.

## User Stories

### Student — progress and readiness

1. As a Student, I want to see the Curriculum Version to which I was admitted, so that later Catalog changes do not silently alter my degree.
2. As a Student, I want a Credit-Hour Summary by Curriculum category, so that I understand my progress instead of seeing only a single total.
3. As a Student, I want each category to show Baseline Required, Adjusted Required, Attended, Earned, Transferred, Waived, Exempted, Satisfied, and Remaining CH, so that every contribution is transparent.
4. As a Student, I want the Total row to reconcile with its category rows, so that the summary can be trusted.
5. As a Student, I want Satisfied CH capped at the Adjusted Required amount and Remaining CH never below zero, so that surplus study does not create impossible negative requirements.
6. As a Student, I want Course-targeted progress allocated before category caps, so that Exemption and Earned CH for the same Course do not count twice.
7. As a Student, I want raw Earned CH and the CGPA effect of a completed Course preserved even when a corrected Exemption later releases that Course requirement, so that academic history remains truthful.
8. As a Student, I want failed electives to remain in my Grade and CGPA history after a different elective satisfies the Curriculum slot, so that requirement satisfaction is not confused with Grade supersession.
9. As a Student, I want to see my current exhaustive Academic Standing, so that I never have to infer it from CGPA and Grades.
10. As a Student with no finalized CGPA-bearing Grade, I want to see Not Yet Assessed unless a termination trigger is active, so that absence of evidence is not presented as Good Standing.
11. As a Student below a 3.00 CGPA, I want to see Academic Probation, so that the registration consequence is clear.
12. As a Student at or above 3.00 with an effective Grade below C+, I want to see Academic Warning, so that poor Grade history remains visible even when my CGPA is adequate.
13. As a Student at or above 3.00 with no effective Grade below C+, I want to see Good Standing, so that the classification reflects both CGPA and Grade history.
14. As a Student with an actionable open Termination Case, I want Termination Pending to take precedence, so that the active due-process state is visible even without a CGPA.
15. As a Student, I want adverse and recovery Standing changes notified once, so that reconciliation retries do not spam me.
16. As a Student, I want Academic Warning alone not to block Graduation Readiness, so that Good Standing is not invented as an extra graduation requirement.
17. As a Student, I want an open actionable Termination Case to block Graduation Readiness temporarily, so that readiness reflects unresolved termination risk.
18. As a Student, I want dismissal or Trigger Cleared to remove that temporary blocker immediately, so that stale workflow state does not hide readiness.
19. As a Student, I want Graduation Readiness to show Remaining CH, nullable CGPA, Approaching Graduation, and typed blockers, so that I know exactly what remains.
20. As a Student, I want Approaching Graduation evaluated independently from CGPA and Termination Pending, so that curriculum proximity remains visible alongside separate blockers.
21. As a Student with 1–5 Remaining CH, I want a below-minimum schedule allowed only when it satisfies every remaining target or slot, so that the exception completes rather than merely reduces my requirements.
22. As a first-semester Student with 1–5 non-exempt Preparatory CH remaining, I want a below-minimum schedule allowed only when it includes all of them, so that the exception is narrow and predictable.
23. As a first-semester Student with no remaining Preparatory requirements, I want the normal minimum load to apply, so that Exemption alone does not create a generic underload privilege.

### Student — Exemption, Transfer, and Waiver

24. As an accepted Student whose admission evidence satisfies a Preparatory exemption rule, I want the Exemption applied automatically before first registration, so that I am not asked to study an inapplicable requirement.
25. As a Student whose automatic Exemption was missed, I want reconciliation to produce the same decision exactly once, so that an event-delivery failure does not permanently change my record.
26. As a Student, I want to submit corrected or missing evidence for a manual Preparatory Exemption, so that an admission-time omission can be repaired.
27. As a Student, I want a corrected Exemption allowed even after a failed or successful Attempt, so that applicability evidence can be corrected without erasing study history.
28. As a Student, I want to create and edit a Draft credit-decision request before submission, so that incomplete evidence is not prematurely reviewed.
29. As a Student, I want a returned request to become editable with the reviewer's reason visible, so that I can address the actual deficiency.
30. As a Student, I want resubmission to invalidate earlier approvals while retaining them in history, so that reviewers decide the revised evidence.
31. As a Student, I want submitted requests locked from editing, so that reviewers and I see the same evidence.
32. As a Student, I want Approved and Rejected requests to be terminal, so that finalized review history cannot be rewritten.
33. As a Student, I want an effective academic decision separate from its request, so that workflow history and current academic effect are not conflated.
34. As a Student, I want a reasoned Revocation appended instead of an approved decision being edited or deleted, so that corrections remain auditable.
35. As a Student, I want any replacement after Revocation to follow the full workflow, so that a revoked decision does not silently return.
36. As a Student transferring prior external study, I want to request recognition only within the inclusive calendar-month window beginning at admission acceptance, so that the deadline is deterministic.
37. As a Student, I want Transfer eligibility to require an external Grade of B or higher and verified equivalency, so that weak or unrelated prior study is not recognized.
38. As a Student, I want the external Grade preserved exactly as printed while the Department Head records the normalized E-JUST Letter Grade, so that evidence and the local eligibility decision are both visible.
39. As a Student, I want the lifetime Transfer limit to use the target Curriculum Course hours and never exceed 9 CH, so that source-institution hour differences cannot bypass the cap.
40. As a Student, I want Revocation to free previously recognized Transfer CH, so that the lifetime cap reflects only effective decisions.
41. As a Student seeking a Waiver, I want only prior E-JUST Courses at level 400 or above with at least C+ and 80% verified content coverage to qualify, so that Waiver policy is enforced consistently.
42. As a Student with a legacy E-JUST result absent from PRS, I want an official transcript accepted as evidence, so that older valid study remains usable.
43. As a Student with external prior study, I want it routed through Transfer rather than Waiver, so that external study cannot bypass Transfer limits.
44. As a Student, I want a new Transfer or Waiver request blocked after an effective failed Attempt in the target Course, so that prior learning cannot erase a known failure.
45. As a Student with a Transfer or Waiver under review when a failed Grade becomes effective, I want the request rejected with an attributed system transition and notification, so that reviewers do not approve stale eligibility.
46. As a Student, I want Exemption, Transfer, and Waiver decisions excluded from CGPA, so that prior-learning recognition never manufactures Grade points.

### Student — mandatory remediation and registration

47. As a Student with an effective below-C+ Preparatory or Core outcome, I want a Must-Retake Obligation created, so that the Course cannot be postponed indefinitely.
48. As a Student with offered obligations, I want all feasible required Courses selected before unrelated for-credit Courses, so that remediation has real priority.
49. As a Student whose obligations cannot all fit, I want the required feasible set chosen deterministically, so that equal facts produce equal registration requirements.
50. As a Student, I want Preparatory obligations ordered by earliest checkpoint, origin time, then Course code before Core obligations, so that the most consequential work comes first.
51. As a Student, I want Core obligations ordered by oldest failed Attempt then Course code, so that long-standing failures are handled first.
52. As a Student, I want a feasible higher-priority Course never skipped merely to optimize Course count or CH, so that the policy cannot be gamed.
53. As a Student, I want to choose any eligible non-conflicting Offering for each required Course, so that priority does not remove all timetable choice.
54. As a Student, I want my Advisor able to switch the Offering but not remove the required Course, so that advising can resolve scheduling details without bypassing remediation.
55. As a Student, I want other registration allowed when no obligation is offered, so that unavailable remediation does not halt all progress.
56. As a Student, I want an unrelated timetable conflict removed or switched while modification is open rather than used to declare an obligation unavailable, so that self-created conflicts cannot defer priority.
57. As a Student, I want no Enrollment auto-dropped after the modification window closes, so that reconciliation does not rewrite an established schedule.
58. As a Student, I want an unselected obligation carried forward when it could not lawfully be added, so that it is neither lost nor applied retroactively.
59. As a Student, I want Audit Enrollment neither to satisfy nor clear an obligation, so that non-credit attendance is not treated as remediation.
60. As a Student needing a full required Offering, I want my Advisor able to recommend a capacity exception, so that capacity alone does not delay mandatory remediation.
61. As a Student, I want each full Offering's own Department Head to decide its capacity exception with a reason, so that operational impact has accountable local review.
62. As a Student, I want a multi-Department request to wait for every required approval and be rejected unchanged if any is denied, so that my atomic batch is never silently trimmed.
63. As a Student, I want Enrollments created only after unanimous approval and final whole-batch revalidation, so that reviews do not bypass current load, timetable, or capacity rules.
64. As a Student, I want a returned normal seat to retire the active exception requirement while preserving its history, so that obsolete approval work does not block registration.
65. As a Student, I want a newly full Offering to gain its own exception requirement while the request waits, so that current capacity is enforced without discarding completed reviews.
66. As a Student, I want capacity reconciliation not to reserve or displace seats, so that existing Enrollments remain protected.

### Remediation after Exemption Revocation

67. As a Student whose Preparatory Exemption is revoked after the ordinary checkpoint, I want a distinct Remediation Obligation rather than an invented failed Attempt, so that the record states what actually happened.
68. As a Student, I want the remediation window to use the first main Semester in which an eligible Offering exists and I can still lawfully add it, so that I receive a real opportunity before termination evidence can arise.
69. As a Student, I want the current Semester considered only through late-add, so that an already closed registration window is not called an opportunity.
70. As a Student with no future eligible Offering, I want the obligation marked Awaiting Offering without a termination deadline, so that institutional unavailability cannot terminate me.
71. As a Student in Awaiting Offering, I want Remaining CH and readiness updated and the relevant people notified, so that the restored requirement remains visible.
72. As a Student, I want a newly available opportunity to activate the obligation idempotently, so that repeated events do not duplicate deadlines or notifications.
73. As a Student, I want an active remediation deadline extended by a later Grade-Submission Deadline but never shortened by an earlier Calendar edit, so that time already granted cannot be withdrawn.
74. As a Student, I want both the official and retained effective dates preserved and notified on a real change, so that the deadline history is auditable.
75. As a Student, I want an evaluated deadline frozen, so that later Calendar edits cannot rewrite the evidence used for a decision.
76. As a Student, I want institution-controlled loss of an unused qualifying opportunity to choose another same-Semester Offering, then the earliest later lawful opportunity, or Awaiting Offering, so that I retain a fair chance.
77. As a Student, I want full capacity handled by the exception workflow and my own conflicts excluded from postponement, so that availability rules remain consistent.
78. As a Student already enrolled for remediation, I want later schedule changes not to reassign the opportunity, so that used opportunities remain historical fact.
79. As a Student with a valid unresolved Incomplete at the checkpoint, I want evaluation deferred until resolution or expiry, so that unfinished Grading work is not treated as failure.
80. As a Student whose replacement Exemption is approved, I want the obligation closed as Released By Exemption, so that corrected applicability is distinguished from Course completion.
81. As a Student whose released Course is in a Pending or Awaiting Capacity Override request, I want the whole unchanged request rejected with its history retained, so that no unconsented schedule is manufactured.
82. As a Student already enrolled in that Course, I want the Enrollment left under ordinary drop, withdrawal, and grading rules, so that an approved schedule is not rewritten automatically.

### Advisor, Department Head, Council, and Student Affairs

83. As an Advisor, I want to review manual Exemption, Transfer, and Waiver submissions for my current advisees, so that academic guidance follows live assignment.
84. As an Advisor, I want credit-request and termination queues to follow Advisor reassignment, so that former Advisors lose future authority.
85. As a Department Head, I want to make the final manual Exemption decision for Courses in my scope, so that the fixed role model represents departmental authority.
86. As a Department Head, I want to verify Transfer equivalency and normalized Grade before Council review, so that the Council receives a complete academic recommendation.
87. As a Department Head, I want to verify Waiver level and content coverage before Council review, so that the minimum policy evidence is explicit.
88. As an Academic Affairs Council member, I want to make the final Transfer and Waiver decision, so that university-level recognition receives university-level authority.
89. As the relevant final authority, I want to revoke an effective decision only with a reason, so that correction power remains narrow and attributable.
90. As a Department Head, I want access only to Advisor-recommended full required Offerings in my Course scope, so that capacity authority does not expose the ordinary registration queue.
91. As an academic reviewer, I want every workflow action attributed and retained, so that returned, superseded, rejected, dismissed, and revoked decisions remain auditable.
92. As a Student Affairs user, I want authorized oversight views of Student progress and credit workflows, so that I can answer academic-record enquiries without bypassing backend scope rules.

### Termination due process

93. As a Student, I want Preparatory non-completion evaluated only after the second consecutive main Semester's Grade-Submission Deadline, subject to a valid Incomplete, so that the checkpoint is objective and fair.
94. As a Student, I want English treated as one semantic Preparatory requirement rather than a second independent condition, so that the Curriculum model remains coherent.
95. As a Student, I want the Grade trigger to require effective D or F Grades in at least two distinct Courses, so that D+ and repeated attempts in one Course do not qualify incorrectly.
96. As a Student, I want a successful later Attempt in the same Course to clear that Course's Grade trigger, so that remediation affects the current evidence.
97. As a Student, I want a successful different elective not to supersede the failed Course's Grade, so that Grade history and Curriculum satisfaction remain separate.
98. As a Student, I want at most one non-terminal Termination Case aggregating all actionable triggers, so that I never face conflicting parallel termination workflows.
99. As a Student, I want the Case evidence frozen into a deterministic fingerprint, so that reviewers decide a precise and reproducible set of facts.
100. As a Student, I want Preparatory fingerprints to identify the requirement, checkpoint source, and effective Grade, Withdrawal, decision, and Revocation evidence including no Attempt, so that material changes are detectable.
101. As a Student, I want Department Head or Council dismissal to suppress only the exact reviewed fingerprint, so that unchanged evidence cannot reopen immediately but new evidence can receive fresh due process.
102. As a Student, I want cleared triggers to close the Case as Trigger Cleared before final decision, so that stale evidence cannot reach termination.
103. As a Student, I want changed but still-actionable evidence to close the old Case as Evidence Changed and open a fresh Advisor-stage Case, so that earlier reviews are not applied to different facts.
104. As a Student, I want old reviews retained as history but not transferred to the new Case, so that accountability is preserved without stale authority.
105. As an Advisor, I want to assess and recommend on a newly flagged Case for my current advisee, so that the closest academic relationship begins due process.
106. As a newly assigned Advisor, I want an unassessed Case transferred into my queue and removed from the former Advisor's authority, so that current assignment controls pending work.
107. As a reviewer, I want an assessment completed before Advisor reassignment to remain valid and attributed, so that later Department Head or Council work does not restart unnecessarily.
108. As a Department Head, I want to review the Advisor assessment and either recommend termination or dismiss the Case for the Student's bound Program Department, so that departmental review cannot be bypassed.
109. As an Academic Affairs Council member, I want to terminate or dismiss only Cases that completed both earlier stages, so that final authority receives full due process.
110. As a Student, I want final termination and corrected replacement Exemption approval serialized against one Student guard with post-lock revalidation, so that stale outcomes cannot both commit.
111. As a Student, I want Exemption-first to clear the evidence and termination-first to block the later academic mutation, so that commit order is authoritative.
112. As a Student, I want final termination to set my lifecycle to Terminated while retaining the Student Role and historical read access, so that the record remains accessible without permitting new academic mutations.
113. As a Student, I want final termination to system-reject every non-terminal Registration and Credit Decision Request while preserving its evidence and review history, so that no open academic mutation survives termination.
114. As a Student, I want existing Enrollments and effective academic decisions left unchanged by final termination, so that the historical academic record is not rewritten.
115. As a Student, I want final termination never reversed automatically, so that reinstatement requires a future explicit policy rather than an inferred side effect.

### System operation and developer experience

116. As an operator, I want a global or per-Student Academic Standing reconciliation command, so that elapsed checkpoints and missed events can be repaired without a scheduler.
117. As an operator, I want a global or per-Student remediation reconciliation command, so that Offering, Course, and Calendar event failures can be repaired deterministically.
118. As an operator, I want repeated reconciliation to be idempotent, so that safe retries create no duplicate Cases, decisions, transitions, Observations, or notifications.
119. As a developer, I want Standing and readiness GET requests to be side-effect free, so that reads cannot unexpectedly mutate academic history.
120. As a developer, I want AcademicStanding to consume owner Public DTOs rather than another module's models or tables, so that module boundaries remain enforceable.
121. As a developer, I want Enrollment to own Remediation Obligations and expose their immutable DTOs, so that registration can enforce local state without an Enrollment-to-AcademicStanding dependency.
122. As a developer, I want determinate remediation evaluation to update the obligation and Case/Standing effects atomically with an expected-deadline guard, so that a concurrent extension causes rollback and reload rather than stale termination evidence.
123. As a developer, I want Admissions, Calendar, and Catalog events to carry immutable evidence or owner IDs as appropriate, so that event consumers do not depend on mutable internal models.
124. As a developer, I want list endpoints to use the existing paginated, filterable-iff-readable query contract and audited export sibling, so that Phase 5 screens inherit the reporting platform consistently.
125. As a user, I want Academic Standing and Credit-Hour Summary Dashboard Cards, so that the most important progress facts are visible without opening a report.
126. As an Arabic-speaking user, I want every new message, screen, status, notification, and validation error in Arabic with correct RTL behavior, so that Phase 5 preserves the bilingual contract.
127. As an authorized user, I want API and UI access governed by the existing Roles, Permissions, and scope rules, so that frontend visibility never substitutes for backend authorization.

## Implementation Decisions

### Ownership and dependency direction

- Introduce `AcademicStanding` as a deep module. It owns the Credit-Hour Summary,
  Academic Standing, Graduation Readiness, Standing Observations, and Termination Cases.
  It derives current read models from Public DTOs and does not own copied Enrollment,
  Curriculum, Grade, or Calendar facts.
- Enrollment owns Exemption, Transfer, and Waiver requests; effective decisions and
  Revocations; Must-Retake Obligations; Remediation Obligations; and capacity-exception
  workflow state. These facts directly govern registration and therefore stay beside the
  Enrollment rules that consume them.
- Identity owns the Student's admission Curriculum Version binding, Student Lifecycle
  Status, and transaction-scoped per-Student guard. Cross-module workflows use its Public
  contracts; no Eloquent model crosses a module seam.
- Catalog owns semantic Preparatory Requirement Roles and publishes an ID-only Course
  activation change signal. Calendar publishes an ID-only Semester change signal.
  Consumers reread the authoritative Public DTO after receiving either signal.
- Grading remains the authority for effective Grades, CGPA, and Incompletes. AcademicStanding
  reads those facts and never converts or edits an Incomplete.
- The dependency remains one-way from AcademicStanding to provider Public contracts and
  from Admissions/Calendar/Catalog signals into Enrollment listeners. Enrollment never
  reads AcademicStanding state.

### Curriculum binding and Preparatory semantics

- A Student is bound at admission to the latest Curriculum Version effective at or before
  admission. Existing data falls back visibly and audibly to the earliest available Version
  only when Catalog history begins later than admission.
- Automatic Exemption rules target six semantic Preparatory Requirement Roles, including
  the admitted Program's departmental Key Skills Entry, rather than hard-coded Course IDs.
- Applications capture structured previous E-JUST Program evidence. The MSc-to-PhD rule
  requires the previous and admitted Programs to belong to the same Department.

### Credit-Hour Summary and readiness

- For every Curriculum category, expose Baseline Required, Adjusted Required, Attended,
  Earned, Transferred, Waived, Exempted, Satisfied, and Remaining CH plus a Total row.
- Raw operands remain visible. Satisfied CH is target-aware: effective non-earned credit
  decisions satisfy their target first, Earned CH for the same released target is excluded
  from the Satisfied operand, and only then is the category result capped at Adjusted
  Required. Remaining CH is floored at zero.
- Credit decisions never affect CGPA. A corrected Exemption after an Attempt preserves that
  Attempt, its effective Grade, raw Earned CH, and CGPA effect.
- Graduation Readiness is advisory and derived. It requires all Curriculum requirements
  satisfied, CGPA at least 3.00, and no actionable Termination Pending Case. It does not
  change lifecycle state or perform formal graduation.
- Its Public result returns a boolean, Remaining CH, nullable CGPA, Approaching Graduation,
  and a closed typed blocker set: Remaining Requirements, CGPA Unavailable, CGPA Below
  Minimum, and Termination Pending. Consumers do not reconstruct the result.
- Approaching Graduation means 1–5 Remaining CH and a proposed for-credit schedule that
  satisfies every remaining target or slot. It is independent of CGPA and termination.
- The first-semester underload exception is limited to the Student's enrollment Semester,
  1–5 CH, and a schedule containing every remaining non-exempt Preparatory requirement.
  With no such requirements, the ordinary minimum applies.

### Academic Standing and Observations

- Academic Standing is exhaustive and evaluated in this order: actionable Termination
  Pending; otherwise Not Yet Assessed when no finalized CGPA-bearing Grade exists;
  Academic Probation below 3.00; Academic Warning at or above 3.00 with any effective Grade
  below C+; otherwise Good Standing.
- A successful later Attempt supersedes the earlier effective Grade only for the same
  Course. Satisfying an elective slot with a different Course does not supersede the failed
  Grade for CGPA, Warning, or D/F termination evidence.
- Academic Probation and Termination Pending cap total Semester load, including Audit, at
  the configured minimum. The approved approaching-graduation and first-semester exceptions
  remain available. Only final termination blocks academic mutations.
- Standing Observations are append-only records of actual derived transitions used for
  history and once-only notifications. They are not stored Standing snapshots and never
  answer current reads.
- Event handlers and `academic-standing:reconcile {student?}` invoke the same idempotent
  per-Student service. The global command evaluates all active Students; a scoped command
  repairs one Student. GET endpoints remain side-effect free.

### Credit-decision workflows

- Automatic Preparatory Exemptions are immutable effective decisions created idempotently
  from a Public `AdmissionAccepted` evidence snapshot. A per-Student reconciliation command
  recovers missed post-commit handling.
- Manual Exemption workflow is Student submission, Advisor recommendation, then scoped
  Department Head final decision. Transfer and Waiver add Department Head verification and
  Academic Affairs Council final decision.
- Requests begin as editable Drafts, lock on submission, may be Returned for revision, and
  retain append-only transition and review history. Resubmission invalidates previous review
  authority. Approved and Rejected are terminal request states.
- Final approval atomically creates a separate immutable academic decision. Only effective
  decisions feed progress. Corrections append a reasoned Revocation by the same final
  authority; decisions are never edited or deleted, and replacement uses a new request.
- Transfer uses an inclusive calendar-month submission window beginning at admission,
  requires an external Grade of at least B and verified equivalency, preserves the printed
  source Grade, and records a Department Head-normalized E-JUST Letter Grade.
- The 9-CH Transfer cap is lifetime and transactionally revalidated at final approval using
  target Curriculum Course hours. Source hours remain evidence only; Revocation frees the
  recognized target hours.
- Waiver requires prior E-JUST study at Course level 400 or above, Grade C+ or higher, and
  Department Head-verified content coverage of at least 80%. An official legacy E-JUST
  transcript is valid evidence; external study must use Transfer.
- A new Transfer or Waiver cannot target a Course after an effective failed Attempt. If
  failure becomes effective during review, an idempotent Grade-event handler rejects the
  request with an attributed system transition and notifies the Student and current
  reviewers. Exemption alone may correct applicability after any Attempt outcome.

### Obligations, priority, and capacity

- A below-C+ effective Grade in a Preparatory or Core Course creates a Must-Retake
  Obligation. A post-checkpoint Preparatory Exemption Revocation creates a distinct
  Remediation Obligation without implying an Attempt.
- Both obligation types use one availability-aware registration validator. All offered
  obligations must be selected when feasible. When not all fit, it greedily processes
  Preparatory obligations by earliest checkpoint, origin time, then Course code, followed
  by Core obligations by oldest failed Attempt then Course code. It includes an obligation
  only when an eligible Offering fits remaining load and timetable, and never skips a
  feasible higher-priority Course to optimize another measure.
- The Student chooses the eligible non-conflicting Offering for each required Course; the
  Advisor may change only that Offering. An unrelated Student-created conflict does not make
  the obligation unavailable. After modification closes, no Enrollment is auto-dropped and
  an unselected obligation carries forward. Audit never satisfies an obligation.
- A full required Offering remains available through a narrow capacity-exception workflow.
  Advisor recommendation moves the atomic Registration Request from Pending to Awaiting
  Capacity Override. Each full Offering's scoped Department Head records an independent,
  reasoned, audited decision. Any denial rejects the unchanged batch; unanimous approval
  performs final whole-batch revalidation before minting all Enrollments atomically.
- Department Head capacity authority is Role plus Course-department scope. It neither uses
  the ordinary registration approval Permission nor exposes the ordinary queue.
- Capacity reconciliation is bidirectional and idempotent. A returned normal seat retires
  the active requirement while preserving history and may auto-approve after complete
  transactional revalidation. A newly full line gains its own scoped requirement without
  duplicating a prior decision. No seat is reserved or displaced.

### Remediation lifecycle

- A post-checkpoint Revocation initially selects the first main Semester with an eligible
  Offering in which the Student can lawfully add the Course; the current Semester counts
  only through late-add. Otherwise the durable obligation is Awaiting Offering and has no
  termination deadline.
- Enrollment reconciles local Offering changes synchronously and consumes Calendar and
  Catalog ID-only events. `enrollment:reconcile-remediation {student?}` uses the same service
  globally or per Student as a recovery mechanism.
- An unevaluated active deadline follows a later Grade-Submission Deadline but never an
  earlier edit. Each real change appends both official and retained effective dates and
  sends notifications. Evaluation freezes the deadline.
- Before Enrollment, institution-controlled loss of the qualifying opportunity selects
  another eligible same-Semester Offering, then the earliest later lawful opportunity,
  else Awaiting Offering. Full capacity and Student-created conflicts do not qualify. An
  existing Enrollment proves the opportunity was used and freezes assignment.
- A determinate checkpoint evaluation invokes Enrollment's idempotent Public command with
  obligation ID and expected effective deadline in the same transaction as Case/Standing
  effects. Exact duplicate evaluation is a no-op; deadline mismatch rolls back all effects
  for reload. A valid unresolved Incomplete remains indeterminate and active.
- A replacement Exemption closes an active or Awaiting Offering obligation as Released By
  Exemption. In the same transaction it rejects affected Pending or Awaiting Capacity
  Override Registration Requests unchanged, preserves their review/capacity history, and
  notifies the Student and current reviewers. Existing Enrollments continue normally.

### Termination workflow and concurrency

- The ordinary Preparatory trigger is evaluated after the second consecutive main
  Semester's Grade-Submission Deadline. A valid unresolved Incomplete defers determination.
- The Grade trigger requires current effective D or F Grades in at least two distinct
  Courses. D+ does not qualify; multiple Attempts of one Course count once; a successful
  later Attempt in that Course clears it.
- Each Student has at most one non-terminal Termination Case under a transactional database
  invariant. It aggregates every currently actionable Preparatory and D/F component.
- The deterministic fingerprint contains each Preparatory requirement ID, ordinary or
  remediation checkpoint identity, and current effective Grade, Withdrawal, Exemption,
  and Revocation evidence IDs, including explicit no-Attempt; D/F components contain Course
  and effective Grade IDs. Input ordering cannot affect the fingerprint.
- Workflow states distinguish waiting for Advisor assessment, Department Head review,
  Council decision, and terminal outcomes including Dismissed, Trigger Cleared, Evidence
  Changed, and Terminated.
- Advisor and Department Head share the existing recommendation Permission but receive
  transition-specific Role authority. The current Advisor assesses; the scoped Department
  Head recommends or dismisses; only then may Council terminate or dismiss.
- Pending Advisor scope is resolved live from Identity. Reassignment transfers an
  unassessed Case and its action authority. A completed assessment remains immutable,
  attributed, and valid, so later-stage review does not restart. Later notifications target
  the current Advisor.
- Reconciliation closes a Case as Trigger Cleared when no trigger remains. When actionable
  evidence changes, it atomically closes the old Case as Evidence Changed and opens a new
  Advisor-stage Case. Reviews remain history but do not transfer. Neither fingerprint is
  suppressed by Evidence Changed.
- Department Head or Council dismissal suppresses only the exact fingerprint reviewed.
  Unchanged evidence cannot reopen immediately; later authoritative evidence may produce a
  new Case. Dismissed evidence is no longer actionable for Standing or readiness.
- Final termination and replacement Exemption approval acquire Identity's transaction-
  scoped Student guard before other module locks and revalidate after locking. Exemption-
  first clears the evidence; termination-first blocks the academic mutation. Partial or
  stale outcomes cannot commit.
- Final Council termination atomically records the Case decision, changes lifecycle to
  Terminated, and invokes Enrollment's idempotent request-closeout command. Every
  non-terminal Registration and Credit Decision Request becomes system-rejected with its
  cause and history preserved. Existing Enrollments and effective decisions do not change.
- A Terminated Student retains the Student Role and historical reads but cannot perform new
  academic mutations. No automatic reinstatement exists.

### API, lists, frontend, audit, and localization

- Public HTTP resources expose owner-composed Credit-Hour Summary, Standing, readiness,
  obligations, credit workflows, and termination workflows. OpenAPI is regenerated before
  the generated frontend client is consumed; generated artifacts are never hand-edited.
- Every Phase 5 list starts on the existing Support query contract: paginated envelopes,
  actor-aware filterable-iff-readable fields, and an audited export sibling. Derived filter,
  sort, and aggregate values come from owner-local batch Public contracts.
- Add an `academic-standing` frontend feature for Student progress/Standing/readiness and
  role-scoped termination work. Extend Enrollment screens for credit-decision requests,
  obligations, required Offering choice, and capacity decisions. App composition happens
  only in the app layer; features do not import each other.
- Ship Academic Standing and Credit-Hour Summary Dashboard Cards with permission-gated read
  endpoints. Cards summarize current facts and link to owner screens; they are not Reports.
- Backend authorization protects every action. Frontend gates are navigation and UX only.
  Reuse the fixed Role/Permission vocabulary; do not invent Department Council, Faculty
  Council, or new capacity permissions.
- Every material transition records actor or system cause, reason where required, timestamp,
  and immutable evidence references. Administrative scope override follows existing audit
  policy.
- Add English and Arabic backend messages, notifications, labels, empty states, and frontend
  locale entries together. Validate light/dark and LTR/RTL combinations using theme tokens;
  color is never the only status signal.
- Seed a deterministic demonstration path in which a finalized Grade updates Credit-Hour
  Summary and Standing, plus representative credit and termination workflows.

## Testing Decisions

- The primary acceptance seam is the existing backend HTTP feature-test layer running
  against PostgreSQL. Tests assert externally observable API responses, persisted workflow
  outcomes, permissions, notifications, and cross-module effects rather than private class
  calls or implementation structure.
- Prefer full workflow tests at that seam: automatic Exemption from accepted admission;
  each manual credit workflow; Standing/progress refresh after finalized or corrected Grade;
  Must-Retake registration through ordinary and capacity-exception paths; remediation from
  Revocation through opportunity/evaluation; and Termination Case through dismissal,
  evidence change, or finalization.
- Exercise module seams through real Public contracts within feature tests. Add narrow
  contract tests only where failure semantics are load-bearing: idempotent event handling,
  expected-deadline remediation evaluation, lifecycle guarding, request closeout, and
  authoritative ID-only event rereads.
- Use pure unit tests only for deterministic algorithms with dense boundary matrices:
  target-aware Credit-Hour Summary accounting, Standing precedence, readiness blockers,
  first-semester and approaching-graduation exceptions, obligation ordering/feasibility,
  Transfer cap arithmetic, remediation opportunity/deadline selection, and canonical
  termination fingerprints.
- Accounting tests cover raw versus Satisfied operands, category caps, zero floors,
  Exemption plus Earned CH for one target, extra electives, Revocation, and Total-row sums.
- Standing tests cover every CGPA boundary, absent CGPA, effective Grade below C+, Grade
  supersession within one Course, failed different elective, actionable/dismissed/cleared
  termination evidence, and Observation deduplication.
- Credit-workflow feature tests cover Role and scope at every transition, Draft locking,
  Return/resubmission review invalidation, immutable decision creation, Revocation authority,
  Transfer window boundaries, external/normalized Grades, 9-CH target-hour cap under
  concurrency, Waiver level/Grade/coverage boundaries, and failure-triggered rejection.
- Registration tests extend the existing Enrollment feature-test precedent. Cover every
  registration mutation and approval boundary, offered versus unavailable obligations,
  greedy ordering ties, timetable/load infeasibility, Advisor Offering substitution, Audit,
  closed modification windows, multi-Department capacity decisions, returned/newly lost
  seats, final revalidation races, unchanged-batch rejection, and no seat displacement.
- Remediation tests cover Awaiting Offering activation, current-versus-later Semester
  selection, late-add boundary, Calendar extension and forbidden shortening, evaluated
  deadline freeze, Course/Offering deactivation before and after Enrollment, full capacity,
  Student-created conflicts, valid Incomplete deferral, command/event retry equivalence,
  Released By Exemption, and preservation of existing Enrollment.
- Termination tests cover the transactional one-open-Case invariant, aggregate triggers,
  fingerprint order independence and every evidence version, exact-fingerprint dismissal,
  Trigger Cleared, Evidence Changed replacement, live Advisor reassignment before assessment,
  preserved assessment afterward, stage authorization, and no duplicate notification or
  Standing Observation.
- Add database-backed race tests for Exemption versus final termination, concurrent Case
  creation, Transfer-cap final approval, capacity changes during review, and remediation
  deadline extension during evaluation. Assert complete rollback rather than partial state.
- React component tests cover meaningful presentation and interaction: CH tables, typed
  readiness blockers, Standing status, request state machines, evidence forms, obligation
  guidance, capacity decisions, termination queues, permissions, validation, and EN/AR RTL
  rendering. Mock generated API hooks at the feature boundary; do not reproduce backend
  domain rules in UI tests.
- Keep Playwright intentionally small. Cover representative end-to-end journeys for a
  Student credit request and progress view, Advisor/Department Head review including a
  required full Offering, and Advisor-to-Department-Head-to-Council termination handling.
  Rule permutations remain at the faster HTTP seam.
- Follow prior art in the existing Enrollment registration/decision tests, Grading Grade
  finalization and Incomplete lifecycle tests, colocated React feature tests, and the serial
  Enrollment/Grading Playwright journeys.
- Regenerate and verify OpenAPI and the frontend client. Run the affected backend and
  frontend package gates, dependency-boundary checks, static analysis, formatting, and the
  serial full-stack suite with a migrated and seeded PostgreSQL database.

## Out of Scope

- Formal graduation approval, a Graduated lifecycle status, degree audit sign-off, and
  non-course or administrative graduation checks.
- Transcript composition, official/unofficial transcript issuance, PDF rendering, seals,
  watermarks, and QR verification; these belong to Phase 6 Records.
- Academic Standing, Graduation Readiness, and Termination Reports and exports in the
  Analytics section; Phase 7 consumes this phase's Public read models. Phase 5 includes only
  owner screens and Dashboard Cards.
- Automatic reinstatement after final termination or a new reinstatement/appeal workflow.
- Email, SMS, push notifications, durable external queues, scheduler infrastructure,
  websockets, real-time updates, or production deployment concerns.
- Editing or deleting effective academic decisions, silently rewriting submitted
  Registration Requests, auto-dropping existing Enrollments, reserving seats, or displacing
  enrolled Students.
- Generic prior-course equivalence outside the defined Transfer and Waiver policies.
- New Roles or Permissions, including Department Council, Faculty Council, or a dedicated
  capacity-override Permission.
- Materialized Standing, GPA, Credit-Hour Summary, or Graduation Readiness snapshots and
  cached report aggregates.
- Saved filters, ad-hoc reporting, cross-filtered analytics, and Card personalization.
- Changes to the grading scale, GPA/CGPA formula, Grade Sheet workflow, or general Phase 4
  behavior except for consuming its established Public outputs and handling new events.

## Further Notes

- Executable code and accepted ADRs remain authoritative if an older planning sentence
  conflicts with this spec. ADR-0029 through ADR-0043 are the decision record for this phase.
- “Termination Pending” is a derived Academic Standing with an actionable Case; “Terminated”
  is an Identity-owned Student Lifecycle Status after final Council decision. They are not
  interchangeable.
- “Satisfied CH” is target-aware progress toward the bound Curriculum, not a synonym for
  raw Earned, Transferred, Waived, or Exempted CH.
- “Remediation Obligation” is caused by post-checkpoint Exemption Revocation and does not
  imply an Attempt. “Must-Retake Obligation” is caused by an effective below-C+ Preparatory
  or Core Grade. They share registration enforcement but retain distinct causes and endings.
- Recovery commands are an explicit localhost operational mechanism, not a substitute for
  side effects on GET requests. They must be safe to rerun globally and per Student.
- The headline demonstration is: finalize a Grade, observe the Student's Credit-Hour Summary
  and Academic Standing change, then exercise a consequential workflow whose final state is
  reflected without manual data repair.
