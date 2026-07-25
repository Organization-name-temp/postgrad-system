# The grading scale is replaced with uniform five-point bands; Appendix A's bands are not implementable

**Status:** accepted — deliberately departs from SRS Appendix A / §4.7.1.2

The SRS states the grading scale twice, identically (§4.7.1.2 and Appendix A), and **the
percentage bands are not contiguous**. Sorted downward they leave three three-point holes:

```
A+ 97–100   A 93–96.99   ⟵ 90–92.99 maps to no letter
B+ 87–89.99  B 83–86.99  ⟵ 80–82.99 maps to no letter
C+ 77–79.99  C 73–76.99  ⟵ 70–72.99 maps to no letter
D+ 67–69.99  D 60–66.99   F below 60
```

A student who scores 91 cannot be graded. Since FR-GRD-001.2 requires the system to
*auto-calculate* the letter from the mark, the mapping must be **total over 0–100**, so the
scale cannot be implemented as written and a decision is unavoidable. The owner chose to
replace the bands with **uniform five-point bands**:

| Letter | Points | Percentage | | Letter | Points | Percentage |
|:---:|:---:|:---:|---|:---:|:---:|:---:|
| A+ | 4.00 | 95–100 | | C+ | 2.50 | 75–79.99 |
| A | 4.00 | 90–94.99 | | C | 2.00 | 70–74.99 |
| B+ | 3.30 | 85–89.99 | | D+ | 1.30 | 65–69.99 |
| B | 3.00 | 80–84.99 | | D | 1.00 | 60–64.99 |
| | | | | F | 0.00 | below 60 |

**This is a replacement, not a typo-fix, and the distinction matters.** Eight uniform bands
cover 60–100 with no gaps and no overlaps, but they also **move the minimum-pass threshold
from 77% to 75%** while leaving Appendix C's `min_grade_per_course = 2.5 (C+)` untouched.
Every band except A+'s ceiling and F's floor moves. Real students get different letters
under this scale than under the appendix, so it must be recorded as a decision — otherwise
a future reader comparing code to Appendix A will read it as a bug and "fix" it.

**The GPA points are unchanged.** Only the percentage boundaries move; A+ and A both remain
4.00, so the 95/90 split affects the transcript's letter and nothing in the arithmetic.

**P and FL are not modelled.** Appendix A lists them for "Pass/Fail courses", but no
postgraduate Course is Pass/Fail: nothing in the Catalog marks a Course as such, and
BR-56 / §4.4.6.5 put a **C+ threshold on Preparatory courses** ("prep and core below C+ →
MUST retake"), which only makes sense for a letter-graded, CGPA-counted course. §4.8's
termination criterion on failing prep courses says the same. Preparatory courses are
therefore ordinary letter-graded courses, and the `LetterGrade` type ships nine values with
no unreachable cases.

**A− / B− / C− are not introduced.** They are the obvious way to fill the appendix's three
holes — the gaps sit exactly where a standard scale puts them, at 3.70 / 2.70 / 1.70 — but
they appear nowhere in the SRS or the business requirements. Inventing three letters and
three point values in a build whose premise is SRS fidelity is a larger liberty than
re-drawing boundaries the SRS already got wrong.

**The scale lives in code, not config.** `LetterGrade` carries its own points and band as a
static matrix, the ADR-0008 house style already used by `Role`, `Permission`,
`ApplicationStatus` and `CourseStatus`. Appendix C's genuine tunables (minimum passing
points, graduation CGPA, incomplete completion threshold) go to `config/grading.php`. The
derived letter and points are **frozen onto each Grade when it is recorded**, so a later
change to this table never rewrites a grade already given.

## Considered options

- **Uniform five-point bands (chosen).** Total, uniform, easy to state and to test at every
  boundary. Costs the largest departure from the appendix's numbers.
- **Widen A/B/C downward — 7/3 per decade (rejected).** A 90–96.99, B 80–86.99, C 70–76.99
  closes all three holes while leaving every surviving boundary — including the 77% minimum
  pass — exactly as the appendix has it, and the 7/3 split per decade matches the intact
  D/D+ pair, which is real evidence of the author's intent. The narrowest possible reading,
  but rejected in favour of a uniform scale.
- **Add A− / B− / C− (rejected).** Tidiest arithmetic and closest to how most universities'
  scales read, but fabricates three letters and three GPA point values that no project
  document contains.
- **Widen the + grades upward (rejected).** Also total with no new letters, but makes the +
  bands six points and the plain bands four, contradicting the D/D+ shape, and is the
  reading least generous to students at 90–92.
- **Reject marks that fall in the gaps (rejected).** Faithful to the appendix in the most
  literal sense and operationally absurd — an Instructor could not record a 91.

## Consequences

- The minimum passing mark is **75%**, not the appendix's 77%. Any document, seed, or test
  fixture quoting 77% is wrong.
- `LetterGrade` has exactly nine cases, all reachable, so its unit test can be exhaustive:
  both sides of every boundary (59.99/60, 64.99/65, … 94.99/95) plus 0 and 100, and
  out-of-range rejection.
- No Catalog schema change is needed for a Pass/Fail flag, and Preparatory courses need no
  special case anywhere in the GPA arithmetic — they are 1-CH letter-graded courses, which
  is why credit-weighting rather than a plain mean matters.
- Because the letter and points are frozen per Grade, this table can be changed again later
  without a data migration and without altering any historical record — but also without
  retroactively correcting one, which is the intended trade-off.
