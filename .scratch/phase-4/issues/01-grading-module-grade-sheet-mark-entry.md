# 01 — Grading module + Grade Sheet + mark entry

**What to build:** An Instructor opens the Grade Sheet for a Course Offering they teach and
records what each Student achieved. The Sheet lists every gradable Enrollment on the roster
with the Student's University ID and name; the Instructor types one final percentage per
Student and immediately sees the Letter Grade and its GPA points, so a mistyped figure is
obvious before anything is committed. Each mark saves as it is entered, so a long roster
survives a closed tab. Nothing is signed or reviewed yet, and no Student can see anything —
this ticket is the tracer bullet that proves the module, the seam, and the scale.

Audit and Withdrawn Enrollments appear on the roster but are never gradable lines: an
Instructor is never asked for a mark that cannot exist.

The grading scale, including why it departs from SRS Appendix A, is
[ADR-0021](../../../docs/adr/0021-grading-scale-uniform-bands.md) — encode it from there
rather than from the SRS. The Sheet's shape and lifecycle is
[ADR-0023](../../../docs/adr/0023-grade-sheet-submission-rounds.md); module ownership is
[ADR-0020](../../../docs/adr/0020-grading-owns-gpa-read-model.md).

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] New `Grading` module with `Public`/`Internal` layers and an auto-discovered provider; deptrac gains `Grading_Internal → {Identity, Catalog, Enrollment}\Public` and `App → Grading_Public`, with every `Internal` still sealed and zero violations.
- [ ] `Enrollment\Public` ships its first surface: the gradable Enrollments of one Offering, and the Offerings one Instructor teaches — returning DTOs, never Eloquent models, with cross-module references as opaque ids and no foreign keys (ADR-0012). A facade test drives it through the bound contract and asserts `null` for a missing id.
- [ ] A `LetterGrade` type carries the nine values and their GPA points and percentage bands as a static matrix in code (the ADR-0008 house style), not in config. Its unit test is exhaustive: both sides of every band boundary, plus 0 and 100, plus out-of-range rejection.
- [ ] An Instructor can open a Grade Sheet for an Offering they teach; the Sheet is created lazily on first open, not eagerly. An Instructor opening an Offering they do not teach gets a 403.
- [ ] The Sheet returns one line per gradable Enrollment with the Student's University ID and name, and omits Audit and Withdrawn Enrollments.
- [ ] A mark is saved one line at a time, not as a batch — the Phase-2 per-step-save precedent. The Letter Grade and GPA points are derived and stored frozen on the Grade, so a later change to the scale never rewrites a Grade already recorded.
- [ ] A mark outside 0–100 is rejected with a bilingual 422 naming the field.
- [ ] Frontend `features/grading` with its own nav section: the Sheet editor is built on MUI `Table` with one controlled numeric input per row and a live letter preview mirroring the scale client-side (ADR-0014). **No new frontend dependencies** — `@mui/x-data-grid` stays deferred to Phase 7.
- [ ] Sheet and line states use the Phase-0.5 `<StatusChip>` with a colocated `statusToChip` map; colour is never the only signal.
- [ ] The mark→letter mirror helper has a Vitest unit test; the Sheet editor has RTL coverage of its non-trivial behaviour only.
- [ ] Every new model-bound route carries a Scribe `@urlParam` example, and the docblock's first line is a short summary so the generated hook name stays clean. `openapi.yaml` and the Orval client are regenerated and both drift checks are clean.
- [ ] `composer gate` and `npm run gate` both green; new screens pass axe with no serious or critical violations, in light and dark, EN and AR.
