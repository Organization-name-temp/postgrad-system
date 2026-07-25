# 06 — Correction rounds

**What to build:** A mistyped finalized mark stops being permanent. An Instructor opens a
Correction round for a Sheet that is already Finalized, containing only the lines they are
changing; the Department Head finalizes it; the new Grade supersedes the old one while the old
one stays visible in the Student's grade history, so the record shows what changed rather than
pretending it never happened. The Student's affected Semester GPA and their CGPA recompute on
the next read.

Two boundaries matter. A Finalized Sheet is **never reopened** — a Correction is a new round,
so a whole class's settled grades never vanish from their records while one is being fixed
([ADR-0023](../../../docs/adr/0023-grade-sheet-submission-rounds.md)). And this is
deliberately **not** a student-facing appeal: the SRS has no correction or appeal process at
all, so the extension is held to the narrowest thing that closes the hole — Instructor
initiates, Department Head reviews, the write is audited, and a Student has no way to request
one.

**Blocked by:** 04 (GPA/CGPA engine + My Grades).

**Status:** ready-for-agent

- [ ] An Instructor can open a `Correction` round against a Finalized Sheet for an Offering they teach, selecting which lines to correct; unaffected lines are not part of the round.
- [ ] The Correction round travels the same `Draft → Submitted → Finalized` path as any other round, with the same illegal-transition 409s and the same Department-Head routing.
- [ ] The original Finalized Sheet is untouched throughout — no state change, no editable lines, no exceptions. A test asserts it.
- [ ] Once the Correction is finalized, the current Grade for that Enrollment is the corrected one. Currency is **derived** — the Grade on the latest Finalized Sheet — with no superseded flag stored anywhere.
- [ ] The earlier Grade remains readable as history and is presented as superseded, not deleted, on both the Student's and the oversight views.
- [ ] The Student's Semester GPA and CGPA reflect the corrected Grade on the next read, with no invalidation step — which is only safe because the read model is computed on demand.
- [ ] Each finalized Correction writes an Identity audit entry recording who corrected what.
- [ ] A Student cannot initiate a Correction, and no endpoint lets them; there is no appeal surface.
- [ ] Frontend: the Instructor can start a Correction from a Finalized Sheet and pick lines; the Student's grade history shows the superseded Grade alongside the current one.
- [ ] Pest feature tests cover the round trip end to end, the untouched original Sheet, the derived currency after correction, the recomputed GPA and CGPA, the audit row, and the absence of any student-initiated path.
- [ ] Scribe `@urlParam` examples on new model-bound routes; `openapi.yaml` and the Orval client regenerated, both drift checks clean.
- [ ] `composer gate` and `npm run gate` both green; the affected screens pass axe with no serious or critical violations.
