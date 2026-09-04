# Registration targets Course Offerings; timetabling/LMS growth is seeded structurally, not schematically

**Status:** accepted

Phase 3 adds course registration (SRS §4.4). The SRS itself never defines what a student
registers *into* — and it excludes "physical classroom scheduling" outright (§2.4). We
nonetheless want two future growth paths kept open without building either now: fuller
timetabling, and an LMS-flavored module (materials, assessments, assignments). Two
questions had to be settled: **what an Enrollment references**, and **what a "seed" for
future scope is allowed to look like.**

**Reference — Enrollments point at a `CourseOffering`, never at a Course directly.** The
Enrollment module owns `CourseOffering` = one Course actually taught in one Semester,
carrying the Instructor, an optional seat capacity, and weekly meeting times (child
`offering_meetings` rows: weekday + start/end; a single small support class does the
interval-overlap check at registration). The eligible list for a semester is the
student's Curriculum ∩ that semester's Offerings. This one reference is the load-bearing
seed: sections, timetabling, rosters, and every future LMS artifact attach to
"this course as taught this semester to these students" — the Offering — so none of them
require remodeling the enrollment spine. It also creates the Instructor→Course link that
Phase 4 grading already needs (FR-GRD-001 "courses they teach"), which nothing in the
system recorded before.

**Seeds — structural only, zero placeholder schema.** One Offering per (Semester, Course)
is *enforced by a unique constraint now*; multi-section later means relaxing a constraint
and adding `section_no`, cheap because every flow already points at Offering ids. No
room/location fields, no materials/assessments tables, no speculative enums. The LMS seed
is a module boundary: a future LMS becomes a new module consuming an `Enrollment\Public`
offering/roster surface — which itself ships **empty** in Phase 3 and is built against its
first real consumer (Phase 4 grading), the same YAGNI that served the Catalog facade.
Grading stays Phase 4's Grading module; an LMS would later *feed* the mark, not replace it.

**Snapshot — accounting-critical facts are copied onto the Enrollment at registration.**
Each Enrollment stores its Enrollment Classification (Preparatory / Core / Internal
Elective / External Elective / Research Course) and the course's **total CH at
registration time**. Curricula are versioned and editable (ADR-0011) and course credit
splits can change; CH-limit math, the BR-55/56 retake distinction, and Phase 5 counting
must read what was true when the student registered. Display facts (names, codes, level)
are read live through the Catalog facade (ADR-0012).

## Considered options

- **Offering-referenced enrollment + structural seeds (chosen).** One honest entity does
  triple duty (eligibility gate, instructor link, future attachment point); future scope
  costs a migration, not a remodel.
- **Register directly against the Course (rejected).** Simpler today, but sections,
  rosters, attendance, and LMS artifacts all need "course-as-taught-this-semester";
  retrofitting an Offering under existing enrollments is exactly the remodel the seed
  exists to avoid.
- **Full timetabling (rooms, conflict engine) (rejected).** Explicitly out of scope
  (SRS §2.4); a scheduling engine has no SRS consumer.
- **Placeholder schema for future features (rejected).** Reserved columns/tables are
  untested, unmigrated-against, and the future feature always arrives shaped differently;
  the codebase's precedent (deferred Catalog facade, captured-but-unbuilt exemptions) is
  structural readiness, not speculative DDL.
- **Meeting times as a JSON blob (rejected).** Child rows cost the same today and are the
  only shape a future timetabler can extend with columns and constraints.

## Consequences

- Cross-semester rules (retake detection, external-elective caps) derive course identity
  via the Offering inside the module; cross-module reads stay opaque-id + DTO (ADR-0012).
- Capacity and time-clash are validated wherever the schedule changes (request approval,
  free adds) — the clash check lives in one seam a real engine could later replace.
  [ADR-0042](0042-must-retake-courses-have-registration-priority.md) adds the one capacity
  exception: after Advisor recommendation, the Course's scoped Department Head may give
  reasoned, audited final approval to exceed capacity for a Must-Retake Obligation. It
  neither applies to ordinary registration nor displaces an existing Enrollment.
- A curriculum edit after registration never re-classifies an existing Enrollment; if a
  correction is ever needed it is an explicit administrative action, not a recompute.
- Phase 4 builds `Enrollment\Public` (offerings + roster read surface) against grading;
  a future LMS consumes the same surface without touching Enrollment internals.
