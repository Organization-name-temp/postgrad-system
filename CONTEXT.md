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
University ID, the (opaque) Program reference, enrollment term, assigned Advisor, and an
identity snapshot. It is only the *anchor*; the growing academic record (Enrollments,
Grades, Credit-Hour Summary, Standing) is owned by later modules and references it by id.

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
intersected with that semester's Offerings.
_Avoid_: section, class, timetable slot ("Course Offering" is canonical; rooms and
physical timetabling are out of scope per SRS §2.4).

**Enrollment**:
One Student taking one Course Offering — and therefore one Course in one semester —
carrying a Course Status. The unit of registration and the thing a Grade attaches to.
_Avoid_: registration (that is the *act* of creating an Enrollment, not the record).

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
Withdrawal (W), Fail Withdrawal (FW), Incomplete (I), CGPA Improvement, Transfer, Waive,
Exempted. (See SRS Appendix D for the counting matrix.)

**Enrollment Classification**:
How one Enrollment counts for the Student's Program — Preparatory, Core, Internal
Elective, External Elective, or Research Course — derived at registration time from the
Student's Curriculum, the Course level, and the degree level, then kept as a snapshot on
the Enrollment (later Curriculum edits never re-classify past registrations). Per-Student
and per-Enrollment; distinct from Course Category, which is the Course's per-Program
placement in a Curriculum. An External Elective is a course outside the Student's own
Curriculum taken from another graduate Program's; a Research Course is the level-700
course whose Research Type matches the degree (PBL for MSc, Seminar for PhD).

**Research Type**:
Whether a level-700 research course runs as PBL (project-based learning — taken by MSc
students) or Seminar (taken by PhD students). An intrinsic property of the Course,
recorded in the Catalog.

**Exemption**:
Release from a *required Preparatory Course* based on prior qualification (e.g.
IELTS ≥ 6.5, prior E-JUST study). Reduces the Program's required total. Applied
automatically on enrollment or requested manually. Does not affect CGPA.
_Avoid_: conflating with Waiver or Transfer — see below.

**Transfer**:
Credit carried in from a course taken at *another institution* (max 9 CH, min grade B,
must map to an equivalent Course). Counts toward Required CH but NOT toward CGPA.

**Waiver**:
Excusal from a Course whose content the Student *previously studied* at level 400+ with
≥80% coverage. Counts toward Required CH but NOT toward CGPA; shown as "Waived".
_Distinction_: Exemption = prep-course release from a qualification; Transfer = external
credit imported; Waiver = internal course excused by prior study.

**Credit-Hour Summary**:
The per-Student, per-Category derived view of Required / Attended / Earned / Remaining /
Transferred / Waived / Exempted credit hours. Computed from Enrollments, Grades,
Exemptions, Transfers, and Waivers — not a directly-edited record.
_Avoid_: credit ledger, progress table.

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
CGPA and earned credit.

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
A Student's current classification derived from grades and CGPA: Good Standing,
Academic Warning, Academic Probation, or Termination Pending.

**Termination**:
The workflow that may end a Student's enrollment when termination criteria are met
(unfinished prep courses within 2 semesters, or grade D in 2+ courses). Decided by the
Academic Affairs Council.

## Records

**Transcript**:
The official or unofficial record of a Student's Enrollments, Grades, and Credit-Hour
Summary. Official transcripts are issued only by Student Affairs with seal, watermark,
digital signature, and verification QR code.
