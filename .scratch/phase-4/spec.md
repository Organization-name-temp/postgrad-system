# Spec — Phase 4: Grading & GPA/CGPA

**Status:** ready-for-agent
**Feature slug:** phase-4
**Phase:** 4 of the [Build Roadmap](../../docs/ROADMAP.md) (SRS §4.7 — Grading & GPA Calculation; §4.7.2 is intentionally blank in the SRS and is resolved here; plus the grade-gated re-registration rules of §4.4.6 deferred from Phase 3)
**Runtime:** Laravel 12 / PHP 8.4 ([ADR-0006](../../docs/adr/0006-runtime-baseline-laravel-12-php-84.md))
**Sources:** [ROADMAP §Phase 4](../../docs/ROADMAP.md) + [§5 GPA/CGPA agenda](../../docs/ROADMAP.md) (closed by this spec) · [SRS §4.7](../../docs/SRS.md) (FR-GRD-001…003), §4.4.6 (FR-REG-006), Appendix A (grading scale), Appendix C (parameters), Appendix D (counting rules) · [business-requirements](../../docs/business-requirements.md) (BR-55, BR-56) · [CONTEXT.md](../../CONTEXT.md) (Grade, Mark, Letter Grade, Grade Sheet, Attempt, Incomplete, Fail Withdrawal, CGPA Improvement, Quality Points, GPA/CGPA) · ADRs [0002](../../docs/adr/0002-modular-monolith-seams.md)/[0005](../../docs/adr/0005-localhost-learning-build.md)/[0008](../../docs/adr/0008-rbac-enums-static-matrix.md)/[0010](../../docs/adr/0010-catalog-curriculum-model.md)/[0012](../../docs/adr/0012-cross-module-reads-by-id-and-dto.md)/[0013](../../docs/adr/0013-acceptance-handoff-student-anchor.md)/[0014](../../docs/adr/0014-client-validation-mirrors-server-rules.md)/[0015](../../docs/adr/0015-administrative-override-of-scope-guards.md)/[0017](../../docs/adr/0017-offering-based-registration.md)/[0018](../../docs/adr/0018-calendar-module-semester.md)/[0019](../../docs/adr/0019-attendance-session-register.md) · **new ADRs 0020–0023**

---

## Problem Statement

Phase 3 ends with Students holding real Enrollments in real Course Offerings, Instructors
holding rosters and attendance registers, and Advisors approving registrations — **and no
academic outcome anywhere.** Every Enrollment is permanently in progress:

- **An Instructor** teaches a course, keeps its attendance register, and then has nowhere
  to record what anybody achieved. FR-GRD-001 ("instructors enter marks for courses they
  teach") has a roster to stand on since Phase 3 but no gradebook to write into.
- **A Student** can see their schedule and their attendance percentage but never a grade,
  a semester GPA, or a CGPA. The single number the whole degree turns on — the 3.0 CGPA
  required to graduate — does not exist in the system.
- **A Department Head** has no surface at all. The role holds `grades.finalize` since
  Phase 0 and SRS §4.7.1.4 routes every signed grade to them for review, but Identity has
  no Head↔Department assignment, so the system cannot even name who reviews what.
- **Phase 3's registration rules are knowingly half-built.** `RetakeDeriver` allows a
  second attempt after any prior for-credit Enrollment *regardless of grade*, so a Student
  who passed a course can silently "retake" it; `CourseStatus::CgpaImprovement`,
  `::Incomplete` and `::FailWithdrawal` ship with live Appendix-D counting rows and no way
  to reach them; and FR-REG-006.4's "failed an elective, take a different one" is blocked
  outright by the lifetime external-elective cap.
- **Nothing can be deferred.** An Instructor with a student who finished 80% of the
  coursework has only two options, both wrong: invent a mark, or leave the roster blank
  forever. SRS §4.7.3's entire Incomplete lifecycle is unreachable.
- **`Enrollment\Public` ships empty** ([ADR-0017](../../docs/adr/0017-offering-based-registration.md)
  deferred it to this phase deliberately), so no module can read a roster or an
  Enrollment. Phase 5 (Standing, Credit-Hour Summary) and Phase 6 (Transcript) both hang
  off numbers this phase must produce.
- **SRS §4.7.2 is blank.** The GPA/CGPA formula, the counting interpretation, and the
  rounding rule are simply absent from the requirements. A developer cannot implement
  "CGPA" without seven decisions the SRS never made.

## Solution

Build the **`Grading`** module: the **Grade** (a Mark, and the Letter Grade and GPA points
derived from it), the **Grade Sheet** that carries a round of an Offering's grades through
Instructor signature to Department-Head finalization, the **Incomplete** lifecycle with
its derived deadline and automatic conversion to Fail Withdrawal, and the **GPA/CGPA read
model** — computed on demand from authoritative records, never stored
([ADR-0020](../../docs/adr/0020-grading-owns-gpa-read-model.md), which supersedes
[architecture.md](../../docs/architecture.md) §5's placement of GPA in Records).

The blank §4.7.2 is closed with a credit-weighted quality-points formula, and Appendix A's
non-contiguous percentage bands are replaced with a total, uniform scale
([ADR-0021](../../docs/adr/0021-grading-scale-uniform-bands.md)).

Around it, three modules gain exactly what the grades need and nothing more.
**`Enrollment\Public`** finally ships — a roster and enrollment read surface plus the four
commands that let Grading drive Enrollment's own `course_status`, keeping Enrollment the
sole writer of its column; and Grading pushes a single `earned` boolean back at
finalization so every registration gate stays a pure function inside Enrollment
([ADR-0022](../../docs/adr/0022-grade-outcome-pushed-to-enrollment.md)). **`Identity`**
gains the Department-Head assignment, whose department reference is nullable exactly as
`Course.department_id` is, so the university-wide Preparatory courses route to a Head with
no special case. **`Calendar`** gains one column, the grade-submission deadline.

With grades in hand, the three registration rules Phase 3 documented as hooks become real:
a second Attempt is a **Retake** or a **CGPA Improvement** depending on whether the first
earned its credit, two for-credit Attempts is the lifetime cap for any Course, and a failed
external elective releases the external allowance so FR-REG-006.4 works.

---

## User Stories

### Instructor — entering and signing grades

1. As an Instructor, I want each Offering I teach to show whether its grades are unstarted, in progress, awaiting review, returned to me, or finalized, so that I know what still needs my attention across every semester.
2. As an Instructor, I want to open a Grade Sheet for one of my Offerings, so that I can record what my students achieved.
3. As an Instructor, I want the Sheet to list every gradable Enrollment on the roster with the Student's University ID and name, so that I am grading the right people.
4. As an Instructor, I want Audit and Withdrawn Enrollments left off the Sheet, so that I am never asked for a mark that cannot exist.
5. As an Instructor, I want to enter one final percentage per Student, so that the system decides the letter rather than my having to remember the scale.
6. As an Instructor, I want the letter grade and its GPA points to appear the moment I type a mark, so that I can see a mistyped figure before I sign anything.
7. As an Instructor, I want each mark saved as I enter it rather than only on submit, so that a long roster is never lost to a closed tab.
8. As an Instructor, I want marks outside 0–100 rejected with a clear message, so that a slipped keystroke cannot enter the record.
9. As an Instructor, I want to see how many lines are still blank before I can sign, so that I do not submit a half-graded course.
10. As an Instructor, I want to sign the Sheet as one act, so that the whole course goes to the Department Head together, as SRS §4.7.1.4 describes.
11. As an Instructor, I want a signed Sheet to become read-only, so that nobody — including me — can quietly alter marks that are under review.
12. As an Instructor, I want to be told when the Department Head returns my Sheet, and to read their note, so that I know exactly what to correct.
13. As an Instructor, I want a returned Sheet to be editable again and re-signable, so that correcting it does not mean starting over.
14. As an Instructor, I want to grade only the Offerings I teach, so that no one else's course is exposed to me.
15. As an Instructor, I want to be reminded when a Semester's grade-submission deadline has passed and one of my Offerings has no finalized Sheet, so that I am nudged rather than silently late.

### Instructor — Incomplete

16. As an Instructor, I want to mark a Student Incomplete instead of entering a mark, so that a student who has done most of the work but cannot finish is not failed by default.
17. As an Instructor, I want to state the percentage of coursework the Student completed and a reason when I declare an Incomplete, so that the decision is justified on the record.
18. As an Instructor, I want an Incomplete refused when the completion figure is below the configured 70%, so that the SRS §4.7.3.1 threshold is enforced rather than trusted.
19. As an Instructor, I want a list of my outstanding Incompletes with each one's resolution deadline, so that I can chase them before they expire.
20. As an Instructor, I want to resolve an Incomplete by entering the outstanding mark, so that a student who finishes their work gets the grade they earned.
21. As an Instructor, I want a resolution to go through the same signature and Department-Head review as any other grade, so that no grade in the system escapes a second pair of eyes.
22. As an Instructor, I want a resolved grade to count in the semester the course was actually taken, so that the Student's record reflects when they studied it.

### Instructor — corrections

23. As an Instructor, I want to open a Correction round for a finalized Sheet, so that a mistyped mark is not permanent.
24. As an Instructor, I want a Correction to contain only the lines I am changing, so that a whole class's grades do not disappear from their transcripts while one is fixed.
25. As an Instructor, I want the corrected grade to supersede the earlier one while the earlier one stays visible in the Student's grade history, so that the record shows what changed rather than pretending it never happened.

### Department Head — review

26. As a Department Head, I want a queue of the signed Grade Sheets awaiting my review, so that I know what is waiting on me.
27. As a Department Head, I want the queue limited to my own Department's Offerings, so that I am not shown another Department's grades.
28. As a Department Head assigned university-wide, I want the Preparatory Offerings that belong to no Department to appear in my queue, so that PRE401–404 grades are reviewed like any other.
29. As a Department Head, I want to open a Sheet and see every mark, letter, and Incomplete with its stated reason, so that I can judge the whole course rather than one line.
30. As a Department Head, I want to see the Offering's grade distribution alongside the marks, so that an implausible spread is visible at a glance.
31. As a Department Head, I want to finalize a Sheet, so that its grades become part of the academic record.
32. As a Department Head, I want to return a Sheet with a note instead of finalizing it, so that I can send a suspect grade back rather than accepting or silently editing it.
33. As a Department Head, I want to be unable to edit marks myself, so that authorship of a grade always stays with the Instructor who taught the course.
34. As a Department Head, I want a finalized Sheet to be permanently closed, so that "finalized" means something.

### Student — grades and GPA

35. As a Student, I want a My Grades page grouped by Semester, so that my academic history reads chronologically.
36. As a Student, I want each Semester's GPA shown with its courses, so that I can see how a single term went.
37. As a Student, I want my CGPA shown prominently, so that I always know where I stand against the 3.0 needed to graduate.
38. As a Student, I want a dash rather than 0.00 before I have any graded course, so that a new record does not look like a catastrophic one.
39. As a Student, I want each course to show its credit hours, letter grade, and whether it earned its credit, so that I understand why my CGPA and my earned hours differ.
40. As a Student, I want nothing shown until my Department Head has finalized the Sheet, so that I never see a provisional grade that later changes.
41. As a Student, I want to be notified when a grade is finalized, so that I learn it without refreshing a page for weeks.
42. As a Student, I want a clear marker on any prep or core Course I passed below C+, telling me I must retake it, so that FR-REG-006.5's obligation is visible rather than buried in a bylaw.
43. As a Student, I want a superseded earlier attempt shown on my record but visibly excluded from my CGPA, so that I can see the rule working rather than wondering why the numbers do not add up.
44. As a Student, I want to see both grades when I failed an elective and passed a different one, so that FR-REG-006.4's "all grades count" is not a surprise later.
45. As a Student, I want my grades and GPA to read correctly in Arabic with RTL layout, so that the record is usable in either language.

### Student — Incomplete

46. As a Student, I want an Incomplete shown on my record with its resolution deadline, so that I know I have unfinished work and by when.
47. As a Student, I want to be notified when an Incomplete is declared against me, with the reason, so that it is never a silent decision.
48. As a Student, I want reminders as the deadline approaches, so that I do not lose a course to a date I forgot.
49. As a Student, I want to be told when an unresolved Incomplete becomes a Fail Withdrawal, so that a change with real consequences is not discovered months later.
50. As a Student, I want an Incomplete to count toward neither my CGPA nor my earned hours while it is outstanding, so that an unfinished course neither helps nor harms me until it is decided.

### Advisor

51. As an Advisor, I want to see each advisee's grades, semester GPAs, and CGPA, so that I can advise on the basis of their actual record.
52. As an Advisor, I want to see which of an advisee's Courses carry a must-retake obligation, so that I can plan their next registration around it.
53. As an Advisor, I want to see an advisee's outstanding Incompletes, so that I know what is unresolved before approving more courses.
54. As an Advisor, I want to see only my own advisees, so that the scope guard on grades matches the one on registrations.

### Student Affairs

55. As a Student Affairs administrator, I want to enter marks on any Draft Grade Sheet, so that grades arriving on paper can be keyed in centrally, which is what `grades.enter` in the §3.2 matrix means.
56. As a Student Affairs administrator, I want to be unable to sign a Sheet, so that the Instructor remains the author of their own grades.
57. As a Student Affairs administrator, I want every mark I enter on someone else's Sheet audited, so that central data entry is traceable.
58. As a Student Affairs administrator, I want to see any Student's grades and CGPA, so that I can answer records enquiries.
59. As a Student Affairs administrator, I want to set each Semester's grade-submission deadline alongside its other period dates, so that the deadline lives with the calendar it belongs to.
60. As a Student Affairs administrator, I want the deadline validated as falling on or after the Semester's end, so that an impossible calendar cannot be saved.

### System Administrator

61. As a System Administrator, I want to assign a Department Head to a Department, so that signed Sheets have somewhere to go.
62. As a System Administrator, I want to assign a Department Head to the university-wide scope, so that the Preparatory courses that belong to no Department are reviewed too.
63. As a System Administrator, I want to see which Departments currently have no Head, so that I can spot grades that would strand in review.
64. As a System Administrator, I want the assignment screen gated on my role rather than a new permission, so that the RBAC matrix stays as the SRS fixed it.
65. As a System Administrator, I want my administrative override of the Grading scope guards audited, so that reaching past a Department boundary is always visible.

### Registration rules that grades unlock

66. As a Student who failed a Course, I want to re-register for it as a Retake, so that I can earn the credit I missed.
67. As a Student who passed a Course, I want re-registering for it to be recorded as a CGPA Improvement rather than a Retake, so that my record describes what I am actually doing.
68. As a Student, I want a third for-credit attempt at any Course refused, so that FR-REG-006.1's "maximum once" is real.
69. As a Student with an unresolved Incomplete in a Course, I want registration for that same Course refused, so that I cannot have two live attempts at once.
70. As a Student who failed my external elective, I want to register for a different one, so that FR-REG-006.4 works instead of being blocked by an allowance I already spent.
71. As a Student, I want an Audit or Withdrawn attempt to leave my Retake allowance untouched, so that Phase 3's rule still holds now that grades exist.

### Developer

72. As a developer, I want `Enrollment\Public` to expose roster and Enrollment reads as DTOs, so that Grading never touches Enrollment's tables and Phases 5 and 6 have the same seam.
73. As a developer, I want `Grading\Public` to expose grades and GPA/CGPA, so that Phase 5's Standing and Phase 6's Transcript consume one contract rather than re-deriving the arithmetic.
74. As a developer, I want the dependency between Enrollment and Grading to run one way only, so that neither module needs the other's test doubles and deptrac has no cycle to permit.
75. As a developer, I want the grading scale encoded as a static matrix in code, so that it is tested exhaustively at every band boundary rather than trusted.
76. As a developer, I want a deterministic seeder with a completed Semester and an already-overdue Incomplete, so that the whole lifecycle including conversion is demonstrable without editing dates by hand.
77. As a developer, I want every new model-bound route to carry a Scribe `@urlParam` example, so that the `openapi.yaml` gate does not flake in CI.

---

## Implementation Decisions

### Modules and seams

- **New `Grading` module** (`Public`/`Internal`, auto-discovered provider). It owns the
  Grade, the Grade Sheet, the Incomplete, and the GPA/CGPA read model.
  **[ADR-0020]** — this supersedes [architecture.md](../../docs/architecture.md) §5, which
  assigned the GPA/CGPA read model to Records. Records is not born until Phase 6 and is a
  document-issuance context; GPA is grade arithmetic and belongs beside the grades. Phase 6
  composes transcripts *from* `Grading\Public`.
- **New deptrac edges:** `Grading_Internal → {Identity, Catalog, Calendar, Enrollment,
  Notifications}\Public` and `App → Grading_Public`. `Grading_Public → Grading_Internal`
  for the binding, per the Calendar precedent. Every `Internal` stays sealed.
- **`Enrollment\Public` ships** ([ADR-0017]'s deferral, now due). Reads return DTOs, never
  Eloquent models, and cross-module references stay opaque ids with no FK ([ADR-0012]):
  - the gradable Enrollments of one Offering (enrollment id, student id, classification,
    credit-hour snapshot, course status)
  - one Student's Enrollments for the CGPA tally (plus course id, semester id)
  - Offering facts needed for routing and scoping (instructor id, course id, semester id)
    and the Offerings one Instructor teaches
  - commands: `recordOutcome`, `markIncomplete`, `resolveIncomplete`,
    `convertIncompleteToFailWithdrawal`
- **Dependency direction is one-way, `Grading → Enrollment`. [ADR-0022]** On finalization
  Grading pushes a single nullable `earned` boolean — "this Attempt reached C+ and earned
  its credit hours" — onto the Enrollment. Enrollment's registration gates therefore stay
  pure functions over its own rows, needing no Grading double; a `Public↔Public` cycle is
  avoided. Grading remains the sole owner of the Mark, Letter Grade and points; Enrollment
  learns only the one bit its own rules turn on.
- **Enrollment stays the sole writer of `course_status`.** Grading calls the facade
  commands synchronously inside the same transaction as the Sheet finalize, following
  [ADR-0013]'s acceptance hand-off shape. Enrollment privately remembers the
  pre-Incomplete status so resolution restores it; that memory is never exposed, so
  `CourseStatus` stays `Enrollment\Internal` and needs no promotion.
- **`GradeFinalized` domain event is for side-effects only** — notifications now, Phase 5's
  standing recompute later. It never carries the status write, which must be atomic.

### The grading scale — [ADR-0021]

SRS Appendix A's percentage bands are **not contiguous**: 90–92.99, 80–82.99 and 70–72.99
map to no letter, so a mark of 91 is unrepresentable. They are replaced by uniform
five-point bands, total over 0–100:

| Letter | Points | Percentage | | Letter | Points | Percentage |
|:---:|:---:|:---:|---|:---:|:---:|:---:|
| A+ | 4.00 | 95–100 | | C+ | 2.50 | 75–79.99 |
| A | 4.00 | 90–94.99 | | C | 2.00 | 70–74.99 |
| B+ | 3.30 | 85–89.99 | | D+ | 1.30 | 65–69.99 |
| B | 3.00 | 80–84.99 | | D | 1.00 | 60–64.99 |
| | | | | F | 0.00 | below 60 |

This is a **deliberate replacement, not a typo-fix**: it moves the minimum-pass threshold
from 77% to 75% while leaving the 2.5-point requirement untouched, so it changes real
students' letters. The nine values ship as a `LetterGrade` enum carrying its own points and
band as a static matrix (the [ADR-0008] house style, as `CourseStatus` and
`ApplicationStatus` already do). **P and FL are not modelled** — no postgraduate Course is
Pass/Fail, and Preparatory courses are letter-graded and CGPA-counted, which BR-56's C+
retake rule and §4.8's termination criterion both require. **A−/B−/C− are not introduced.**

### The Grade and the Grade Sheet — [ADR-0023](../../docs/adr/0023-grade-sheet-submission-rounds.md)

- **A Grade is the academic result only.** An Instructor enters one **Mark** (0–100, two
  decimals); the Letter Grade and GPA points are derived and **frozen** onto the Grade, so
  a later scale change never rewrites a grade already given. There is no letter override —
  correcting a letter means correcting the mark, keeping `letter == scale(mark)` an
  invariant. No assessment components, weights, or curves.
- **AU / W / FW / I are Course Statuses, never Grades.** An Enrollment in one of those has
  no Grade row at all. Appendix A lists them beside the letters only because a transcript
  prints them in its grade column; that becomes a Phase-6 presentation projection.
- **A Grade Sheet is one submission round for one Offering**, not a unique row per
  Offering. States: `Draft → Submitted → Finalized`, or `Submitted → Returned → Draft`.
  Kinds: `Initial` (every gradable Enrollment), `IncompleteResolution`, `Correction` (only
  the affected lines). **A Finalized Sheet is never reopened.**
- Sheets are created **lazily**, when an Instructor or SA first opens one, not eagerly at
  semester end.
- **Supersession is derived, never stored** (architecture.md's "derive, don't duplicate"):
  the current Grade for an Enrollment is the one on its latest Finalized Sheet, and the
  earlier one remains visible as history.
- **The Sheet routes to the Department Head** of its Offering's Course's Department, via
  `Catalog\Public`. Because `Course.department_id` is nullable ([ADR-0010] — NULL is the
  shared university Preparatory courses), the Head assignment's department reference is
  nullable too, so a NULL Course matches the NULL assignment with **no special case**.
- **Signing is gated on `grades.finalize`**, which SA does not hold; entry is gated on
  `grades.enter`, which SA does. The §3.2 matrix is **untouched** — no new permission and
  no new role mapping, continuing the Phase-2/Phase-3 discipline of authorising by
  assignment. Instructor authority over a Sheet comes from the Offering assignment;
  Department-Head authority from the Department assignment.
- Every SA write to another user's Sheet, and every [ADR-0015] administrative override of a
  scope guard, writes an Identity audit entry.

### GPA and CGPA — closing SRS §4.7.2 and ROADMAP §5

- **Formula:** `Σ(GPA points × credit hours) ÷ Σ(credit hours)` over the Student's
  CGPA-counting, non-superseded, graded Enrollments. Semester GPA is the same sum
  restricted to one Semester. Always computed **on demand, never stored or cached**
  (ROADMAP §1 — no Redis in this build, so it cannot drift).
- **Counting** is the `CourseStatusCounting` matrix Phase 3 already shipped. Every letter
  from A+ to F counts, F at 0.00 included. **Earning** a Course's credit hours is a
  separate `≥ C+` (2.5 points) test, so the CGPA denominator and Earned CH are independent:
  a D counts fully in the CGPA and earns nothing. *(Agenda items 1, 2, 4.)*
- **Retake supersession:** among a Course's for-credit Attempts, only the one in the
  chronologically latest Semester enters the CGPA — ordered by the Semester's `starts_at`,
  **not** by (year, term), since Fall precedes the following Spring. The earlier Attempt
  stays on the transcript and in Attended CH. "Latest" is unconditional: a worse Retake
  genuinely lowers the CGPA, per FR-REG-006.2 and BR-56. *(Agenda item 3.)*
- **Different electives never supersede each other** — they are different Courses, so
  FR-REG-006.4's "ALL grades count" needs **no code at all**.
- **Incomplete is excluded until resolved; FW is excluded permanently.** Transfer, Waive
  and Exempted count toward Required CH and never toward the CGPA — already encoded in the
  matrix, implemented in Phase 5. *(Agenda items 5, 6.)*
- **Precision:** accumulate `Σ(points × 100 × CH)` in integer hundredths so no float drift
  is possible, divide, then **round half-up to two decimals once** — and that single
  rounded figure is authoritative for display *and* for every threshold test (graduation
  `≥ 3.0`, Phase 5's probation `< 3.0`). Display and decisions can never disagree. With no
  CGPA-counting graded Enrollments the CGPA is **null**, not 0.00, and renders as a dash.
  *(Agenda item 7.)*

### Incomplete and Fail Withdrawal

- Declared on a Sheet line in place of a mark, carrying a **completion percentage and a
  reason**. The API rejects a figure below `config('grading.incomplete_min_completion')`
  = 70. It is an **attestation, not a measurement** — [ADR-0019] already ruled that
  §4.7.3.1's "≥70% completed" is about coursework, not attendance.
- **The deadline is derived, never stored:** the `starts_at` of the Semester following the
  Enrollment's, plus 63 days (week 1 begins on `starts_at`, so week 10 begins on day 63).
  Consistent with [ADR-0018]'s rule that period state is always derived. If the next
  Semester does not exist yet the Incomplete simply has no deadline, and the conversion
  command **skips it and reports it** rather than blocking an Instructor for an admin's
  omission. Correcting a Semester's start date moves every outstanding deadline with it.
- **Resolution** is an `IncompleteResolution` Sheet round: the Grade lands on the original
  Enrollment and therefore in its original Semester's GPA, which recomputes retroactively.
  Enrollment restores the pre-Incomplete status.
- **FW is reachable only from an unresolved Incomplete.** §4.7.3.3 is the only place in the
  SRS that creates one, so Appendix D's "After deadline" annotation reads as the Incomplete
  resolution deadline. There is **no administrative late-withdrawal path** and no
  Instructor-assigned FW; Phase 3's "nothing after the withdrawal deadline" stands.

### Grade-gated registration rules (Phase 3's documented hooks)

- **One combined re-registration allowance: at most two for-credit Attempts of any Course,
  ever.** FR-REG-006.1's "maximum once" is the binding cap; §4.4.5's separate "max 1
  Retake" and "max 1 CGPA Improve" notes are per-status descriptions of that same single
  allowance.
- The second Attempt's status is derived from the first's outcome: **below C+ → `Retake`;
  at or above C+ → `CgpaImprovement`**. `CgpaImprovement` joins `burnsRetakeAllowance()`.
  Audit and Withdrawn attempts still consume nothing, exactly as Phase 3 shipped.
- **Registration is refused while an earlier Attempt is an unresolved Incomplete.**
- `RetakeDeriver` is **renamed `AttemptDeriver`** (with its unit test), matching the
  `Attempt` term now in CONTEXT.md and the fact that it routes CGPA Improvement too.
- **A failed external elective releases the lifetime external allowance:** the cap counts
  only External Elective Enrollments whose `earned` is not `false`, so FR-REG-006.4's
  "register for a different elective" works.
- **FR-REG-006.5's "MUST retake" is a flag, not a gate.** A finalized grade below C+ on a
  prep or core Course raises a notification and a persistent must-retake marker on the
  Student's schedule and eligible-course list, visible to the Advisor. The obligation is
  genuinely enforced downstream by Phase 5's Remaining CH and the 3.0 graduation CGPA. This
  matches the house style throughout: attendance audit-at-risk, SLA aging and retake
  derivation all inform rather than coerce, and no automated status change ever fires.

### Identity, Calendar and Catalog changes

- **Identity gains the Department-Head assignment**, mirroring Phase 2's
  `FacultyDeanAssignment` in shape and in its admin UI **gated on the R09/R10 role rather
  than a new permission**. Its department reference is **nullable** — NULL meaning the
  university-wide Preparatory scope — and unique per (user, department). `Identity\Public`
  grows the corresponding read contract.
- **Calendar gains exactly one column**, the grade-submission deadline, joining the
  existing order-validated invariant as `ends_at ≤ grade_submission_deadline_at`, surfaced
  on the existing Semester admin screen. **Exam-period and holiday columns stay deferred** —
  Phase 4 gives them no consumer either, and [ADR-0018] forbids anticipatory schema.
- **Catalog is unchanged.** Grading reads it through the existing `CourseCatalog` /
  `ProgramCatalog` facades.

### Schema

- `grade_sheets` — opaque `course_offering_id` (no FK, [ADR-0012]), kind, status, signer
  and reviewer with timestamps, review note.
- `grades` — FK to its Sheet (intra-module), opaque `enrollment_id`, mark, letter grade,
  GPA points. No superseded column; supersession is derived.
- `incompletes` — opaque `enrollment_id`, the declaring Sheet, declarer, completion
  percentage, reason, and the resolving Grade or conversion timestamp. **No deadline
  column.**
- `enrollments` — gains a nullable `earned` boolean and a nullable private
  pre-Incomplete-status column.
- `semesters` — gains the grade-submission deadline.
- `department_head_assignments` — user, nullable opaque department reference.

### API contract

New `/api/v1` surface under `Grading\Public\Http\routes.php` with controllers in
`Internal`, following the house `{data, meta}` / native `{message, errors}` conventions
and standard codes (422/401/403/404/409 — an illegal Sheet transition is a **409**, as the
Admissions status machine already does):

- the Instructor's Sheets across semesters, including Offerings with no Sheet yet
- open a Sheet round for an Offering; read one Sheet with its lines and roster
- save one line — a mark, or an Incomplete declaration — **per-line, not batch** (the
  Phase-2 per-step-save precedent)
- sign a Sheet; the Department Head's review queue; finalize; return with a note
- the Student's own grades with semester GPAs and CGPA; a scoped read of another Student's
- the Instructor's outstanding Incompletes
- Identity's Department-Head assignment management

### Configuration

`config/grading.php`, following `config/enrollment.php`: the Incomplete minimum completion
(70), the reminder offsets in days (`[28, 14, 7, 1]`), the minimum passing points (2.5) and
the graduation CGPA (3.0) — Appendix C's parameters. **The scale itself lives in the enum,
not config** ([ADR-0008]).

### Console commands

Three manually-run commands, following Phase 2's `admissions:flag-overdue` precedent —
**flag-and-notify only, never a silent status change**, except the conversion which the SRS
explicitly mandates:

- **convert expired Incompletes** → sets Fail Withdrawal and notifies the Student
  (§4.7.3.3, the one command that changes state).
- **send Incomplete reminders** → fires when the days remaining equal exactly one of the
  configured offsets, so running daily produces precisely §4.7.3.4's four reminders and
  **no sent-marker is needed** (Phase 2 established that a hand-run reminder carries none).
- **flag late Sheets** → Offerings past the Semester's grade-submission deadline with no
  Finalized Sheet; notifies the Instructor and the Department Head, changes nothing.

### Notifications

Seven new types, in-app only, rendered bilingually from their payloads:
`grade.sheet_submitted` (Dept Head), `grade.sheet_returned` (Instructor),
`grade.finalized` (Student — carrying the letter and whether it is below C+, so the
must-retake warning needs no second type), `incomplete.declared`, `incomplete.reminder`,
`incomplete.converted` (Student), `grades.overdue` (Instructor + Dept Head).

**Advisors are deliberately excluded.** §4.8's "notify student and advisor" is about
Academic Warning, which is Phase 5's standing module; adding advisor copies now would
duplicate into the same bell.

### Frontend

New `features/grading`, with a new nav section, **and no new dependencies**:

- **Instructor Sheet editor on MUI `Table`** with one controlled numeric input per row and
  a live letter preview via the [ADR-0014] client mirror of the scale. `@mui/x-data-grid`
  stays deferred to Phase 7 — rosters are tens of rows, so virtualization and column
  tooling buy nothing, and a table of labelled inputs is far easier through the axe gate
  than a grid's cell-editing model.
- Instructor outstanding-Incompletes list; Department-Head review queue and Sheet review
  (finalize / return with note); Student My Grades (semester sections with GPA, CGPA
  header, must-retake markers, superseded attempts shown as excluded); Advisor advisee
  grades; SA entry reusing the Sheet editor behind an Offering picker.
- Sheet and Grade states reuse the Phase-0.5 `<StatusChip>` with a colocated
  `statusToChip` map, as every phase does. Colour is never the only signal.
- Identity's Department-Head assignment screen joins the existing `features/admin`
  assignments area beside the Dean assignments.

---

## Testing Decisions

A good test here asserts **externally observable behaviour** — an HTTP response, a
persisted row read back through the API, a Notification visible on the bell endpoint, a
number returned by a `Public` contract — and never a private method, a listener
registration, or a command's internals. The seams below are all **existing kinds**; this
phase introduces no new kind of test.

**Primary seam — HTTP API feature tests** (`tests/Feature/Grading/*`), the highest
available point, driven with `Sanctum::actingAs()`. Prior art: every
`tests/Feature/Enrollment/*`. Covers Sheet opening and lazy creation, per-line save,
mark validation, the blank-line guard on signing, signing, the read-only signed Sheet,
finalize, return-and-resign, the illegal-transition 409, Incomplete declaration and its
70% refusal, resolution rounds, correction rounds and supersession, the Student's grades
and GPA/CGPA payloads, the null-CGPA case, must-retake markers, and every scope guard —
Instructor to own Offerings, Department Head to own Department, the **university-wide Head
reaching the NULL-department Preparatory Sheet**, Advisor to advisees, SA entry-but-not-sign
with its audit row, and the [ADR-0015] override.

**Console-command seam** (`tests/Feature/Grading/*CommandTest.php`), driven by `artisan()`
and asserted through the notifications endpoint and API reads. Prior art:
`OverdueFlaggingCommandTest`, `InterviewReminderCommandTest`. Covers conversion at and
before the deadline, the **missing-next-Semester skip-and-report**, the reminder firing at
exactly 28/14/7/1 days and not between, and late-Sheet flagging.

**Public-facade seam** (`tests/Feature/Grading/GradeBookTest.php`,
`tests/Feature/Enrollment/EnrollmentRecordsTest.php`), exercised through the bound
contract, asserting DTOs and null for missing ids — never the Eloquent models. Prior art:
`CourseCatalogTest`, `ProgramCatalogTest`.

**Pure-unit seam** (`tests/Unit/Grading/*`) for the calculators only, with no HTTP stack or
database. Prior art: `AttendanceCalculatorTest`, `CartValidatorTest`, `SessionScheduleTest`.
Covers the scale mapping **at every band boundary and both sides of each** (59.99/60,
64.99/65, …, 94.99/95, 100) plus out-of-range rejection; quality-points accumulation and
the half-up rounding, including a case that rounds *across* a threshold to prove display
and decision agree; supersession selection ordered by `starts_at` across a Fall→Spring
year boundary; the worse-Retake-still-wins case; the Incomplete deadline arithmetic.

**Modified existing units** — `AttemptDeriverTest` (renamed, plus grade-aware Retake vs
CGPA-Improvement routing, the third-attempt refusal, and the unresolved-Incomplete
refusal), `CartValidatorTest` (external-cap freeing), `CourseStatusTest`
(`CgpaImprovement` burns the allowance), `SemesterCalendarTest` (the new date ordering).

**Vitest + RTL** for the pure mark→letter mirror helper and the Sheet editor's non-trivial
behaviour only. Prior art: `meetingTime.test.ts`, `applicationStatus.test.ts`,
`ApplicationReviewPage.test.tsx`.

**One Playwright smoke** (`frontend/e2e/grading.spec.ts`) plus `@axe-core/playwright` on
the new screens. Prior art: `enrollment.spec.ts`. Multi-role headline flow: Instructor
enters marks and declares an Incomplete → signs → Department Head finalizes → Student sees
grades, semester GPA and CGPA → the conversion command runs → the Student sees the Fail
Withdrawal.

**Deterministic idempotent seeder** extending Phase 3's, over the Phase-1 anchor Programs:
a **completed Spring 2026** whose grades are enterable, a **Fall 2026 already past its
week 10** so a Spring Incomplete is genuinely overdue, Sheets in every state (Draft,
Submitted awaiting review, Returned, Finalized), a Department Head per Department plus one
assigned university-wide, a Student with a superseded Retake, one below-C+ prep grade
carrying a must-retake marker, and one Student with no grades at all for the null-CGPA
case.

**Gates**, per the roadmap Definition of Done: `composer gate` (Pint, PHPStan/Larastan,
deptrac with the new edges, Pest) and `npm run gate` (ESLint + boundaries + the hex rule,
Prettier, `tsc`, Vitest, Playwright), plus Scribe `@urlParam` examples on every new
model-bound route so the `openapi.yaml` drift check does not flake.

---

## Out of Scope

- **Academic Standing and the Credit-Hour Summary** (Phase 5). `GradeFinalized` is the hook
  they will listen on; nothing in this phase computes standing, warnings, probation or
  termination.
- **Exemption, Transfer and Waiver** (Phase 5). Their `CourseStatus` values and counting
  rows already exist; no workflow is built, and the 6/6/9 per-category composition
  accounting stays Phase 5's.
- **Transcripts, official or unofficial, and PDF export** (Phase 6). The transcript's
  grade-column projection of AU/W/FW/I is specified here but rendered there.
- **Reporting** — the GPA-distribution report and the other seven read-only reports
  (Phase 7). The Department Head's distribution view on one Sheet is not a report.
- **Grade appeals** as a student-initiated workflow. The Correction round is
  Instructor-initiated and Department-Head-reviewed; a Student has no appeal surface.
- **Assessment components, weights, curves, and letter overrides.** One final Mark per
  Enrollment; the letter always equals the scale applied to it.
- **P / FL grades and A− / B− / C− grades** — neither exists in this domain.
- **An administrative late-withdrawal path to FW**, and any Instructor-assigned FW.
- **Blocking registration on an outstanding must-retake obligation**, and the
  approaching-graduation credit-hour exception (Phase 5 — it needs Remaining CH).
- **Exam-period and holiday columns** on Semester — still no consumer.
- **Real email or SMS reminders, and a real scheduler.** In-app notifications and manually
  run artisan commands only ([ADR-0005]).
- **Caching the GPA/CGPA.** Redis is out of this build; the read model is computed on
  demand so it cannot drift, and architecture.md's cached-and-invalidated design is a
  documented later optimization.

---

## Further Notes

- **ROADMAP §5's GPA/CGPA agenda is closed by this spec** — all seven items are resolved
  above (1, 2, 4 in *Formula/Counting*; 3 in *Retake supersession*; 5 and 6 in the
  exclusions; 7 in *Precision*). The ROADMAP's Phase 4 section and §5 should be updated to
  point here, and [architecture.md](../../docs/architecture.md) §5 amended for [ADR-0020].
- **Two documents are contradicted deliberately, and both need the ADR to say so plainly:**
  architecture.md §5 on who owns the GPA read model, and SRS Appendix A on the percentage
  bands. The scale change is the more consequential — it moves the minimum-pass threshold
  from 77% to 75%, so a future reader must be able to find out that it was a decision
  rather than a bug.
- **`CONTEXT.md` was updated during the design session**, not by this spec: `Grade` was
  rewritten and `Mark`, `Letter Grade`, `Grade Sheet`, `Attempt`, `Incomplete`, `Fail
  Withdrawal`, `CGPA Improvement`, `Quality Points` added, with `GPA / CGPA` made
  explicitly derived.
- **Phase 3 left three `CourseStatus` values unreachable on purpose.** This phase makes
  `Incomplete`, `FailWithdrawal` and `CgpaImprovement` reachable, leaving only `Transfer`,
  `Waive` and `Exempted` for Phase 5 — at which point the Appendix-D matrix is fully live.
- **The `earned` boolean is the one deliberate duplication in the design**, and
  [ADR-0022] must own that trade-off honestly: it is state pushed across a seam to keep the
  dependency one-way, chosen over a `Public↔Public` cycle. It is written only through the
  facade command, in the same transaction as the finalize, so it cannot drift from the
  Grade that produced it.
- **`HANDOFF.md` is stale** (it still describes the Phase-0 build) and the project memory
  still claims Phase 2 is in progress. Both need refreshing before the build starts.
- **Suggested next steps:** branch `phase-4-grading`, write ADRs 0020–0023, amend the two
  documents, then `/to-issues` for vertical-slice tickets and `/tdd` per ticket.
