# 10 — Seeder + multi-role headline smoke

**What to build:** The capstone. A deterministic, idempotent seeder that stands the phase up in
a state where every behaviour is demonstrable without editing dates by hand, and one
multi-role Playwright smoke that drives the headline flow through the real stack.

The seeder's hard part is **time**. The Incomplete lifecycle only shows itself if a Semester
has actually finished and the *following* Semester is already past its week 10, so an
Incomplete is genuinely overdue and the conversion command has something real to convert. Set
the period windows relative to seed time, the way the Phase-3 seeder already does with its two
out-of-phase Semesters.

The demo this makes possible: an Instructor enters marks and defers one Student, signs; the
Department Head finalizes; the Student sees their grades, Semester GPA and CGPA; the conversion
command runs; the Student sees the Fail Withdrawal.

**Blocked by:** 01, 02, 03, 04, 05, 06, 07, 08, 09 — every other ticket in the phase.

**Status:** ready-for-agent

### Seeder

- [ ] Extends the Phase-3 seeder over the same Phase-1 anchor Programs; deterministic and idempotent, so re-running changes nothing and never duplicates.
- [ ] A **completed** Semester whose grades are enterable, and a following Semester **already past its week 10**, so an Incomplete from the completed one is genuinely overdue at seed time. Windows relative to seed time, not hard-coded dates.
- [ ] The new Semester grade-submission deadlines are seeded, including one Semester already past its deadline with an unfinalized Sheet so the late-flagging command has a subject.
- [ ] Grade Sheets resting in every state: Draft, Submitted awaiting review, Returned with a note, and Finalized.
- [ ] A Department Head assigned per Department **plus one assigned university-wide**, so the department-less Preparatory routing is demonstrable, and at least one Department deliberately left without a Head.
- [ ] Students in varied states: one with a **superseded Retake** (two Attempts of one Course, the later counting); one with a below-C+ **Preparatory or Core** grade carrying a must-retake marker; one with an **outstanding Incomplete** that is not yet overdue; one with an **overdue** Incomplete awaiting conversion; one with a finalized **Correction** in their history; and one with **no grades at all**, for the null-CGPA case.
- [ ] A Student who failed an external elective, so the freed-cap path is demonstrable.
- [ ] Seeded users' identifiers are distinct from anything the smoke creates, so the two never collide — the Phase-2 precedent.
- [ ] A seeder test asserts the shape it promises (the overdue Incomplete really is overdue, the superseded Retake really is superseded), so a drifting seed fails loudly rather than silently weakening the smoke.

### Headline smoke

- [ ] One Playwright spec drives the real stack across roles: Instructor enters marks, declares an Incomplete, signs → Department Head finalizes → Student signs in and sees grades, a Semester GPA and a CGPA → the conversion command runs → the Student sees the Fail Withdrawal.
- [ ] The Student's CGPA is asserted as a **value**, not merely as present, so the arithmetic is exercised end to end rather than the page merely rendering.
- [ ] `@axe-core/playwright` runs over the new screens with no serious or critical violations; per-step screenshots are uploaded as CI artifacts rather than pixel-asserted.
- [ ] The existing Phase-0 through Phase-3 smokes still pass unchanged.

### Phase close-out

- [ ] Full `composer gate` and `npm run gate` green, deptrac at zero violations with all the new edges, and both `openapi.yaml` drift checks clean.
- [ ] The four Phase-4 ADRs (0020–0023) exist and are referenced from the docs that they change: architecture.md's module/read-model section amended for ADR-0020, and the ROADMAP's Phase 4 section rewritten with its §5 GPA/CGPA agenda marked closed.
- [ ] `HANDOFF.md` refreshed — it currently still describes the Phase-0 build — or deleted if the ROADMAP plus git history now serve its purpose.
- [ ] The headline demo verified manually, then handed to the per-phase manual review gate before Phase 5 begins.
