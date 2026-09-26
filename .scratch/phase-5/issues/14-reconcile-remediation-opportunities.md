# 14 — Reconcile remediation opportunities and deadlines

**What to build:** Keep each unused Remediation Obligation aligned with real Course, Offering, and Calendar opportunities while preserving every deadline already granted and providing safe recovery without a scheduler.

**Blocked by:** 13 — Create and release post-Revocation Remediation Obligations.

**Status:** complete

- [x] Initial assignment selects the first main Semester with an eligible Offering in which the Student can lawfully add the Course; the current Semester qualifies only through late-add.
- [x] Enrollment reconciles its own relevant Offering changes synchronously and consumes minimal ID-only Course and Semester change events from Catalog and Calendar.
- [x] Event consumers reread authoritative Public DTOs and never trust copied Course state or Calendar dates in event payloads.
- [x] A newly available opportunity activates Awaiting Offering exactly once; repeated events and commands create no duplicate transition or notification.
- [x] Before Enrollment, institution-controlled loss of the qualifying opportunity selects another eligible same-Semester Offering, then the earliest later lawful opportunity, else Awaiting Offering.
- [x] Full capacity stays an available opportunity through capacity override, and Student-created timetable conflicts do not postpone assignment.
- [x] Once an Enrollment exists for the opportunity, later institutional schedule changes do not reassign it.
- [x] An unevaluated active checkpoint moves later when its Semester Grade-Submission Deadline is extended but never moves earlier; each real observation records both official and retained effective dates.
- [x] Evaluation freezes the effective deadline against subsequent Calendar edits.
- [x] Every real opportunity or deadline change preserves prior assignment evidence and notifies the Student, current Advisor, and scoped Department Head once.
- [x] A global or per-Student remediation reconciliation command invokes the same idempotent service as local operations and events.
- [x] PostgreSQL feature tests cover date/window boundaries, same-versus-later Semester selection, Course/Offering activation changes, full capacity, Student conflicts, pre/post-Enrollment behavior, monotonic dates, retry recovery, generated contracts, and bilingual UI status.
