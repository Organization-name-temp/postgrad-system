# Spec — Phase 3: Enrollment (Course Registration, Calendar, Offerings & Attendance)

**Status:** ready-for-agent
**Feature slug:** phase-3
**Phase:** 3 of the [Build Roadmap](../../docs/ROADMAP.md) (SRS §4.4 — Student Course Registration; §4.5/§4.6 as accounting values only; plus two owner-approved SRS extensions: course scheduling and attendance)
**Runtime:** Laravel 12 / PHP 8.4 ([ADR-0006](../../docs/adr/0006-runtime-baseline-laravel-12-php-84.md))
**Sources:** [ROADMAP §Phase 3](../../docs/ROADMAP.md) · [SRS §4.4](../../docs/SRS.md) (FR-REG-001…006), §4.11 (FR-CAL-001, partial) · [business-requirements](../../docs/business-requirements.md) (BR-14…26, BR-55/56, BR-70) · [CONTEXT.md](../../CONTEXT.md) (Course Offering, Semester, Enrollment Classification, Research Type, Class Session, Attendance) · ADRs [0002](../../docs/adr/0002-modular-monolith-seams.md)/[0005](../../docs/adr/0005-localhost-learning-build.md)/[0012](../../docs/adr/0012-cross-module-reads-by-id-and-dto.md)/[0014](../../docs/adr/0014-client-validation-mirrors-server-rules.md)/[0015](../../docs/adr/0015-administrative-override-of-scope-guards.md)/**[0017](../../docs/adr/0017-offering-based-registration.md)**/**[0018](../../docs/adr/0018-calendar-module-semester.md)**/**[0019](../../docs/adr/0019-attendance-session-register.md)**

---

## Problem Statement

Phase 2 ends with an accepted Applicant holding a University ID, an Advisor, and role
R02 — **and nothing a Student can actually do.** The academic lifecycle stops dead at
admission:

- **A Student** cannot see which courses run this semester, build a registration, or
  submit it to their Advisor. There is no semester, no schedule, no enrollment record —
  their Program's Curriculum is browsable in the Catalog but inert.
- **An Advisor** was assigned to every admitted Student in Phase 2 and has nothing to
  advise on: no queue of registration requests, no way to approve, trim, or reject one,
  and no view of an advisee's schedule.
- **Nobody can say what "this semester" means.** No Semester exists anywhere — no dates,
  no registration window, no add/drop or withdrawal deadlines — so none of the SRS's
  period-gated behavior (FR-REG-004) can even be expressed.
- **Nobody records who teaches what.** Phase 4's grading ("instructors enter marks for
  courses they teach", FR-GRD-001) has no Instructor→Course link to stand on.
- **An Instructor** has no roster and no way to keep an attendance register, so the one
  attendance rule in the SRS (Audit "must attend ≥75%", Appendix D) is unenforceable and
  invisible.
- **A developer** starting Phase 4 (Grading) would have no Enrollment for a Grade to
  attach to — the entire remaining roadmap (Grades → Standing → Transcript → Reports)
  hangs off records this phase creates.

## Solution

Build two new modules on the existing skeleton: a supporting **`Calendar`** module — the
single authority for the **Semester** (Term Fall/Spring + year) and its five
order-validated period dates ([ADR-0018](../../docs/adr/0018-calendar-module-semester.md)) —
and the **`Enrollment`** domain module, which owns **Course Offerings**, **Registration
Requests**, **Enrollments**, **Class Sessions**, and **Attendance**.

Administrators (under the existing `calendar.manage` permission) create Semesters and
populate them with **Course Offerings** — one Course taught in one Semester, carrying its
Instructor, an optional seat capacity, and weekly meeting times. A Student's eligible list
is their Curriculum ∩ this semester's Offerings (plus other graduate Programs' curriculum
courses as External Electives), each line classified five ways — Preparatory / Core /
Internal Elective / External Elective / Research Course (BR-23…26) — with the Research
Course type-matched (PBL for MSc, Seminar for PhD) via a new `research_type` on the
Catalog Course.

The Student builds a **cart** (min 6 / max 12 CH, config-driven, validated as a batch with
a client-side mirror per [ADR-0014](../../docs/adr/0014-client-validation-mirrors-server-rules.md))
and submits a **RegistrationRequest**. The assigned **Advisor** reviews it **line by line**
— approving or trimming lines, never adding — and on approval **Enrollments are minted
atomically**, each referencing its Offering and snapshotting its classification and
registration-time CH ([ADR-0017](../../docs/adr/0017-offering-based-registration.md)).
After approval the FR-REG-004 periods govern everything: free add/drop in weeks 1–2
(advisor notified, no veto), add-only **late-add requests** in week 3, **self-serve
withdrawal** (Course Status `W`) until the deadline, nothing after. The full 10-value
`CourseStatus` enum ships with the Appendix-D counting matrix encoded, but only
Regular / Retake / Audit / W are reachable — Retake always **derived** (W/AU attempts
don't burn the single allowed retake), never chosen.

Each Offering's weekly meeting times generate **Class Sessions** across the Semester; the
assigned Instructor cancels sessions (the holiday answer) and keeps a binary
present/absent register per session ([ADR-0019](../../docs/adr/0019-attendance-session-register.md)).
Attendance percentages are always derived, and the only consequence is a **flag**: an
Audit Enrollment first crossing below 75% gets a chip and a notification — never an
automated status change.

Five new in-app **Notification** types keep the loop closed (submitted → advisor,
decided → student, adjusted/withdrawn → advisor, audit-at-risk → student). RBAC uses only
permissions that have existed since Phase 0; register-for-student ships minimally
(Advisor-composed for an advisee is **born Approved**; SA-composed goes Pending as usual);
[ADR-0015](../../docs/adr/0015-administrative-override-of-scope-guards.md)'s administrative
override applies to all new scope guards with admin writes audited. A deterministic seeder
stands up **two out-of-phase Semesters** — Spring 2026 running and inside its withdrawal
window with attendance history, Fall 2026 with registration open — so every flow demos on
seed day.

## User Stories

**Calendar administration — Semesters (`calendar.manage`: SA / Dean / SysAdmin)**

1. As a Student Affairs administrator, I want to create a Semester (Term + year with start/end, registration window, add/drop end, late-add end, withdrawal deadline), so that the academic term the university runs actually exists in the system (FR-CAL-001).
2. As a Student Affairs administrator, I want the period dates validated for ordering (registration opens before semester start; add/drop < late-add < withdrawal deadline ≤ semester end), so that I cannot configure an impossible calendar.
3. As a Student Affairs administrator, I want duplicate Semesters (same Term + year) rejected, so that "Fall 2026" means exactly one thing.
4. As a Student Affairs administrator, I want to edit a Semester's dates but never delete a Semester, so that I can correct the calendar without orphaning academic records.
5. As a Student Affairs administrator, I want the Semesters list to show each Semester's derived state (upcoming / registration open / running / ended), so that I can see the calendar at a glance without stored flags.

**Calendar administration — Course Offerings (`calendar.manage`)**

6. As a Student Affairs administrator, I want to create a Course Offering for a Semester by picking a Course from the Catalog, assigning an Instructor, and setting an optional seat capacity, so that "what runs this semester" is recorded and registrable.
7. As a Student Affairs administrator, I want to give each Offering weekly meeting times (weekday + start/end, one or more), so that schedules and Class Sessions have something to derive from.
8. As a Student Affairs administrator, I want an Offering's own meeting times validated against self-overlap, so that a single course cannot be scheduled against itself.
9. As a Student Affairs administrator, I want a second Offering of the same Course in the same Semester rejected, so that one Course runs at most once per term (relaxable to sections later per ADR-0017).
10. As a Student Affairs administrator, I want to edit an Offering (instructor, capacity, meetings) and to remove one only while nothing references it, so that live rosters are never silently destroyed.
11. As a Student Affairs administrator, I want an Instructor picker listing R04 users, so that I can assign teaching without knowing user ids.

**Catalog — Research Type (`catalog.manage`)**

12. As a catalog manager, I want to set a Course's Research Type (PBL or Seminar, or neither) when creating or editing it, so that level-700 research courses can be matched to the right degree at registration (BR-25, the Phase 1 deferral).

**Student — eligibility & the registration cart (`courses.register_self`, R02)**

13. As a Student, I want to see the semester whose registration window is open now, with its key dates, so that I know what I am registering for and until when.
14. As a Student, I want an eligible-course list grouped by Enrollment Classification (Preparatory / Core / Internal Elective / External Elective / Research Course), so that I understand what role each course plays in my Program (BR-14…16, BR-23…26).
15. As a Student, I want each eligible line to show the Offering's instructor, meeting times, and seats remaining, so that I can choose sensibly.
16. As a Student, I want courses in my Curriculum with no Offering this semester to be absent from the list, so that I can only pick what actually runs (ADR-0017).
17. As a Student, I want External Electives — courses from other graduate Programs' curricula at my degree's level — offered in the list and clearly labeled, so that I can use my one external allowance deliberately (BR-24, BR-26).
18. As an MSc Student, I want my Research Course slot to match only level-700 PBL courses (Seminar for PhD), so that BR-25 is enforced rather than remembered.
19. As a Student, I want to build a cart with a live credit-hour total and inline validation mirroring the server rules, so that I fix problems before submitting (ADR-0014).
20. As a Student, I want to mark a cart line as Audit instead of Regular, so that I can sit a course without credit; I never choose "Retake" — the system derives it.
21. As a Student, I want submission blocked below 6 or above 12 CH with a clear message, so that the load rules are unambiguous (FR-REG-001).
22. As a first-semester Student, I want the 6-CH floor waived when registering for my enrollment term, so that a reduced first load is possible (FR-REG-001; tightens to exempted-only in Phase 5).
23. As a Student, I want a time-clash between any two chosen Offerings (or with my existing enrollments) rejected, so that I cannot register an impossible schedule.
24. As a Student, I want a full Offering (capacity reached) to be visibly full and rejected on submission, so that seats are honest.
25. As a Student, I want registering the same course twice in one semester blocked (including Regular + Audit of the same course), so that duplicates cannot exist.
26. As a Student re-registering a course I previously took for credit, I want the line derived and labeled as Retake and a second retake of the same course blocked, so that FR-REG-006's cap is enforced.
27. As a Student who withdrew from (W) or audited (AU) a course, I want re-registering it to count as a fresh Regular attempt, so that non-graded attempts don't burn my one retake.
28. As a Student, I want a second External Elective blocked while one is on my record, and a second Research Course likewise, so that the composition caps hold (external ≤ 1 allowance, research = exactly 1).
29. As a Student, I want to submit my cart only while the registration window is open and only one request pending at a time, so that the pipeline stays orderly.
30. As a Student, I want my submitted request visible with its status (Pending / Approved / Rejected), per-line outcomes, and the Advisor's note, so that I always know where my registration stands (FR-REG-003.4).
31. As a Student whose request was rejected, I want to build and submit a new cart while the window is open, so that a rejection is recoverable.

**Student — schedule & period-gated changes**

32. As a Student, I want a My Schedule view per semester showing each Enrollment with its Course Status chip, classification, meeting times, and attendance percentage, so that my academic present is one screen (FR-REG-005).
33. As a Student with an approved registration, I want to add an eligible course directly during weeks 1–2 (add/drop period) without advisor approval, so that "add freely" is real (FR-REG-004) — my Advisor is notified, not asked.
34. As a Student, I want to drop a course directly during weeks 1–2 — removed without a trace — but be blocked from dropping below the CH floor unless my first-semester exception applies, so that free drops cannot hollow out my load.
35. As a Student without an approved initial registration, I want free add/drop unavailable, so that the Advisor can never be bypassed on the initial schedule.
36. As a Student, I want adding an External Elective to be possible only via a request (initial or late-add), never a free add, so that BR-24's advisor approval is never skipped.
37. As a Student during week 3 (late-add period), I want to submit an add-only late-add request to my Advisor, so that late joins follow FR-REG-004's "add with approval".
38. As a Student, I want to withdraw from a course, self-serve with a confirmation, from after late-add until the withdrawal deadline — recording status W permanently and notifying my Advisor, so that FR-REG-004's withdrawal row works with no invented approval step.
39. As a Student, I want withdrawal to carry no CH-floor check, so that I am not trapped in a course to preserve a number.
40. As a Student after the withdrawal deadline, I want all schedule actions gone from my UI (and rejected by the server), so that "must complete" is unambiguous.
41. As a Student, I want the current period (and what I can do right now) shown on My Schedule, so that the calendar's consequences are legible.

**Advisor — reviewing requests (`registration.approve`, R03)**

42. As an Advisor, I want a queue of Pending requests from my advisees — resolved live, so advisees reassigned to me appear and departed ones vanish — so that my worklist is always correct.
43. As an Advisor, I want a request to show the student's context (Program, degree, enrollment term, current semester load) beside the lines, so that I can judge the whole schedule.
44. As an Advisor, I want each line to show its classification, CH, meeting times, and any derived Retake label, so that nothing about the request is opaque.
45. As an Advisor, I want to approve or reject individual lines and attach a note, so that "modify" means trimming with reasons — never adding courses the student didn't choose (FR-REG-003.3).
46. As an Advisor, I want the trimmed result re-validated (CH floor unless excepted, caps), so that my modification cannot produce an illegal schedule.
47. As an Advisor, I want to reject a whole request with a note, so that "please build a different cart" is one honest action.
48. As an Advisor, I want approval to mint the Enrollments immediately and atomically and notify the student, so that a decision is a fact, not a promise (FR-REG-003).
49. As an Advisor, I want notifications when an advisee free-adds, free-drops, or withdraws, so that I stay informed of schedule changes I don't gate (BR-70).
50. As an Advisor, I want to compose and submit a registration for my own advisee (`courses.register_for_student`) that is born Approved — I am its approver — so that I can register a student who cannot self-serve without approving my own paperwork.
51. As an Advisor, I want to view an advisee's schedule and attendance, so that monitoring doesn't require asking the student.

**Student Affairs — registration oversight**

52. As Student Affairs staff, I want the request queue unscoped (all advisors' advisees) since I hold `registration.approve`, so that I can unblock any stuck request.
53. As Student Affairs staff, I want to compose a registration for any student that goes to their Advisor as a normal Pending request, so that helping a student never silently bypasses their Advisor.

**Instructor — offerings, sessions & attendance (authorization by assignment, R04)**

54. As an Instructor, I want a My Offerings list showing what I teach per semester, so that my teaching load is visible (the Phase 4 grading link, born here).
55. As an Instructor, I want each Offering's roster (active Enrollments with mode labels — Regular/Retake/Audit), so that I know who is in my room; withdrawn students keep their history but leave future sessions.
56. As an Instructor, I want Class Sessions auto-generated from my Offering's meeting times across the Semester, so that the register exists without data entry.
57. As an Instructor, I want to cancel a session (Eid, illness) so that it never counts in any denominator (ADR-0019).
58. As an Instructor, I want to mark present/absent per student per session, and correct past marks, so that the register reflects reality.
59. As an Instructor, I want each student's derived attendance percentage on the roster, with an audit-at-risk chip when an Audit Enrollment sits below 75%, so that Appendix D's one attendance rule is visible.
60. As an Instructor who is not the Offering's assignee, I want its register read-only/forbidden, so that assignment is the authority (no new permission; the Phase 2 interviewer precedent).

**Notifications (in-app bell, bilingual, Phase 2 mechanics)**

61. As an Advisor, I want `registration.submitted` when an advisee (or SA on their behalf) submits, so that queue work finds me.
62. As a Student, I want `registration.decided` with the outcome and per-line results, so that I learn my schedule the moment it exists.
63. As an Advisor, I want `enrollment.adjusted` / `enrollment.withdrawn` on free changes and withdrawals, so that BR-70's "registration approvals" loop closes in-app.
64. As a Student, I want `attendance.audit_at_risk` exactly once when my Audit Enrollment first crosses below 75%, so that I am warned without being spammed.

**Administrators (R09/R10 — ADR-0015 override)**

65. As a System Administrator, I want to read all queues, requests, schedules, and registers unscoped, so that oversight needs no impersonation.
66. As a System Administrator, I want scope-guarded writes (deciding requests, recording attendance) to succeed for me but write an Audit Log row, so that override is possible and accountable.

**Developer / demo**

67. As a developer, I want a deterministic idempotent seeder — two out-of-phase Semesters (Spring 2026 running, in its withdrawal window, with sessions + attendance incl. an AU below 75%; Fall 2026 with registration open now), Offerings over the Phase 1 anchor programs, and students resting in every interesting state (pending request, W, derived Retake) — so that every screen demonstrates on seed day.
68. As a developer, I want the OpenAPI spec and Orval client regenerated with the new endpoints (Scribe `@urlParam` examples on every model-bound route), so that the typed frontend contract never drifts.

## Implementation Decisions

**Modules & seams**

- Two new modules, both `Public`/`Internal` per ADR-0002/0007: supporting **Calendar**
  (Semester + Term enum + read facade; no dependency on any other domain module) and
  domain **Enrollment** (Offerings, RegistrationRequests, Enrollments, ClassSessions,
  Attendance).
- New deptrac edges: `Enrollment_Internal → {Identity, Catalog, Calendar, Notifications}\Public`;
  `App → {Enrollment, Calendar}\Public`. Every `Internal` stays sealed.
- **`Enrollment\Public` ships empty (routes only).** Phase 4 builds the offering/roster
  read surface against grading, its first consumer (ADR-0017).
- Catalog grows the long-deferred cross-module read facade: a Course reader (by id/ids →
  summary DTO incl. code, bilingual names, level, total CH, research type, active) and a
  Curriculum reader (entries for a Program; "which active graduate Programs' curricula
  contain course X" for the External-Elective test). `CourseCategory` and `CourseLevel`
  move from Catalog Internal to Public. All cross-module references are opaque ids + DTOs,
  no FKs (ADR-0012).
- Identity\Public gains `studentIdsAdvisedBy(userId)`; the advisor queue and all advisee
  scoping resolve **live** (open requests follow advisor reassignment). An R04
  (instructor) listing endpoint mirrors the Phase 2 advisors-list precedent.

**Calendar (ADR-0018)**

- `Semester`: Term enum {Fall, Spring} + year (unique together), five typed period dates
  — start/end, registration opens/closes, add/drop end, late-add end, withdrawal deadline
  — with a cross-field ordering invariant (registration opens before start; registration
  closes no later than add/drop end; add/drop < late-add < withdrawal deadline ≤ end).
- "Registration open" / "running" / current-period are always **derived from dates**;
  no stored flags. Semesters are never hard-deleted.
- CRUD under the existing `ManageAcademicCalendar` permission. AdmissionCycle is
  untouched; Cycle↔Semester and `students.enrollment_term`↔Semester resolve by natural
  key (Term + year), never FK.
- Grade-submission deadline, final-exam period, and holiday/break periods are **not**
  modeled (their consumers arrive in later phases; session cancellation covers holidays).

**Course Offering (ADR-0017)**

- One Course taught in one Semester: instructor (users ref), optional integer capacity,
  ≥1 weekly meeting rows (weekday + start/end). Unique (semester, course) enforced now;
  meeting rows must not self-overlap. Managed under `calendar.manage` (the line:
  `catalog.manage` = what the university *can* teach; `calendar.manage` = what *runs*
  this semester). Deletable only while unreferenced by enrollments/sessions.
- Catalog Course gains nullable `research_type` enum {PBL, Seminar} (+ form field, under
  `catalog.manage`); no validation coupling it to level — the classifier simply won't
  match a 700 research slot without it.

**Registration workflow**

- `RegistrationRequest` = student + semester + kind {Initial, LateAdd} + status
  {Pending, Approved, Rejected} + lines. Each line references an **Offering** and carries
  the requested mode {Regular, Audit}; Retake is **derived**, never requested. No
  server-side Draft — the cart lives in the SPA. At most one Pending request per
  (student, semester); resubmission allowed after rejection while the window permits.
- Advisor decision is **line-item**: approve/reject per line + an optional note; the
  approved subset is re-validated (CH floor unless excepted, caps, clashes, capacity) at
  decision time; approving ≥1 line ⇒ request `Approved`, all lines rejected ⇒ `Rejected`.
  Advisors never add lines. Approval mints Enrollments **atomically in the decision
  transaction** (the ADR-0013 accept→mint precedent).
- Each Enrollment snapshots at mint: its five-way **Enrollment Classification** and the
  course's **total CH**; display facts are read live via the Catalog facade (ADR-0017).
- Period gates (all read from Calendar): initial requests only while the registration
  window is open; free add/drop (direct actions, no approval, advisor notified) from
  semester start to add/drop end **and only for students with an approved Initial request
  for that semester**; LateAdd requests only during the late-add window, add-only;
  self-serve withdrawal (status → W, permanent) from late-add end to the withdrawal
  deadline, **no CH-floor check**; nothing after. Drops hard-delete the Enrollment.
  External Electives are only legal inside a request (Initial or LateAdd) — never free-added.
- Register-for-student (`courses.register_for_student`): SA composes for any student →
  normal Pending to the advisor; an Advisor composes for their own advisee → request is
  **born Approved** and mints immediately (composer is the approver). Same validators.

**Rules engines (all pure, targeted-unit-tested)**

- **CH validator:** min 6 / max 12 from new `config/enrollment.php` (env-overridable,
  the `config/admissions.php` pattern); counts Regular+Retake+Audit loads; first-semester
  exception = target semester equals the student's enrollment term (Phase 5 tightens to
  exempted-only); approaching-graduation exception **deferred to Phase 5** (needs
  Remaining CH) — the validator takes an exceptions context so Phase 5 plugs in without
  reshaping the seam. No advisor override hatch.
- **Classifier:** Preparatory / Core / Internal Elective (own curriculum ResearchElective
  at 500 MSc / 600 PhD) / Research Course (own curriculum, level 700, research type
  matching degree) / External Elective (not in own curriculum; in another active graduate
  Program's curriculum; level per degree). Caps at registration: external ≤ 1 ever
  (allowance), research course ≤ 1; full 6/6/9 composition accounting is Phase 5.
- **Retake derivation:** one enrollment per (student, course, semester); prior for-credit
  attempt (Regular/Retake) ⇒ new line is Retake, max 1 per course; prior W or AU attempts
  don't burn it. Grade gates (below-C+ rules, CGPA-Improvement routing, failed-external
  freeing the cap) are **Phase 4 hooks, not built**.
- **Meeting overlap:** one interval-overlap seam used for offering self-overlap and
  student schedule clashes (cart lines + existing enrollments).

**Attendance (ADR-0019)**

- `ClassSession` rows generated from the Offering's meeting rows across the Semester
  dates; the assigned Instructor may cancel a session (cancelled never counts) and mark
  binary present/absent per active Enrollment; marks are correctable. Percentage is
  always derived: present ÷ non-cancelled sessions recorded to date.
- Authorization **by assignment** (no new permission; §3.2 matrix untouched). ADR-0015
  override: R09/R10 read unscoped and may write with an Audit Log row.
- The only consequence anywhere: the **audit-at-risk flag** (AU enrollment < 75%) — a
  chip plus one `attendance.audit_at_risk` notification fired on the downward crossing
  (recompute around each register save), never an automated status change.

**Course Status accounting**

- `CourseStatus` enum ships complete — all 10 Appendix-D values with the counting matrix
  (transcript / CGPA / required / attended / earned flags) encoded as a static matrix on
  the enum (the ADR-0008 pattern). Only Regular, Retake, Audit, W are reachable through
  Phase 3 flows; a drop is a deletion, not a status.

**Notifications**

- Five new `NotificationType` cases: `registration.submitted` (advisor),
  `registration.decided` (student; payload carries outcome + per-line results),
  `enrollment.adjusted` (advisor; payload action add/drop + course),
  `enrollment.withdrawn` (advisor), `attendance.audit_at_risk` (student). Bilingual
  SPA-rendered payloads, dot-free i18n aliases — the Phase 2 mechanics. No artisan
  SLA/reminder commands (no SRS registration SLA exists).

**Frontend**

- New `features/enrollment` (new "enrollment" nav section, Phase 2.5 section mechanism):
  student Registration page (eligible list grouped by classification, cart with live CH
  total + ADR-0014 Zod mirror, submit + request timeline) and My Schedule (per-semester
  enrollments with StatusChips, attendance %, period-gated action buttons; server stays
  authoritative); advisor queue + line-item review page + compose-for-advisee; instructor
  My Offerings → roster with session-register grid (marks, cancel, at-risk chips).
- `features/admin` grows Semesters and per-semester Offerings screens beside Cycles
  (calendar nav section).
- No new frontend dependencies; meeting times use plain time inputs; attendance detail
  stays instructor-side (students see only their percentage).

## Testing Decisions

Good tests here assert **externally observable behavior at the highest seam** — the HTTP
response, the database rows that represent domain facts (an Enrollment minted, a W
recorded, a session cancelled), and the notifications a user would see — never internal
call structure.

- **Primary seam — Pest API-feature tests** (the Phase 1/2 pattern: `actingAsRole`,
  real Postgres, full HTTP): Semester/Offering CRUD + invariants, eligibility listing,
  cart submission + every validation rejection (CH, caps, clash, capacity, duplicates,
  window), advisor line-item decisions + atomic mint, free add/drop, late-add, withdrawal,
  registers + at-risk, RBAC/scoping incl. ADR-0015 override audits, notification rows.
  **Period behavior is tested by freezing time** (`travelTo`) against seeded semester
  dates — same seam, deterministic.
- **Targeted-unit seam** (the `CreditHours`/`BusinessDays` precedent — small pure
  `Internal\Support` classes tested directly): CH validator + exceptions context, 5-way
  classifier + caps, retake derivation, interval overlap, session generation +
  cancellation arithmetic, attendance percentage + crossing detection, semester
  date-ordering invariant, and the CourseStatus counting matrix.
- **One multi-role Playwright smoke** (real stack, the Phase 2 capstone precedent):
  admin creates a Fall Offering → student builds/submits a cart → advisor trims a line +
  approves → student sees the schedule, withdraws from a Spring course (W chip) →
  instructor cancels a session, marks a register until audit-at-risk appears. Axe on
  every new screen; per-step screenshots as artifacts.
- **Sparse Vitest** for non-trivial components only (cart CH mirror, register grid),
  reusing the mocked-`http`-with-real-generated-hooks pattern from Phase 2.
- No console-command seam — Phase 3 ships no artisan commands.

## Out of Scope

- **Grades and everything gated on them** (Phase 4): FW/Incomplete/CGPA-Improvement
  becoming reachable; below-C+ retake gates (BR-55/56); routing passed-course
  re-registrations to CGPA-Improvement; the failed-external-elective cap-freeing rule;
  grade-submission-deadline and final-exam-period dates.
- **Exemption / Transfer / Waiver workflows and composition accounting** (Phase 5):
  automatic exemptions, the approaching-graduation CH exception, tightening the
  first-semester exception to exempted-only, Credit-Hour Summary, full 6/6/9 accounting.
- **Rooms, physical timetabling, conflict engines** (SRS §2.4) and **all LMS features**
  (materials, assessments, assignments) — structurally seeded only, per ADR-0017.
- Multi-section Offerings; holiday/break calendar; registration SLAs or reminder
  commands; refunds/billing (§2.4); email/SMS (ADR-0005); attendance policy beyond
  binary marks (late/excused, automated consequences).

## Further Notes

- **Documented Phase 4/5 hooks** (decided, deliberately unbuilt): the CH validator's
  exceptions context (Phase 5 plugs Remaining CH), grade-gated retake rules and the
  external-cap freeing rule (Phase 4), `Enrollment\Public`'s roster surface (Phase 4
  grading is its first consumer).
- The Advisor↔advisee link resolves live everywhere; if an advisor is reassigned while a
  request is Pending, the request follows the student to the new advisor by construction.
- Scribe/Orval discipline from Phases 1–2 applies: `@urlParam` examples on model-bound
  routes, populated `@response` examples (no empty arrays / nulls), snake_case nested
  route params.
- Seeder windows are computed **relative to seed time** (natural keys stay Fall/Spring
  2026) so the demo is evergreen; tests never rely on the seeder (factories + `travelTo`).
- After this spec: `/to-tickets` slices it into vertical tickets under
  `.scratch/phase-3/issues/`, then build proceeds one ticket at a time on a
  `phase-3-enrollment` branch off `main` (the per-phase pattern).
