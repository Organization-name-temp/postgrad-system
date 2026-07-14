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
Total), and a level. Owned by a Department, or university-wide (the shared Preparatory
language/culture courses belong to no Department). A Course carries no Category of its
own — its Category is assigned per Program by a Curriculum Entry. Distinct from an
Enrollment (a Student taking that Course).

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

## Enrollment & credit

**Enrollment**:
One Student taking one Course in one semester, carrying a Course Status. The unit of
registration and the thing a Grade attaches to.
_Avoid_: registration (that is the *act* of creating an Enrollment, not the record).

**Course Status**:
The accounting classification of a single Enrollment, governing how it counts toward the
transcript, CGPA, and each credit-hour category: Regular, Retake, Audit (AU),
Withdrawal (W), Fail Withdrawal (FW), Incomplete (I), CGPA Improvement, Transfer, Waive,
Exempted. (See SRS Appendix D for the counting matrix.)

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
The letter grade (A+ … F, plus P/FL/AU/W/FW/I) and GPA points recorded against an
Enrollment by its Instructor, then signed and reviewed.

**GPA / CGPA**:
GPA is the grade-point average for one semester; CGPA is cumulative across all counted
semesters. Minimum CGPA for graduation is 3.0.

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
