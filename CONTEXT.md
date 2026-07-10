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

**Audit Log**:
The append-only record of security- and data-sensitive events (authentication events now;
sensitive-data CRUD later), retained long-term. Entries are never updated or deleted and
are written only through the audit writer, never edited directly.
_Avoid_: activity log, history, event log (the domain-event stream is a separate thing).

## Admissions

**Application**:
An Applicant's single submission for admission, moving through a fixed sequence of
statuses (Draft → Submitted → Email Verified → Under SA Review → … → Accepted/Rejected).
The workflow object, distinct from the Applicant who owns it.
_Avoid_: submission, form, request.

**Admission Decision**:
The terminal outcome recorded after interview: Accepted, Conditionally Accepted,
Waitlisted, or Rejected.

## Catalog

**Faculty**:
A top-level academic division (FoE, BAS, CSIT). Contains Departments.

**Department**:
An academic unit within a Faculty. Owns Programs.

**Program**:
A named course of study leading to a degree (MSc or PhD) within a Department, with a
total required credit-hour count (default 21). An Applicant applies to a Program; a
Student is enrolled in one.
_Avoid_: track, major, course (a Program is not a Course).

**Course**:
A single teachable unit with a code, credit hours, and a level. Belongs to one of three
categories. Distinct from an Enrollment (a Student taking that Course).

**Course Category**:
One of exactly three buckets a Course counts toward for a Program:
Preparatory (6 × 1 CH), Program Core (2 × 3 CH), Research Elective (3 × 3 CH).

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
