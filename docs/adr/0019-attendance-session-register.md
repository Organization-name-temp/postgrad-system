# Attendance is a session register owned by Enrollment: instructor-recorded, derived percentages, flag-only consequences

**Status:** accepted

Attendance has **no functional requirement in the SRS**: it appears only as the derived
"Attend. CH" accounting column (covered by the Course-Status matrix) and the one-line
Audit rule "must attend ≥75%" (Appendix D), and LMS functionality is out of scope
(§2.4). Building it anyway is a deliberate owner extension of the learning build, made
for two reasons: the Audit rule is otherwise unenforceable *and invisible*, and the
project wants a structural seed for a future LMS module (ADR-0017). Because it is an
extension, its scope is held deliberately tight. Three questions had to be settled:
**granularity**, **authorization**, and **consequences**.

**Granularity — a session register, not a percentage field.** Each `CourseOffering`
gets `ClassSession` rows **auto-generated** from its weekly meeting times across the
Semester's start→end dates; the Instructor can **cancel** a session (a cancelled session
never counts — this is also the holiday answer, so no holiday calendar is needed).
Attendance is one binary present/absent mark per (session, enrollment). No late/excused
taxonomy — that is attendance *policy*, which has zero SRS backing. A student's
attendance percentage is always **derived on demand** (present ÷ non-cancelled sessions
recorded to date), never stored — the project's standing rule for derived read models.

**Authorization — by assignment, not by permission.** The Offering's assigned Instructor
records marks and cancels sessions, exactly as Phase 2 authorized interviewers by
assignment. The §3.2 permission matrix has no attendance row and stays untouched (the
fixed-matrix discipline); ADR-0015's administrative override applies — R09/R10 may read
unscoped and write with an Audit Log entry.

**Consequences — visibility and a flag, nothing automated.** Students see their own
percentage, instructors their registers, advisors their advisees'. The single
rule-flavored output is the **audit-at-risk flag**: an Audit Enrollment whose derived
percentage first crosses below 75% gets a StatusChip and an in-app notification. No
status change, conversion, or penalty fires — the SRS never says what happens to a
failing audit, and we do not invent consequences.

## Considered options

- **Session register, binary marks, flag-only (chosen).** Auditable numbers, the
  smallest model that makes the ≥75% rule visible, and sessions are exactly the
  attachment point future LMS artifacts (materials, assessments) need.
- **No attendance at all (rejected).** The default SRS-faithful answer, but rejected by
  the owner's scope decision — and it leaves Appendix D's only attendance rule
  permanently dark.
- **A single instructor-entered percentage per enrollment (rejected).** Far cheaper, but
  the number is unauditable, there is nothing for a future LMS to attach to, and
  editing a bare percentage invites drift the register makes impossible.
- **Richer marks (late/excused) or automated consequences (rejected).** Policy invention
  without SRS backing; a wrong guess here would have to be walked back once a real
  bylaw answer exists.

## Consequences

- New Enrollment-owned tables: `class_sessions` and per-(session, enrollment) marks;
  W'd enrollments keep historical marks but drop off future sessions.
- Session generation reads only the Offering's meeting rows and the Semester dates —
  regeneration/cancellation stays inside one seam a future timetabler would replace.
- The at-risk notification fires on the downward *crossing* (recompute around each
  register save), not on every subsequent mark — no notification spam, no stored state.
- Phase 4's Incomplete rule ("≥70% completed") is about coursework completion, not
  attendance; nothing here pre-empts it.
