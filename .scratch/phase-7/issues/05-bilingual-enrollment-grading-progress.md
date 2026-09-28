# 05 — Bilingual polish: Enrollment, Grading and academic progress

**What to build:** Students and staff use existing registration, attendance, grading, academic progress and termination-review workflows in either language with clear actions, translated outcomes and correct direction, including complex numeric academic content.

**Blocked by:** None — can start immediately.

**Status:** ready-for-human

- [x] Audit existing registration/advising/on-behalf flows, offerings/schedules/attendance, capacity exceptions, credit decisions, grade sheets/corrections/Incompletes, Student Grades, Credit-Hour Summary, Standing and Termination Case review in EN/LTR and AR/RTL with light/dark themes.
- [x] Record surface coverage and each concrete defect/fix/deferral. Correct untranslated validation and workflow messages, missing status/blocker/notification labels, clipped dialogs/tables and incorrect directional layout without changing academic rules.
- [x] Backend language messages are paired in EN/AR and frontend dictionaries stay aligned. Typed notifications continue to render from their data; distinguish Standing from lifecycle and preserve null CGPA presentation.
- [x] Course codes, credit hours, grades, CGPA, dates and Student identifiers remain readable in Arabic contexts. Numeric inputs, mark previews and error association retain their existing behaviour; statuses use text/icons in addition to token colours.
- [x] Check responsive schedules/tables, confirmation/review dialogs, permission/lifecycle-disabled actions, keyboard access and language switching with populated forms. Preserve effective owner calculations, supersession, registration exceptions and transition authority.
- [x] Test concrete regressions at existing seams using focused frontend interactions and backend message responses. Extend an existing full-stack flow only when the changed interaction/integration warrants it; do not copy academic arithmetic into tests or add broad visual snapshots.
- [x] Run relevant tests and affected package checks and record gate omissions with reasons. The existing academic workflows remain intact; new #01–#03 Reports are tested bilingually in their own tickets.

## Comments

This work has no dependency on the new Reports because it covers implemented workflows. Any issue requiring a new business rule is recorded separately rather than resolved as a polish change.

## Executable disposition

Implemented on `phase-7-academic-reports-bilingual` against fixed baseline
`b3a3ae7b71538c36230985270de2b73bc2f92088`. Focused red/green evidence,
owner/API/UI verification and prerequisite dispositions are recorded in
[the Phase 7 tracker](../tracker.md). The single consolidated
[Standards/Spec review](../review.md) has all five actionable findings resolved.
Both complete package gates passed: backend 1,334 tests / 8,458 assertions;
frontend API/static checks, 121 files / 684 unit tests and all 179 serial browser
checks. The new CI transition regression and complete affected Enrollment/Records
journeys passed together (3/3, 12.4m, exit 0), providing evidence for all 180
current distinct browser tests. Required human demo/Arabic/print acceptance is
pending. Latest GitHub run is pre-fix and failed two browser contrast assertions;
`c1dbe1c` fixes them locally, with remote verification awaiting publication under
the user's no-push instruction. No phase acceptance, push or merge is claimed.
