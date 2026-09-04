# Academic Standing owns the student-progress read models

**Status:** accepted — amends [architecture.md](../architecture.md) §4.1 and §4.3, which
previously placed Credit-Hour Summary composition in the Phase 6 Records module

Phase 5 needs both a Student's **Academic Standing** and **Credit-Hour Summary** before
Records exists. Both are current student-progress views derived from the same academic
facts: Enrollment owns Enrollments and Exemption/Transfer/Waiver records, while Grading
owns Grades and the authoritative GPA/CGPA calculation. Creating Records early would mix
live progress monitoring with later document issuance; placing the summary in Enrollment
would make Enrollment pull grade-shaped facts back from Grading and erode the one-way
dependency protected by [ADR-0022](0022-grade-outcome-pushed-to-enrollment.md).

**Decision: the Phase 5 `AcademicStanding` module owns both derived read models, the
advisory Graduation Readiness result, and the Termination workflow.** It computes Academic
Standing, the per-category Credit-Hour Summary, and readiness on demand by consuming
owner-provided DTOs and batch calculations through the
`Enrollment`, `Grading`, `Identity`, and `Catalog` public facades. It does not own or copy
the underlying Enrollments, Grades, exemptions, transfers, or waivers. `Enrollment`
continues to own the three credit-adjustment workflows and their records. Phase 6 Records
composes transcripts from `AcademicStanding\Public`; it does not recalculate either read
model.

## Considered options

- **Academic Standing owns both read models (chosen).** It gives Phase 5 one coherent
  student-progress boundary and one public contract for the Cards and later Reports.
- **Records owns Credit-Hour Summary (rejected).** This follows the original architecture,
  but births a document-issuance module a phase early for a live progress calculation and
  splits two closely related Phase 5 views across modules.
- **Enrollment owns Credit-Hour Summary (rejected).** It keeps credit accounting beside
  Enrollment's rows, but either requires a reverse `Enrollment → Grading` dependency or a
  second copy of grade-derived outcomes beyond the deliberately narrow `earned` bit.

## Consequences

- `AcademicStanding\Public` is the authoritative source for a Student's current Standing
  and Credit-Hour Summary, and the advisory source for Graduation Readiness, including
  single-student and batched forms under
  [ADR-0028](0028-aggregate-facades-sql-php-parity.md).
- The module may depend on `Enrollment\Public`, `Grading\Public`, `Identity\Public`, and
  `Catalog\Public`; no reverse dependency is introduced.
- Enrollment owns post-Exemption-Revocation Remediation Obligations and exposes their
  state/checkpoint DTOs. AcademicStanding consumes them for Preparatory trigger timing and
  never asks Enrollment to read Standing state
  ([ADR-0043](0043-enrollment-owns-remediation-obligations.md)).
- Phase 5's Cards and Phase 7's Academic Standing, Graduation Readiness, and Termination
  Reports live with this module. Phase 6 Records consumes its facade for transcripts.
- The values remain derived on demand and never become editable balances or stored
  snapshots. A stored projection remains only a future optimization behind the facade.
- Credit-Hour Summary satisfaction is target-aware: an effective Exemption removes its
  Course target, so any raw Earned CH later produced by an already-active Enrollment in
  that Course cannot satisfy another target ([ADR-0035](0035-credit-hour-summary-accounting.md)).
- Readiness does not mutate the Identity-owned Student Lifecycle Status and is not a
  formal graduation approval.
- The readiness DTO carries its authoritative boolean, Remaining CH, nullable CGPA,
  Approaching Graduation result, and closed typed Blocker set. Cards and later Reports do
  not rebuild the decision from lower-level facade fields.
