# Academic Standing is exhaustive and includes Not Yet Assessed

**Status:** accepted — amends SRS §4.8's four-value table

The original four Academic Standing values leave ordinary Students unclassified. A new
Student has no CGPA, while a Student whose only finalized Grade is C+ has CGPA 2.50 but no
Grade below C+ and has not “completed coursework” if that phrase means the entire degree.
Representing these cases as `null` creates an undocumented state; calling them Good
Standing asserts criteria they have not met.

**Decision: Academic Standing is a total, ordered classification with five values.** The
calculator applies this precedence:

1. **Termination Pending** — an actionable termination trigger exists, even if no
   CGPA-bearing Grade has ever been finalized. Evidence already dismissed by the Department
   Head or Council is not actionable until the trigger fingerprint materially changes.
   Preparatory fingerprints version the requirement with its checkpoint source and
   effective academic evidence, so a later Attempt/Withdrawal/decision/Revocation or new
   Remediation Obligation is new evidence while unchanged non-completion stays suppressed.
   All actionable components are aggregated into the Student's single non-terminal
   Termination Case; parallel trigger-specific Cases do not exist.
2. **Not Yet Assessed** — no finalized CGPA-bearing Grade exists and no termination trigger
   is active.
3. **Academic Probation** — authoritative CGPA is below 3.00 once any CGPA-bearing
   coursework has been finalized.
4. **Academic Warning** — CGPA is at least 3.00, but at least one effective Grade is below
   C+.
5. **Good Standing** — CGPA is at least 3.00 and every effective Grade is C+ or higher.

“After completing coursework” in the Probation requirement therefore means after finalized
CGPA-bearing coursework exists, not after all degree requirements are satisfied. The
calculator consumes the authoritative CGPA and effective Grades from `Grading\Public`; it
does not reproduce grade supersession or rounding.

Curriculum progress does not change Grade effectiveness. If a Student fails one elective
and passes a different elective to satisfy the same slot, the original Course's Grade
continues to drive Warning (and CGPA) until a successful later Attempt in that same Course
supersedes it.

Standing describes academic condition; it is not itself a graduation gate. Academic
Warning alone does not block readiness when every curriculum requirement is satisfied and
the authoritative CGPA is at least 3.00. Requiring Good Standing would silently add a
condition absent from the SRS and negate the practical alternative-elective path.
Termination Pending is the explicit exception: its actionable open Case temporarily
blocks graduation readiness until dismissal or Trigger Cleared.
Readiness remains an advisory calculation; it neither changes Student Lifecycle Status nor
represents a formal graduation decision.

## Considered options

- **Add Not Yet Assessed and exhaustively classify (chosen).** Gives every Student one
  explainable value and deterministic precedence. It adds a value absent from the original
  SRS and sharpens the meaning of “after completing coursework.”
- **Keep four values and allow null (rejected).** Preserves the listed enum but pushes an
  undocumented fifth state into every API, filter, Card, and Report.
- **Treat gaps as Good Standing (rejected).** Avoids null and a new value, but labels
  Students good without satisfying the stated CGPA and Grade conditions.

## Consequences

- Cards, lists, filters, exports, notifications, and later Reports use the same five-value
  enum and never invent display-only fallback states.
- A CGPA of exactly 3.00 satisfies the CGPA side of Warning/Good; below means strictly
  `< 3.00`, using Grading's authoritative rounded value from ADR-0020.
- Only effective, non-superseded Grades participate in the below-C+ test.
- Satisfying an elective slot with another Course does not clear a Warning caused by the
  original Grade; progression accounting and Grade supersession remain separate.
- Graduation readiness tests satisfied curriculum and CGPA directly. It does not infer an
  extra Good Standing requirement or reject an otherwise ready Student solely for Warning.
  An actionable Termination Pending Case suspends readiness until dismissed or cleared.
- Termination is evaluated before CGPA availability, so Preparatory non-completion cannot
  be hidden by Not Yet Assessed.
- Academic Probation changes the registration ceiling to the configured minimum CH (6 by
  default). All load-counting Course modes, including Audit, consume that ceiling; the
  approved first-semester and Approaching Graduation exceptions may still permit less.
- Approaching Graduation remains a curriculum-proximity result when Standing is Probation
  or Termination Pending. The Standing/readiness blocker is shown beside it rather than
  suppressing the completing below-minimum schedule.
- Termination Pending uses the same ceiling while the Student Lifecycle Status remains
  Active. Only final Council termination blocks registration mutations, so the derived
  trigger does not pre-empt due process or prevent remediation.
- Standing is still derived on demand. A `StandingChanged` event, if emitted for
  notifications, records a detected transition and does not become the source of truth.
