# Credit-Hour Summary preserves raw accounting and caps satisfaction

**Status:** accepted

The Credit-Hour Summary must expose the distinct Required, Attended, Earned, Transferred,
Waived, Exempted, and Remaining figures required by SRS Appendix B and BR-65. Exemptions
reduce the requirement, while Transfers and Waivers satisfy it without becoming Earned
credit. Simply capping every component hides real academic history; treating Exemptions as
completed credit contradicts their meaning.

**Decision: `AcademicStanding` calculates the following figures for each of Preparatory,
Program Core, and Research Elective, plus a Total row:**

- `baseline_required_ch` comes from the Student's bound Curriculum Version.
- `exempted_ch` is the total of effective Exemptions.
- `adjusted_required_ch = max(0, baseline_required_ch - exempted_ch)`.
- `attended_ch` is the raw total of Enrollments whose Course Status counts as attended;
  distinct Attempts remain visible in this measure.
- `earned_ch` is the raw credit of each Course's effective passing Attempt under the same
  supersession rule used by Grading.
- `transferred_ch` and `waived_ch` are the raw totals of effective decisions.
- satisfaction first matches contributions to Course targets. Earned CH from a Course
  whose target is removed by an effective Exemption is excluded from the satisfaction
  operand while remaining in raw `earned_ch`.
- `satisfied_ch = min(adjusted_required_ch, target-eligible earned_ch + transferred_ch +
  waived_ch)`.
- `remaining_ch = max(0, adjusted_required_ch - satisfied_ch)`.

`Satisfied CH` is the canonical meaning of the SRS's otherwise ambiguous “Completed CH.”
It is the only completion numerator. Raw measures may exceed a category's requirement, but
Satisfied never does and Remaining never falls below zero. The Total row is the sum of the
three category rows, not a separately calculated balance.

This raw-versus-satisfied distinction also covers replacement Exemptions approved during
an in-progress Enrollment in the released Preparatory Course. Approval leaves that
Enrollment intact. If it later earns credit, Exempted CH and Earned CH both remain visible,
Adjusted Required reflects the Exemption, and target matching excludes that Course's Earned
CH from Satisfied before the cap is applied. The two facts therefore cannot produce
duplicate progress.
The same allocation makes approval of corrected evidence after an already-successful
Attempt neutral to Remaining CH: the Exemption removes the target and its hours from
Adjusted Required while that target's earned contribution leaves Satisfied. Raw Earned and
Exempted values and the Grade/CGPA effect remain visible.

## Considered options

- **Raw measures plus target-de-duplicated, capped Satisfied CH (chosen).** Preserves full
  accounting while ensuring one Course target cannot both reduce and satisfy requirements.
  It adds allocation logic and requires the UI to explain Earned versus Satisfied.
- **Category cap without target matching (rejected).** Looks simpler, but an exempted
  Course's later Earned CH can satisfy a different requirement before the cap is reached,
  understating Remaining CH.
- **Cap every measure (rejected).** Produces compact progress figures, but hides legitimate
  extra study and approved decisions and would force Transcript to use different totals.
- **Keep 21 Required and count Exemptions as completed (rejected).** Makes a simple 21/21
  display, but falsely presents a release from a Course as completed credit and contradicts
  the adjusted-required rule.

## Consequences

- Graduation readiness and the below-minimum registration exception read `remaining_ch`;
  they never reconstruct it from a subset of fields. For the exception, the facade also
  returns the unsatisfied Course targets and category slots: a Student is Approaching
  Graduation only at 1–5 Remaining CH and only a proposed for-credit schedule satisfying
  every remaining requirement may use the sub-6-CH floor. Audit Courses never qualify.
  This result is independent of CGPA and Termination Pending, so their separate readiness
  blockers do not prevent a completing schedule during Probation or due process.
- Academic graduation readiness requires zero Remaining CH and Grading's authoritative
  CGPA of at least 3.00. It does not additionally require Good Standing: Academic Warning
  alone cannot negate otherwise complete curriculum accounting. An actionable Termination
  Pending Case is the explicit temporary exception; dismissal or Trigger Cleared removes
  that block without changing the underlying Summary.
- Graduation Readiness is advisory and derived. It does not change Student Lifecycle
  Status, perform a graduation decision, or claim that non-course and administrative
  checks have passed.
- Its DTO returns `ready`, Remaining CH, nullable CGPA, Approaching Graduation, and typed
  blockers (`RemainingRequirements`, `CgpaUnavailable`, `CgpaBelowMinimum`, or
  `TerminationPending`). Consumers render this result rather than rebuilding it from the
  Summary and Standing independently. The separately returned Approaching Graduation
  result remains about curriculum proximity even when a readiness blocker exists.
- The first-semester exception consumes the same unsatisfied-requirements result. In the
  Student's enrollment Semester, a 1–5 CH post-change schedule may fall below the floor only
  when it contains every remaining non-exempt Preparatory Course. If none remains, the
  ordinary 6-CH floor applies; an Exemption alone never waives the floor.
- UI and API labels use Baseline Required, Adjusted Required, Satisfied, and Remaining
  rather than the overloaded bare words Required and Completed.
- Effective decisions are de-duplicated by their course target before summing, and
  Revoked decisions contribute nothing.
- An in-progress Enrollment is never auto-dropped by a later replacement Exemption. Its
  eventual Earned CH remains raw history even where Exempted CH has already reduced the
  requirement; that target is excluded from the Satisfied operand, which is then capped at
  Adjusted Required.
- Transferred CH uses each decision's target Course hours from the bound Curriculum Version,
  which is also the value enforced against the 9-CH lifetime cap; external source hours are
  retained as evidence only.
- Single-Student and batch facade methods share this calculator and prove parity under
  ADR-0028.
