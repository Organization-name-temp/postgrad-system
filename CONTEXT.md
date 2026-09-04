# Postgraduate Registration System (PRS)

The ubiquitous language for E-JUST's postgraduate registration system, which manages
the full student lifecycle from application through graduation across the Faculties of
Engineering (FoE), Basic & Applied Sciences (BAS), and Computer Science & IT (CSIT).

This file is a glossary only. Architecture and technology decisions live in
`docs/architecture.md`, `docs/tech-stack.md`, and `docs/adr/`.

## People

**Applicant**:
A prospective student (role R01) who has submitted, or is filling out, an application
but has not yet been admitted. Becomes a Student upon acceptance.
_Avoid_: prospect, candidate.

**Student**:
An admitted, enrolled postgraduate (role R02) with a University ID and an academic
record. An Applicant transitions to a Student — the same person, a new role.
_Avoid_: enrollee, pupil.

**Advisor**:
The faculty member (role R03) assigned to a Student who approves registrations and
monitors progress. Full name "Academic Advisor".
_Avoid_: supervisor, mentor, tutor.

**Instructor**:
The faculty member (role R04) who teaches a Course and enters/signs its grades.
_Avoid_: doctor, teacher, professor, lecturer (all appear in the SRS; "Instructor" is canonical).

## Identity & access

**User**:
The authenticable account for one person — an email, a password, a preferred locale, and
one or more Roles. A User is distinct from the Roles it holds: the same person is one
User throughout, whether their current Role is Applicant or Student. The People above are
Roles a User holds, not separate accounts.
_Avoid_: account (informal for User); "login" (that is the *act*, not the User).

**Role**:
One of the ten fixed positions defined by the bylaws (R01–R10 — see People for each). The
role set is fixed in code; a User may hold several, and only the *assignment* of Roles to
Users is editable at runtime, and only by System/Super Administrator.
_Avoid_: group, profile, persona.

**Permission**:
A single authorised action (e.g. "Approve Course Registration"), drawn from the SRS §3.2
matrix. Each Role grants a fixed set; a User's effective Permissions are the union across
their Roles. Enforced on the API and mirrored on the SPA for display only — the client is
never the authority.
_Avoid_: privilege, right, capability ("Permission" is canonical).

**University ID**:
The unique student identifier minted by Identity when an Applicant is accepted, formatted
`{year}-{programCode}-{NNNN}` (e.g. `2026-CSE-0001`) — the year of the Admission Cycle, the
accepted Program's code, and a per-(year, program) sequence. Distinct from the User id.

**Student anchor**:
The `Student` record Identity creates at acceptance — 1:1 with a User, holding the
University ID, the (opaque) Program and admission-time Curriculum Version references,
enrollment term, assigned Advisor, Student Lifecycle Status, and an identity snapshot. It
also holds a typed immutable admission-evidence recovery receipt: Identity stores this
narrow acceptance-time snapshot opaquely so Enrollment can recover wholly missed
post-commit exemption handling through `StudentDirectory`; it is not mutable Admissions
state or an Exemption decision. The Student is only the *anchor*; the growing academic
record (Enrollments, Grades, Credit-Hour Summary, Standing) is owned by later modules and
references it by id.

**Student Lifecycle Status**:
Whether a Student may undertake new academic actions: Active from admission, or Terminated
after a final Academic Affairs Council decision. A Terminated Student keeps the Student
Role and read access to their historical academic record but cannot mutate it.
_Avoid_: Course Status, Academic Standing, enrollment status.

**Audit Log**:
The append-only record of security- and data-sensitive events (authentication events now;
sensitive-data CRUD later), retained long-term. Entries are never updated or deleted and
are written only through the audit writer, never edited directly.
_Avoid_: activity log, history, event log (the domain-event stream is a separate thing).

## Admissions

**Application**:
An Applicant's submission for admission within one Admission Cycle, moving through a
fixed sequence of statuses (Draft → Submitted → Email Verified → Under SA Review → … →
Accepted/Rejected). The workflow object, distinct from the Applicant who owns it. One
Applicant holds at most one Application per Cycle, but may re-apply in a later Cycle.
_Avoid_: submission, form, request.

**Admission Cycle**:
A single admission intake — one Intake Term of one year (e.g. "Fall 2026") — with an
application window (open/close). Applications belong to exactly one Cycle; an Applicant
can only start an Application while a Cycle's window is open. The Cycle also fixes the
enrollment term a successful Applicant is admitted into.
_Avoid_: intake, season, round, admission year (the Cycle, not a bare year, is canonical).

**Intake Term**:
The semester an Admission Cycle admits into — Fall or Spring (E-JUST's two main
semesters). Combined with a year it names a Cycle and is unique per year.

**Application Status**:
Where an Application sits in its fixed workflow — one of Draft, Submitted, Email Verified,
Under SA Review, SA Approved, Returned for Revision, Under Dean Review, Interview Scheduled,
Interview Completed, Accepted, Conditionally Accepted, Waitlisted, Rejected. Only the
transitions defined by the workflow are legal; each change is timestamped, attributed, and
optionally carries a reviewer note.

**Status Transition**:
One append-only record of an Application moving from one Status to another — who, when, and
an optional note (a screening comment, revision instruction, or rejection reason). The
Application's Status Transitions are its applicant-visible timeline and its decision audit
trail; distinct from the security Audit Log.

**Interview Slot**:
An offered interview appointment — a date/time, an assigned interviewer (a faculty User),
and a mode (in-person or virtual with a meeting link). Created by Student Affairs; holds
exactly one Applicant. The pool of open Slots is what an Applicant is scheduled into or
reschedules within.

**Interview**:
One Applicant's booking of one Interview Slot, carrying its confirmation state, a single
allowed reschedule, and — once conducted — the interviewer's evaluation score and notes.
Only the assigned interviewer may record the evaluation.

**Admission Decision**:
The terminal outcome recorded after interview: Accepted, Conditionally Accepted,
Waitlisted, or Rejected. Conditionally Accepted and Waitlisted are not resting ends —
each later resolves to Accepted or Rejected.

**Identification Type**:
How an Applicant is identified on their Application — a National ID (an Egyptian
citizen's 14-digit number) or a Passport (an alphanumeric travel-document number, for a
non-Egyptian or overseas Applicant). Chosen on the personal-info step; it selects the
format the identifier is validated against. Stored with the identifier, which is unique
within an Admission Cycle.
_Avoid_: ID kind; "document type" (Document Type is the uploaded-file taxonomy — a
different thing that happens to share the word "type").

## Notifications

**Notification**:
An in-app message delivered to one User's notification bell, carrying a type and a data
payload (never pre-rendered text — the SPA renders it bilingually from the type). Read via a
polled endpoint; no email, SMS, or websocket. Distinct from the Audit Log (security record)
and the in-process domain-event stream (module-to-module, not user-facing).

## Catalog

**Faculty**:
A top-level academic division (FoE, BAS, CSIT). Contains Departments.

**Department**:
An academic unit within a Faculty. Owns Programs and Courses.

**Program**:
A named course of study leading to a degree (MSc or PhD) within a Department. Carries a
required credit-hour count per Category (default Preparatory 6 / Program Core 6 /
Research Elective 9, total 21). An Applicant applies to a Program; a Student is enrolled
in one.
_Avoid_: track, major, course (a Program is not a Course).

**Course**:
A single teachable unit with a code, a credit-hour breakdown (Lecture + Tutorial + Lab =
Total), a level, and — for level-700 research courses — a Research Type (PBL or Seminar).
Owned by a Department, or university-wide (the shared Preparatory language/culture
courses belong to no Department). A Course carries no Category of its own — its Category
is assigned per Program by a Curriculum Entry. Distinct from an Enrollment (a Student
taking that Course).

**Curriculum**:
A Program's set of Curriculum Entries — the Courses it comprises, grouped by Category.
Editing a Program's Curriculum is a versioned Program change.

**Curriculum Version**:
The immutable snapshot of a Program and its Curriculum effective at a particular change.
A Student is bound at admission to one Version, which remains their degree requirements
unless a future explicit migration changes it.
_Avoid_: current curriculum (when describing a Student's requirements), study plan.

**Curriculum Entry**:
The placement of one Course into one Program under one Category (e.g. "CSE511 is Program
Core in the MSc in Computer Engineering"). The same Course may have Entries in several
Programs under different Categories — one Program's Core Course is available to another
Program as an (external) Research Elective.
_Avoid_: program course, offering (an offering is a scheduled section — Enrollment's term).

**Course Category**:
The role a Course plays within a Program, assigned by a Curriculum Entry — one of exactly
three: Preparatory (6 × 1 CH), Program Core (2 × 3 CH), Research Elective (3 × 3 CH). A
Category is per-Program, never an intrinsic property of the Course.

**Preparatory Requirement Role**:
The meaning of one Preparatory Curriculum Entry within a Program: English Language,
Arabic Language, Japanese Language, Japanese Culture, Key Skills, or Departmental
Requirement. Each Program has exactly one Preparatory Entry in each Role; Exemption rules
target Roles rather than Course codes.
_Avoid_: Preparatory type, exemption code.

## Calendar

**Semester**:
One academic term the university actually runs — a Term (Fall or Spring) of one year,
e.g. "Fall 2026" — carrying its start/end dates, the registration-flow deadlines
(registration window, add/drop end, late-add end, withdrawal deadline), and the
Grade-Submission Deadline that closes it. Course Offerings, Enrollments, and academic
deadlines all belong to a Semester. Related to but distinct from an Admission Cycle: the
Cycle is the *application* window run by Admissions; the Semester is the *academic* term
the accepted Applicant enrolls into (they share the Term + year naming).
_Avoid_: term alone (ambiguous with Intake Term), session, academic year.

**Grade-Submission Deadline**:
The date by which an Instructor is expected to have delivered a Semester's grades — the
one Semester date that falls on or after the term ends. It is a *nudge, not a gate*: past
it, an Offering with no Finalized Grade Sheet is flagged to its Instructor and Department
Head, and nothing is blocked or changed. Distinct from the Incomplete resolution
deadline, which is derived from the following Semester's start and is per-Enrollment.

## Enrollment & credit

**Course Offering**:
One Course actually being taught in one semester — carrying the Instructor teaching it,
an optional seat capacity, and its weekly meeting times. A Course is curriculum (what a
Program comprises); an Offering is operation (what runs this semester). Students register
into Offerings, so the eligible list for a semester is the Student's Curriculum
intersected with that semester's Offerings. Capacity is normally a hard ceiling; the
scoped Department Head may approve a reasoned, audited exception only to place a Student
into a full Offering for a Must-Retake Obligation. The exception never displaces an
existing Enrollment. Authorization uses the Department Head Role plus Course-department
scope, not the ordinary registration-approval Permission and not a new Permission.
_Avoid_: section, class, timetable slot ("Course Offering" is canonical; rooms and
physical timetabling are out of scope per SRS §2.4).

**Enrollment**:
One Student taking one Course Offering — and therefore one Course in one semester —
carrying a Course Status. The unit of registration and the thing a Grade attaches to.
_Avoid_: registration (that is the *act* of creating an Enrollment, not the record).

**Registration Request**:
The Student's submitted batch of proposed Course Offerings for one Semester. It normally
moves from Pending Advisor to Approved or Rejected. If Advisor recommendation requires a
full-Offering exception for a Must-Retake Course, it moves to Awaiting Capacity Override
until every Department Head scoped to a full Offering records a per-Offering decision.
All approvals create the Enrollments atomically; any denial rejects the unchanged batch.
If normal seats become available first, the corresponding override requirements cease to
be active; when none remains, the already-recommended request is revalidated and approved
automatically. If another requested Offering becomes full while the request waits, its
own scoped override requirement is added. Earlier decisions for the same request and
Offering remain recorded and are not duplicated. If a replacement Preparatory Exemption
releases a Course contained in a still-pending request, the whole unchanged batch is
rejected rather than silently removing that Course; its review history remains visible.
Final Termination rejects every still-pending request with an attributed reason while
preserving its batch, transitions, and capacity-review history.
_Avoid_: registration (the act), Enrollment (the approved per-Offering record).

**Class Session**:
One planned meeting of a Course Offering on a specific date, generated from the
Offering's weekly meeting times across the Semester and cancellable by the Instructor
(a cancelled Session never counts). What attendance is recorded against, and where
future course materials or assessments would attach.
_Avoid_: lecture, class, period.

**Attendance**:
The per-Session present/absent record an Instructor keeps for each Enrollment in their
Offering. A Student's attendance percentage is always derived (present ÷ non-cancelled
recorded Sessions), never stored; in this build it informs visibility and the
audit-at-risk flag (below 75% on an Audit Enrollment), never an automatic status change.

**Course Status**:
The accounting classification of a single Enrollment, governing how it counts toward the
transcript, CGPA, and each credit-hour category: Regular, Retake, Audit (AU),
Withdrawal (W), Fail Withdrawal (FW), Incomplete (I), or CGPA Improvement. Exemption,
Transfer, and Waiver are standalone decisions, never Course Statuses.

**Enrollment Classification**:
How one Enrollment counts for the Student's Program — Preparatory, Core, Internal
Elective, External Elective, or Research Course — derived at registration time from the
Student's Curriculum, the Course level, and the degree level, then kept as a snapshot on
the Enrollment (later Curriculum edits never re-classify past registrations). Per-Student
and per-Enrollment; distinct from Course Category, which is the Course's per-Program
placement in the Student's bound Curriculum Version. An External Elective is a course outside the Student's own
Curriculum taken from another graduate Program's; a Research Course is the level-700
course whose Research Type matches the degree (PBL for MSc, Seminar for PhD).

**Research Type**:
Whether a level-700 research course runs as PBL (project-based learning — taken by MSc
students) or Seminar (taken by PhD students). An intrinsic property of the Course,
recorded in the Catalog.

**Exemption**:
The standalone decision releasing a Student from one *required Preparatory Course* based
on a prior qualification (e.g. IELTS ≥ 6.5 or prior E-JUST study). It targets that Course,
reduces the Program's required total, and does not affect CGPA. It is applied automatically
at admission acceptance when the accepted Application's evidence proves a configured
rule, or requested manually to supply or correct evidence for the same rules;
prior-course equivalence is a Waiver, not an Exemption. For the E-JUST MSc-to-PhD rule,
"same" means the previous MSc Program and admitted PhD Program belong to the same
Department; "same track" is not a separate domain concept. Because an Exemption proves
that the Preparatory requirement did not apply, a later corrected Exemption may release
that requirement even after a failed or successful Attempt; the Attempt and its Grade
remain in academic history and CGPA. Earned Credit Hours for that exempted Course remain
raw history but are excluded from Satisfied Credit Hours while the Exemption is effective.
Approval does not cancel an existing in-progress Enrollment in the
released Course: the Student may use ordinary drop or withdrawal rules, or continue and
receive the normal academic outcome.
_Avoid_: conflating with Waiver or Transfer — see below.

**Transfer**:
The standalone decision recognizing a course taken at *another institution* (max 9 CH,
min normalized E-JUST grade B) by mapping it to an equivalent Course in the Student's
Curriculum. It preserves the source grade as printed and the Department Head's normalized
Letter Grade, satisfies the mapped requirement without affecting CGPA, and recognizes the
target Course's credit hours toward the lifetime 9-CH cap. It may be requested through the
inclusive calendar month after admission. A new Transfer request cannot target a Course
after the Student has an effective failed for-credit Attempt in that Course.

**Waiver**:
The standalone decision excusing a Curriculum Course based on a prior *E-JUST Course* at
level 400+ that was successfully completed at C+ or higher and has ≥80% content coverage.
It satisfies that requirement, does not affect CGPA, and is shown as "Waived"; study
outside E-JUST is evaluated only as a Transfer. A new Waiver request cannot target a
Course after the Student has an effective failed for-credit Attempt in that Course.
_Distinction_: Exemption = prep-course release from a qualification; Transfer = external
course recognized; Waiver = current course excused by prior E-JUST study.

**Credit Decision Request**:
A Student's evidence-backed request for one manual Exemption, Transfer, or Waiver. It is
editable in Draft or Returned for Revision, locked while under review, and carries an
append-only transition history through the required review stages to Approved or Rejected;
final approval atomically creates a separate Exemption, Transfer, or Waiver decision. If
a finalized initial or correction Grade creates an effective failed Attempt in the target
Course while a Transfer or Waiver request is under review, the system terminally rejects
that request, records why, and notifies the Student and current reviewers.
Final Termination likewise rejects every non-terminal Credit Decision Request without
removing its evidence or transition history.
_Avoid_: adjustment request, special-status request.

**Revocation**:
The explicit decision that an approved Exemption, Transfer, or Waiver no longer counts,
recorded by the same final authority with a reason and timestamp. It never erases or edits
the original decision; a replacement requires a new Credit Decision Request. If a
Preparatory Exemption is revoked after the ordinary two-Semester checkpoint, its restored
requirement receives a Remediation Window through the Grade-Submission Deadline of the
first main Semester in which its Course has an eligible Offering and the Student can still
lawfully add it at Revocation time. The current Semester qualifies while registration,
add/drop, or late-add remains open; after late-add closes, the search moves forward.
_Avoid_: deletion, cancellation, rollback.

**Credit-Hour Summary**:
The per-Student, per-Category derived view of Baseline Required, Adjusted Required,
Attended, Earned, Transferred, Waived, Exempted, Satisfied, and Remaining credit hours.
Raw accounting remains visible, while Satisfied is capped at Adjusted Required and
Remaining cannot fall below zero; the Summary is never a directly edited record.
_Avoid_: credit ledger, progress table.

**Satisfied Credit Hours**:
The portion of Adjusted Required Credit Hours fulfilled by earned Enrollments, Transfers,
or Waivers after matching them to Course targets, capped at that requirement. Earned hours
from a Course whose target is removed by an effective Exemption remain raw Earned Credit
Hours but do not also satisfy another requirement. This is the precise meaning of
“Completed CH” in the SRS; Exemptions reduce the requirement instead of satisfying it.
_Avoid_: completed credit, earned credit (Earned CH is only one contributor).

**Approaching Graduation**:
The state of a Student who has more than zero but fewer than six Remaining Credit Hours and
can satisfy every remaining Course target or category slot with the proposed for-credit
schedule. Only that completing schedule may fall below the normal 6-CH minimum.
It is independent of CGPA and Termination Pending: those are Graduation Readiness blockers
displayed alongside it, not reasons to hide curriculum proximity or prevent the completing
schedule during Probation or termination due process.
_Avoid_: final year, nearly finished.

**Graduation Readiness**:
An advisory derived result stating whether the Student has zero Remaining Credit Hours,
an authoritative CGPA of at least 3.00, and no actionable Termination Pending Case.
Academic Warning alone does not prevent readiness. This result neither graduates the
Student nor changes Student Lifecycle Status; formal graduation remains a separate future
workflow with any non-course and administrative checks. Its contract returns `ready`,
Remaining CH, nullable CGPA, a separate Approaching Graduation result, and typed Blockers:
Remaining Requirements, CGPA Unavailable, CGPA Below Minimum, or Termination Pending.
Consumers display those facts and never reconstruct readiness.
_Avoid_: graduated, graduation decision, graduation approval.

## Grading & standing

**Grade**:
The academic result of one Enrollment — the Mark its Instructor entered, and the Letter
Grade and GPA points derived from that Mark — then signed and reviewed. A Grade exists
only for an Enrollment that was actually graded: an Audit, Withdrawal, Incomplete, or
Fail-Withdrawal Enrollment has **no Grade at all**, because those are Course Statuses,
not grades. (SRS Appendix A lists AU/W/FW/I beside the letters because a Transcript
prints them in its grade column — that is a rendering of the Course Status, never a
stored Grade.)
_Avoid_: mark (the Mark is the input; the Grade is the result).

**Mark**:
The percentage from 0 to 100 an Instructor records for one Enrollment — the sole input
from which the Letter Grade and its GPA points are derived. One final figure per
Enrollment; this domain has no assessment components or weights.

**Letter Grade**:
One of the nine graded outcomes — A+, A, B+, B, C+, C, D+, D, F — each carrying fixed
GPA points, derived from the Mark by the grading scale and frozen onto the Grade when it
is recorded (a later change to the scale never rewrites a Grade already given). C+ (2.5
points) is the minimum passing Letter Grade: the threshold at which a Course's credit
hours are earned. No postgraduate Course is Pass/Fail, so Appendix A's P and FL are not
part of this domain.
_Avoid_: A−, B−, C− (not part of E-JUST's scale).

**Grade Sheet**:
One round of grade submission for one Course Offering — the Grades of the gradable
Enrollments on its roster, moving as a single document from Draft to Submitted (the
Instructor signs it) to Finalized (the Department Head accepts it), or Returned with a
note for the Instructor to correct and re-sign. Grades become part of the academic record
only when their Sheet is Finalized, and a Finalized Sheet is never reopened. An Offering
accumulates Sheets: one Initial round covering every gradable Enrollment, then a
follow-up round for each batch of Incomplete resolutions or Corrections. Audit and
Withdrawal Enrollments appear on the roster but are never lines on a Sheet.
_Avoid_: gradebook, grade roster, grade batch (the Sheet is the reviewable document).

**Central Grade Entry**:
Student Affairs keying marks into any Instructor's Draft Grade Sheet — the path for
grades that arrive on paper. It is the plain reading of the SRS §3.2 matrix rather than
a new power: Student Affairs holds "Enter Grades" and not "Finalize Grades", so they may
key and never sign, the Instructor remains the author of their own grades, and every such
write to someone else's Sheet is recorded in the Audit Log.
_Avoid_: bulk entry, grade import (nothing is imported; a Sheet is keyed line by line).

**Attempt**:
One of the at most two for-credit Enrollments a Student may hold in a single Course. The
first is Regular; the second is a Retake if the first earned no credit, or a CGPA
Improvement if it did. Audit and Withdrawal Enrollments are not Attempts and consume
nothing. Both Attempts appear on the Transcript, but only the later one counts toward the
CGPA and earned credit. Passing a different elective may satisfy the same curriculum slot,
but it never supersedes the original Course's Grade; that Grade remains effective for
CGPA, Academic Warning, and D/F Termination evaluation.

**Must-Retake Obligation**:
An effective below-C+ outcome in a Preparatory or Core Course that requires the Student
to prioritize the same Course in a later Semester when it has an eligible Offering. A
later successful for-credit Attempt clears the obligation. An effective Exemption may
also release it by proving that the Preparatory requirement did not apply, while a new
Transfer or Waiver request cannot target the failed Course. The failed Attempt and Grade
remain in history and CGPA in either case. An Audit Enrollment neither selects nor clears
the obligation. If none of the Student's obligations is offered, they may continue with
other registration rather than being blocked by an unavailable Course. When load or
timetable constraints prevent taking every offered obligation, the required feasible set
is selected deterministically at the Course level. Preparatory obligations come first,
ordered by earliest applicable termination/remediation checkpoint, then their origin time
(the effective failed Grade or Exemption Revocation), then Course code. Core obligations
follow, ordered by oldest failed Attempt then Course code. The
system walks that order once and includes each Course only if an eligible Offering fits
the remaining load and timetable; it does not skip a feasible higher-priority Course to
maximize Course count, credit hours, or load utilization. The Student selects any
eligible, non-conflicting Course Offering for each required Course; the Advisor may change
that Offering but not substitute a lower-priority Course. An
unrelated Course already in the Student's proposed or approved schedule does not make a
Must-Retake Course “not offered”: while registration changes remain permitted, that
unrelated Course must be removed or switched to resolve the conflict. Once all
modification windows close, the system never auto-drops an Enrollment; the obligation
carries forward if it could not be added. A full eligible Offering still counts as
available: after the Advisor recommends the registration, the scoped Department Head may
approve a reasoned, audited capacity exception for that obligation. No enrolled Student
is displaced. If several full Offerings belong to different Departments, each owning
Department Head decides only their Offering; the Registration Request waits for all
approvals, and any denial rejects the atomic batch. Capacity changes are reconciled
idempotently: a now-available seat removes that Offering's active exception requirement,
and the request auto-approves only when its whole batch passes transactional revalidation
with no exception still needed. Conversely, a requested Offering that newly becomes full
adds its own Department-scoped requirement before final approval.
_Avoid_: must-retake flag (the obligation drives registration behaviour, not only display).

**Remediation Obligation**:
The temporary registration obligation created when a Preparatory Exemption is revoked
after the ordinary completion checkpoint, owned by Enrollment beside the originating
Revocation and exposed to AcademicStanding as a Public DTO. It may exist without any prior
Attempt, so it is not a Must-Retake Obligation. During its Remediation Window it shares
the same deadline-first Preparatory registration priority, timetable handling, and
full-Offering Department Head capacity-exception path. It ends when the requirement is
satisfied, when a replacement Preparatory Exemption closes it as Released By Exemption, or
when its remediation checkpoint is evaluated; if still unsatisfied then, it becomes
termination evidence. Released By Exemption is distinct from Course completion: it records
that corrected evidence proved the requirement did not apply. If no future eligible
Offering exists, it remains Awaiting
Offering with no termination checkpoint: it contributes to Remaining CH and blocks Graduation Readiness,
but cannot trigger termination. The Student, Advisor, and scoped Department Head are
notified; scheduling a real enrollment opportunity assigns the checkpoint and activates
registration priority. Enrollment reconciles immediately after local Offering changes and
after Calendar's Public `SemesterChanged` or Catalog's Public `CourseChanged` signal; each
signal identifies its owner record rather than copying authoritative facts, and an
idempotent scoped reconciliation command repairs missed post-commit handling. Until
evaluation, an assigned remediation
checkpoint extends when its Semester's authoritative Grade-Submission Deadline moves
later, but never shortens when that date moves earlier. Each Calendar change and the
checkpoint outcome are retained in its history and communicated to the Student, Advisor,
and scoped Department Head. Before the Student enrolls, loss of an institution-provided
eligible opportunity moves the obligation to another eligible Offering in the same
Semester, the earliest later lawful Semester, or Awaiting Offering when none exists; the
change is retained and communicated. Full capacity and Student-created conflicts do not
count as loss of opportunity. Once an Enrollment exists, that opportunity was used and is
not reassigned. Evaluation freezes the deadline used for that decision.
_Avoid_: retake (the Student may never have taken the Course), grace flag.

**CGPA Improvement**:
The Course Status of a second Attempt at a Course the Student already passed at C+ or
better, taken to raise the CGPA. It counts exactly as a Retake does — the later Attempt
supersedes the earlier in the CGPA — and it consumes the same single re-registration
allowance, so no Course is ever attempted for credit three times.

**Quality Points**:
One Enrollment's contribution to a GPA — its Letter Grade's GPA points multiplied by its
credit hours. A GPA is the Quality Points of the counted Enrollments divided by their
credit hours, which is why a 3-credit Course moves a GPA three times as far as a 1-credit
Preparatory Course.

**Incomplete**:
The Course Status of an Enrollment whose coursework the Instructor attests is at least
70% complete but unfinished, deferring its Grade rather than deciding it. It must be
resolved before the 10th week of the following Semester; unresolved, it becomes a Fail
Withdrawal. While Incomplete an Enrollment counts as attended but toward neither the
CGPA nor earned credit; resolving it restores the Course Status the Enrollment held
before and records the Grade in the Enrollment's *original* Semester.
_Avoid_: incomplete grade (an Incomplete Enrollment has no Grade at all until resolved).

**Fail Withdrawal**:
The Course Status of an Enrollment whose Incomplete passed its resolution deadline
unresolved — the only way an Enrollment becomes one. It appears on the Transcript and
counts nowhere else: not toward the CGPA, nor required, attended, or earned credit
hours. Distinct from a Withdrawal, which the Student chooses within the withdrawal
period and which carries no failure.

**GPA / CGPA**:
GPA is the grade-point average for one Semester; CGPA is cumulative across every counted
Semester. Both are always derived from the Grades of CGPA-counting Enrollments, never
stored. Minimum CGPA for graduation is 3.0.

**Academic Standing**:
A Student's exhaustive current classification derived from effective Grades, CGPA, and
termination triggers: Not Yet Assessed, Good Standing, Academic Warning, Academic
Probation, or Termination Pending. Exactly one applies; absence of a CGPA is Not Yet
Assessed rather than Good Standing or a null value unless a termination trigger is active,
in which case Termination Pending takes precedence.
A failed elective therefore remains relevant to Warning and D/F Termination after a
different elective satisfies the curriculum slot. Only a later successful Attempt in the
same Course supersedes that Grade; requirement satisfaction is not Grade supersession.
Academic Warning alone does not block graduation readiness: a Student with every
curriculum requirement satisfied and CGPA of at least 3.00 is academically ready even
though the Warning continues to describe their effective Grade history. Good Standing is
not an additional graduation requirement.
Termination Pending is different: an actionable open Termination Case temporarily blocks
graduation readiness. Dismissal or Trigger Cleared removes that block and immediately
returns readiness to the satisfied-curriculum plus CGPA test.

**Standing Observation**:
An append-only record that a Student's derived Academic Standing was observed to change,
used for history and once-only notifications. It is never the source of current Standing,
which is recalculated from authoritative academic facts.
_Avoid_: standing snapshot, stored standing, standing override.

**Termination**:
The workflow that may end a Student's enrollment when termination criteria are met
(Preparatory requirements still unsatisfied after the second consecutive Semester's
Grade-Submission Deadline, subject to a valid Incomplete's resolution, or grade D in 2+
distinct Courses based on their current effective Grades). A successful later Attempt can
clear the Grade-based trigger. A Student has at most one active case, containing the complete
set of currently actionable trigger evidence. The currently assigned Advisor assesses a
flagged case, the Department Head decides whether to recommend termination, and the
Academic Affairs Council makes the final decision. Advisor scope is live while the case
awaits that assessment: reassignment removes the former Advisor's authority and gives it to
the new Advisor. Once recorded, an assessment remains attributed history and reassignment
does not restart or invalidate the later Department Head/Council stages. If every trigger
clears first, the case closes as Trigger Cleared; a final
termination is never reversed automatically. If evidence changes while any trigger remains,
the old case closes as Evidence Changed and a new case starts again with Advisor assessment;
earlier reviews remain historical and suppress neither fingerprint. A dismissal suppresses
only the exact trigger
evidence reviewed. Preparatory evidence includes the requirement, its ordinary checkpoint
or Remediation Obligation, and the effective Grade, Withdrawal, credit decision, or
Revocation facts present—including the explicit absence of an Attempt. A later academic
fact or new Remediation Obligation is materially changed evidence and may open a new case;
unchanged non-completion remains suppressed. A Preparatory
requirement restored by a post-checkpoint Exemption Revocation cannot trigger termination
until its Remediation Window closes; a valid Incomplete then receives the usual deferral.
An Awaiting Offering Remediation Obligation has no deadline and cannot become termination
evidence until an enrollment opportunity is scheduled.
A final termination and a concurrent corrected Exemption cannot both take effect from stale
evidence: if the Exemption takes effect first, its cleared evidence prevents termination;
if termination takes effect first, later academic mutation is blocked.

## Records

**Transcript**:
The official or unofficial record of a Student's Enrollments, Grades, and Credit-Hour
Summary. Official transcripts are issued only by Student Affairs with seal, watermark,
digital signature, and verification QR code.

## Presentation

**Dashboard**:
The authenticated entry screen, composed from the acting User's own access rather than
selected by a single Role: a User holding several Roles sees the union of their Dashboard
Cards on one page, and there is no "view as role" switch (ADR-0024). A System/Super
Administrator sees an explicit administration set instead of the union they inherit.
_Avoid_: home page, portal, role dashboard.

**Dashboard Card**:
One summary on the Dashboard, owned by the module whose data it summarises, gated on a
single Permission or Role, showing counts, a short list, a term-over-term delta or a single
chart, plus a link into the full surface behind it. A Card introduces no domain logic of its
own — every figure is read from the derivation that already owns it. A Card is scoped to the
acting user and **takes no parameter**: that is what separates it from a Report, and the moment
a Card would need one it has become a Report.
_Avoid_: panel (already a generic word for a bordered page section across the codebase),
widget, tile, summary card.

**Report**:
A parameterised, cross-record view of data the acting user may read — figures, charts, and the
drillable list behind them — owned by the module that owns its spine data and presented together
with the others as the Analytics surface. A Report is where a question is asked *about* a
population (a Program's grade distribution, a Faculty's application pipeline); a Dashboard Card
answers "what is waiting for me". A Report never discloses more than the screens its viewer may
already open, and drilling into one lands on an ordinary filtered list.
_Avoid_: analytics dashboard, analytical dashboard, role dashboard (Dashboard means the
authenticated landing surface and nothing else); query; view.

**Export**:
A file rendering of one filtered List, carrying a canonical column set decided by the
system rather than by whoever requested it, rendered in the requester's language, and recorded in
the Audit Log because the data leaves. An Export is authorised by the same access as the list it
mirrors — it is a copy of what the requester could already read, not a new power. Reports export by
drilling into their corresponding filtered List. Distinct from
issuing an official **Transcript**, which is a sealed, verifiable document about one Student that
only Student Affairs may issue.
_Avoid_: download, report file, extract, dump.
